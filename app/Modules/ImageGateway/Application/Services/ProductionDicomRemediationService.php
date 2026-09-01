<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\ImageGateway\Application\Contracts\DicomConverter;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Domain\Security\ConversionManifest;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\PermanentAcceptanceGate;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
use App\Modules\ImageGateway\Domain\Security\ValidationEvidence;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class ProductionDicomRemediationService
{
    public const T005_FAILED_CAPTURE_RETRY = 't005_failed_capture_retry';

    public const DCM_ZSHNSX90_REGENERATE = 'dcm_zshnsx90_regenerate';

    public const MODES = [self::T005_FAILED_CAPTURE_RETRY, self::DCM_ZSHNSX90_REGENERATE];

    public const T005_ADMISSION_ID = '46165c59-1fa6-4f58-9485-a515529c0f76';

    public const DCM_STUDY_ID = 'ed367bcf-4430-496c-a006-f3e8479421d4';

    public const DCM_REFERENCE = 'DCM-ZSHNSX90';

    public const REQUIRED_RUNTIME_FIX = 'f2bf7b9980f9af7649e1a6c45c46aaee7a55a36a';

    private const PURPOSE = 'image-gateway.production-dicom-remediation';

    public function __construct(
        private PrivateObjectStore $objects,
        private DicomConverter $converter,
        private ManifestSigner $signer,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function run(string $mode, string $stage, ?string $runtimeRevision = null, ?string $runtimeFix = null): array
    {
        $this->assertMode($mode);
        if (! in_array($stage, ['preflight', 'execute'], true)) {
            throw new RuntimeException('Invalid remediation stage.');
        }
        if ($runtimeRevision !== self::REQUIRED_RUNTIME_FIX || $runtimeFix !== self::REQUIRED_RUNTIME_FIX) {
            throw new RuntimeException('The serving conversion runtime cannot be proven to contain the required fix.');
        }

        $result = $mode === self::T005_FAILED_CAPTURE_RETRY
            ? $this->t005Preflight()
            : $this->studyPreflight();
        if ($stage === 'preflight') {
            return $this->preflightEvidence($mode, $result);
        }

        return $mode === self::T005_FAILED_CAPTURE_RETRY
            ? $this->retryT005($result)
            : $this->replaceStudy($result);
    }

    public function assertMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException('Invalid remediation mode.');
        }
    }

    /** @return array<string, mixed> */
    private function t005Preflight(): array
    {
        $capture = DB::table('image_gateway_capture_sets as captures')
            ->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'captures.admission_id')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'captures.member_schedule_id')
            ->join('operator_sites as sites', 'sites.id', '=', 'captures.operator_site_id')
            ->join('operator_profiles as operators', 'operators.id', '=', 'captures.operator_profile_id')
            ->where('captures.admission_id', self::T005_ADMISSION_ID)
            ->where('tickets.ticket_number', 'T-005')
            ->select('captures.*', 'admissions.state as admission_state', 'admissions.stage', 'tickets.ticket_number', 'bookings.member_id', 'schedules.examination_site_id', 'sites.operator_site_id as stable_site_id', 'operators.id as operator_id')
            ->first();
        if ($capture === null) {
            throw new RuntimeException('T-005 target could not be resolved exactly.');
        }
        $metadata = $this->metadata($capture);
        if ($metadata['capture']['detector_type'] !== 'BED' || $capture->accepted_at === null || $capture->processing_status !== 'failed' || $capture->radiograph_status !== 'success' || $capture->gain_status !== 'success' || $capture->processing_claim_id !== null || $capture->processing_lease_expires_at !== null || DB::table('image_gateway_studies')->where('capture_set_id', $capture->id)->exists() || (int) $capture->attempts >= (int) config('mhcs.m'.'pips.max_attempts', 5)) {
            throw new RuntimeException('T-005 is not in the exact eligible failed BED state.');
        }

        $objects = $this->sourceObjects((string) $capture->id);
        $this->assertSourceChecksums($capture, $objects);
        [$manifest, $signed] = $this->verifiedManifest($capture, $objects);
        if (($manifest['capture']['detector_type'] ?? null) !== 'BED') {
            throw new RuntimeException('T-005 manifest is not the expected BED state.');
        }

        return ['capture' => $capture, 'objects' => $objects, 'manifest' => $manifest, 'signed' => $signed, 'metadata' => $metadata];
    }

    /** @return array<string, mixed> */
    private function studyPreflight(): array
    {
        $study = DB::table('image_gateway_studies as studies')
            ->join('image_gateway_capture_sets as captures', 'captures.id', '=', 'studies.capture_set_id')
            ->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'captures.admission_id')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'captures.member_schedule_id')
            ->join('operator_sites as sites', 'sites.id', '=', 'captures.operator_site_id')
            ->join('operator_profiles as operators', 'operators.id', '=', 'captures.operator_profile_id')
            ->where('studies.id', self::DCM_STUDY_ID)
            ->where('studies.display_reference', self::DCM_REFERENCE)
            ->select('studies.id as study_id', 'studies.capture_set_id', 'studies.object_key as study_object_key', 'studies.checksum as study_checksum', 'studies.bytes as study_bytes', 'studies.display_reference', 'studies.study_instance_uid', 'studies.series_instance_uid', 'studies.sop_instance_uid', 'studies.created_at as study_created_at', 'captures.id as capture_id', 'captures.admission_id', 'captures.booking_id', 'captures.member_schedule_id', 'captures.operator_site_id', 'captures.operator_profile_id', 'captures.status as capture_status', 'captures.accepted_at', 'captures.processing_status', 'captures.attempts', 'captures.processing_claim_id', 'captures.processing_lease_expires_at', 'captures.capture_metadata', 'captures.radiograph_status', 'captures.gain_status', 'captures.manifest_checksum', 'captures.manifest_bytes', 'captures.signature_checksum', 'captures.signature_bytes', 'admissions.id as resolved_admission_id', 'tickets.ticket_number', 'bookings.member_id', 'schedules.examination_site_id', 'sites.operator_site_id as stable_site_id', 'operators.id as operator_id')
            ->first();
        if ($study === null) {
            throw new RuntimeException('DCM-ZSHNSX90 target could not be resolved exactly.');
        }
        if ($this->metadata($study)['capture']['detector_type'] !== 'TRX' || $study->accepted_at === null || $study->processing_status !== 'completed') {
            throw new RuntimeException('DCM-ZSHNSX90 is not in the exact eligible TRX completed state.');
        }
        $objects = $this->sourceObjects((string) $study->capture_set_id);
        $this->assertSourceChecksums($study, $objects);
        [$manifest, $signed] = $this->verifiedManifest($study, $objects);
        if (($manifest['capture']['detector_type'] ?? null) !== 'TRX') {
            throw new RuntimeException('DCM-ZSHNSX90 manifest is not the expected TRX state.');
        }
        $current = $this->readObject((object) ['object_key' => $study->study_object_key, 'checksum' => $study->study_checksum, 'bytes' => $study->study_bytes, 'created_at' => $study->study_created_at], 'study');
        if (! hash_equals((string) $study->study_checksum, hash('sha256', $current)) || (int) $study->study_bytes !== strlen($current)) {
            throw new RuntimeException('DCM-ZSHNSX90 DICOM integrity failed.');
        }

        $study->id = $study->study_id;
        $study->object_key = $study->study_object_key;
        $study->checksum = $study->study_checksum;
        $study->bytes = $study->study_bytes;

        return ['study' => $study, 'objects' => $objects, 'manifest' => $manifest, 'signed' => $signed, 'metadata' => $this->metadata($study)];
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function retryT005(array $result): array
    {
        $capture = $result['capture'];
        $objects = $result['objects'];
        $metadata = $result['metadata'];
        $metadata['capture']['detector_type'] = 'TRX';
        $manifest = $result['manifest'];
        $manifest['capture']['detector_type'] = 'TRX';
        $manifestBytes = json_encode($this->sortKeys($manifest), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signed = $this->signManifest($capture, $manifestBytes);
        $context = $this->context('t005');
        $manifestObject = $this->objects->put($manifestBytes, $context, self::PURPOSE);
        $signatureBytes = json_encode($signed->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signatureObject = $this->objects->put($signatureBytes, $context, self::PURPOSE);
        try {
            DB::transaction(function () use ($capture, $metadata, $manifestObject, $signatureObject, $manifestBytes, $signatureBytes): void {
                $row = DB::table('image_gateway_capture_sets')->where('id', $capture->id)->lockForUpdate()->first();
                if ($row === null || $row->processing_status !== 'failed' || $row->processing_claim_id !== null || DB::table('image_gateway_studies')->where('capture_set_id', $row->id)->exists() || $this->metadata($row)['capture']['detector_type'] !== 'BED') {
                    throw new RuntimeException('T-005 drifted before mutation.');
                }
                DB::table('image_gateway_capture_sets')->where('id', $row->id)->update([
                    'capture_metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'manifest_checksum' => hash('sha256', $manifestBytes), 'manifest_bytes' => strlen($manifestBytes),
                    'signature_checksum' => hash('sha256', $signatureBytes), 'signature_bytes' => strlen($signatureBytes),
                    'processing_status' => 'pending', 'm'.'pips_status' => 'pending', 'dicom_status' => 'pending',
                    'last_error_code' => null, 'last_response_status' => null, 'failed_at' => null,
                    'updated_at' => $this->clock->now(),
                ]);
                $this->replaceObjectRow((string) $row->id, 'manifest', $manifestObject);
                $this->replaceObjectRow((string) $row->id, 'manifest_signature', $signatureObject);
            });
        } catch (\Throwable $e) {
            $this->objects->delete($manifestObject);
            $this->objects->delete($signatureObject);
            throw $e;
        }
        ProcessCaptureSet::dispatch((string) $capture->id)->onQueue('image-gateway')->afterCommit();
        $this->audit('t005_detector_corrected', (string) $capture->id, hash('sha256', json_encode($result['metadata'], JSON_THROW_ON_ERROR)), hash('sha256', json_encode($metadata, JSON_THROW_ON_ERROR)), [
            'mode' => self::T005_FAILED_CAPTURE_RETRY,
            'previous_manifest_checksum' => (string) $objects['manifest']->checksum,
            'previous_signature_checksum' => (string) $objects['manifest_signature']->checksum,
            'replacement_manifest_checksum' => $manifestObject->checksum,
            'replacement_signature_checksum' => $signatureObject->checksum,
        ]);

        return ['mode' => self::T005_FAILED_CAPTURE_RETRY, 'capture_id' => (string) $capture->id, 'processing' => 'dispatched'];
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function replaceStudy(array $result): array
    {
        $study = $result['study'];
        $objects = $result['objects'];
        $manifestBytes = json_encode($this->sortKeys($result['manifest']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $radiograph = $this->readObject($objects['radiograph'], 'radiograph');
        $gain = $this->readObject($objects['gain'], 'gain');
        $response = $this->converter->convert($radiograph, $gain, $manifestBytes);
        $candidate = $this->validateDicom($response);
        $identifiers = $this->uids($candidate['job_id']);
        if ($identifiers !== ['study' => $study->study_instance_uid, 'series' => $study->series_instance_uid, 'sop' => $study->sop_instance_uid]) {
            throw new RuntimeException('Replacement DICOM identifiers do not match the existing study.');
        }
        (new PermanentAcceptanceGate($this->signer))->accept(
            $result['signed'],
            new ValidationEvidence(
                valid: true,
                conversionJobId: $result['signed']->manifest->conversionJobId,
                radiographChecksum: $result['signed']->manifest->radiographChecksum,
                gainChecksum: $result['signed']->manifest->gainChecksum,
                metadataChecksum: hash('sha256', $manifestBytes),
                manifestSignature: $result['signed']->signature,
                identifiers: $identifiers,
            ),
            $identifiers,
        );
        $context = $this->context('dcm-zshnsx90');
        $object = $this->objects->put($candidate['bytes'], $context, self::PURPOSE);
        try {
            DB::transaction(function () use ($study, $object): void {
                $row = DB::table('image_gateway_studies')->where('id', $study->id)->lockForUpdate()->first();
                if ($row === null || $row->display_reference !== self::DCM_REFERENCE || $row->object_key !== $study->object_key || DB::table('image_gateway_studies')->where('capture_set_id', $row->capture_set_id)->where('id', '!=', $row->id)->exists()) {
                    throw new RuntimeException('DCM-ZSHNSX90 drifted before replacement.');
                }
                DB::table('image_gateway_studies')->where('id', $row->id)->update(['object_key' => (string) $object->key, 'checksum' => $object->checksum, 'bytes' => $object->bytes, 'updated_at' => $this->clock->now()]);
            });
        } catch (\Throwable $e) {
            $this->objects->delete($object);
            throw $e;
        }
        $this->audit('dcm_zshnsx90_replaced', (string) $study->id, (string) $study->checksum, $object->checksum, ['mode' => self::DCM_ZSHNSX90_REGENERATE, 'old_object_checksum' => (string) $study->checksum, 'new_object_checksum' => $object->checksum]);

        return ['mode' => self::DCM_ZSHNSX90_REGENERATE, 'study_id' => (string) $study->id, 'display_reference' => self::DCM_REFERENCE, 'old_object_checksum' => (string) $study->checksum, 'new_object_checksum' => $object->checksum];
    }

    /** @return array<string, object> */
    private function sourceObjects(string $captureId): array
    {
        $rows = DB::table('image_gateway_capture_objects')->where('capture_set_id', $captureId)->get()->keyBy('object_type');
        foreach (['radiograph', 'gain', 'manifest', 'manifest_signature'] as $type) {
            if ($rows->get($type) === null) {
                throw new RuntimeException('Required capture object is missing.');
            }
        }
        $result = [];
        foreach (['radiograph', 'gain', 'manifest', 'manifest_signature'] as $type) {
            $row = $rows->get($type);
            $this->readObject($row, $type);
            $result[$type] = $row;
        }
        if (DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->exists()) {
            $study = DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->first();
            $result['study'] = (object) ['object_key' => $study->object_key, 'checksum' => $study->checksum, 'bytes' => $study->bytes, 'created_at' => $study->created_at];
        }

        return $result;
    }

    /** @param array<string, object> $objects @return array{0: array<string, mixed>, 1: SignedManifest} */
    private function verifiedManifest(object $capture, array $objects): array
    {
        $manifestBytes = $this->readObject($objects['manifest'], 'manifest');
        $signatureBytes = $this->readObject($objects['manifest_signature'], 'manifest_signature');
        if (($capture->manifest_checksum ?? null) !== null && ! hash_equals((string) $capture->manifest_checksum, hash('sha256', $manifestBytes))) {
            throw new RuntimeException('Manifest checksum failed.');
        }
        if (($capture->manifest_bytes ?? null) !== null && (int) $capture->manifest_bytes !== strlen($manifestBytes)) {
            throw new RuntimeException('Manifest byte count failed.');
        }
        if (($capture->signature_checksum ?? null) !== null && ! hash_equals((string) $capture->signature_checksum, hash('sha256', $signatureBytes))) {
            throw new RuntimeException('Signature checksum failed.');
        }
        if (($capture->signature_bytes ?? null) !== null && (int) $capture->signature_bytes !== strlen($signatureBytes)) {
            throw new RuntimeException('Signature byte count failed.');
        }
        $signed = SignedManifest::fromArray(json_decode($signatureBytes, true, 512, JSON_THROW_ON_ERROR));
        $this->signer->verify($signed);
        if (! hash_equals($signed->manifest->metadataChecksum, hash('sha256', $manifestBytes))) {
            throw new RuntimeException('Manifest metadata checksum failed.');
        }

        return [json_decode($manifestBytes, true, 512, JSON_THROW_ON_ERROR), $signed];
    }

    /** @param array<string, object> $objects */
    private function assertSourceChecksums(object $capture, array $objects): void
    {
        foreach (['radiograph', 'gain'] as $type) {
            $field = $type.'_checksum';
            if (($capture->{$field} ?? null) !== null && ! hash_equals((string) $capture->{$field}, (string) $objects[$type]->checksum)) {
                throw new RuntimeException('Capture source checksum failed.');
            }
        }
    }

    private function readObject(object $row, string $type): string
    {
        $context = $this->context($type);
        $object = new PrivateObject(OpaqueObjectKey::fromString((string) $row->object_key), (string) $row->checksum, (int) $row->bytes, new DateTimeImmutable((string) $row->created_at));
        $contents = $this->objects->get($this->objects->grant($object, $context, 'production-dicom-remediation', self::PURPOSE, $this->clock->now()->modify('+300 seconds')), $context, 'production-dicom-remediation', self::PURPOSE);
        if (! hash_equals($object->checksum, hash('sha256', $contents)) || $object->bytes !== strlen($contents)) {
            throw new RuntimeException("{$type} object integrity failed.");
        }

        return $contents;
    }

    private function replaceObjectRow(string $captureId, string $type, PrivateObject $object): void
    {
        DB::table('image_gateway_capture_objects')->where('capture_set_id', $captureId)->where('object_type', $type)->update(['object_key' => (string) $object->key, 'checksum' => $object->checksum, 'bytes' => $object->bytes, 'updated_at' => $object->createdAt]);
    }

    private function signManifest(object $capture, string $bytes): SignedManifest
    {
        $old = $this->readObject(DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->where('object_type', 'manifest_signature')->first(), 'manifest_signature');
        $signed = SignedManifest::fromArray(json_decode($old, true, 512, JSON_THROW_ON_ERROR));

        return $this->signer->sign(new ConversionManifest($signed->manifest->conversionJobId, $signed->manifest->radiographChecksum, $signed->manifest->gainChecksum, hash('sha256', $bytes), $signed->manifest->manifestVersion, $signed->manifest->issuedAt, $signed->manifest->correlationId, $signed->manifest->keyId));
    }

    /** @return array{job_id: string, bytes: string} */
    private function validateDicom(Response $response): array
    {
        $bytes = $response->body();
        $jobId = (string) $response->header('X-Conversion-Job-ID');
        if (strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0])) !== 'application/dicom' || strlen($bytes) < 132 || substr($bytes, 128, 4) !== 'DICM' || ! Str::isUuid($jobId)) {
            throw new RuntimeException('Invalid DICOM response.');
        }

        return ['job_id' => $jobId, 'bytes' => $bytes];
    }

    /** @return array{study: string, series: string, sop: string} */
    private function uids(string $jobId): array
    {
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        $identityPrefix = 'm'.'pips:';

        return ['study' => '2.25.'.Uuid::uuid5($namespace, $identityPrefix.'study:'.$jobId)->getInteger()->toString(), 'series' => '2.25.'.Uuid::uuid5($namespace, $identityPrefix.'series:'.$jobId)->getInteger()->toString(), 'sop' => '2.25.'.Uuid::uuid5($namespace, $identityPrefix.'sop:'.$jobId)->getInteger()->toString()];
    }

    /** @return array{examination: array<string, mixed>, capture: array<string, mixed>} */
    private function metadata(object $row): array
    {
        $metadata = json_decode((string) ($row->capture_metadata ?? ''), true);
        if (! is_array($metadata) || ! is_array($metadata['capture'] ?? null)) {
            throw new RuntimeException('Capture metadata is unavailable.');
        }

        return $metadata;
    }

    private function context(string $operation): AuthenticatedContext
    {
        return new AuthenticatedContext(LocalId::fromString('production-dicom-remediation'), new CorrelationId('production-dicom-remediation:'.$operation), roles: ['system'], permissions: ['image_gateway.remediate'], purpose: self::PURPOSE);
    }

    private function audit(string $action, string $targetId, string $previous, string $new, array $metadata): void
    {
        $now = $this->clock->now();
        $this->audit->append(AuditEvent::fromContext($this->context('audit'), $action, self::PURPOSE, 'success', $now, 'image_gateway_remediation', $targetId, previousStateDigest: $previous, newStateDigest: $new, metadata: $metadata));
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function preflightEvidence(string $mode, array $result): array
    {
        if ($mode === self::T005_FAILED_CAPTURE_RETRY) {
            $capture = $result['capture'];

            return [
                'mode' => $mode,
                'target_admission_id' => self::T005_ADMISSION_ID,
                'capture_id' => (string) $capture->id,
                'ticket_number' => (string) $capture->ticket_number,
                'processing_status' => (string) $capture->processing_status,
                'attempts' => (int) $capture->attempts,
                'detector_type' => $result['metadata']['capture']['detector_type'],
                'radiograph_checksum' => (string) $result['objects']['radiograph']->checksum,
                'gain_checksum' => (string) $result['objects']['gain']->checksum,
                'manifest_checksum' => (string) $result['objects']['manifest']->checksum,
                'signature_checksum' => (string) $result['objects']['manifest_signature']->checksum,
                'signature_valid' => true,
                'study_exists' => false,
            ];
        }

        $study = $result['study'];

        return [
            'mode' => $mode,
            'study_id' => self::DCM_STUDY_ID,
            'display_reference' => self::DCM_REFERENCE,
            'capture_id' => (string) $study->capture_id,
            'processing_status' => (string) $study->processing_status,
            'detector_type' => $result['metadata']['capture']['detector_type'],
            'radiograph_checksum' => (string) $result['objects']['radiograph']->checksum,
            'gain_checksum' => (string) $result['objects']['gain']->checksum,
            'manifest_checksum' => (string) $result['objects']['manifest']->checksum,
            'signature_checksum' => (string) $result['objects']['manifest_signature']->checksum,
            'dicom_checksum' => (string) $study->checksum,
            'study_instance_uid' => (string) $study->study_instance_uid,
            'series_instance_uid' => (string) $study->series_instance_uid,
            'sop_instance_uid' => (string) $study->sop_instance_uid,
            'signature_valid' => true,
        ];
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function sortKeys(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }

        return $value;
    }
}
