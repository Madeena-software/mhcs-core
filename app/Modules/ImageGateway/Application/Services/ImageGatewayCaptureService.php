<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Domain\Security\ConversionManifest;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\PermanentAcceptanceGate;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
use App\Modules\ImageGateway\Domain\Security\ValidationEvidence;
use App\Modules\ImageGateway\Infrastructure\MpipsClient;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Storage\AccessGrant;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use GuzzleHttp\Promise\Utils;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

final readonly class ImageGatewayCaptureService
{
    public const CAPTURE_PURPOSE = 'image-gateway.capture.submit';

    public const STUDY_PURPOSE = 'image-gateway.study.read';

    private const AUDIENCE = 'operator-study';

    public function __construct(
        private PrivateObjectStore $objects,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
        private ManifestSigner $signer,
        private MpipsClient $mpips,
    ) {}

    /** @return array{submission_id: string, missing: list<string>, status: string} */
    public function captureForm(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
    ): array {
        $this->assertContext($context, self::CAPTURE_PURPOSE);
        $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, false, true);

        $capture = DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId)->first();
        $missing = $capture === null ? ['radiograph', 'gain'] : array_values(array_filter([
            $capture->radiograph_status === 'success' ? null : 'radiograph',
            $capture->gain_status === 'success' ? null : 'gain',
        ]));

        return [
            'submission_id' => (string) ($capture->submission_id ?? Str::uuid()),
            'missing' => $missing,
            'status' => (string) ($capture->status ?? 'capturing'),
        ];
    }

    /** @return array{capture_id: string, study_id: ?string, status: string, missing: list<string>} */
    public function submit(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
        string $submissionId,
        ?UploadedFile $radiograph,
        ?UploadedFile $gain,
    ): array {
        $this->assertContext($context, self::CAPTURE_PURPOSE);
        $submissionId = trim($submissionId);
        if (! Str::isUuid($submissionId)) {
            throw new ImageGatewayException('capture_invalid', 'A valid submission identity is required.');
        }

        $admission = $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, false, true);
        $existing = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
        $uploads = [
            'radiograph' => $this->assertUpload($radiograph, $existing?->radiograph_status !== 'success'),
            'gain' => $this->assertUpload($gain, $existing?->gain_status !== 'success'),
        ];
        $this->assertRetryChecksums($existing, $uploads);
        $captureContext = $context->forPurpose(self::CAPTURE_PURPOSE);

        try {
            $result = $this->idempotency->run(
                $submissionId,
                self::CAPTURE_PURPOSE,
                ['admission_id' => $admissionId, 'operator_profile_id' => $profileId, 'site_id' => $siteId, 'operator_site_id' => $operatorSiteId],
                function () use ($admission, $profileId, $siteId, $submissionId, $admissionId, $uploads): array {
                    $existing = DB::table('image_gateway_capture_sets')->where('submission_id', $submissionId)->first();
                    if ($existing !== null) {
                        if ((string) $existing->admission_id !== $admissionId) {
                            throw new ImageGatewayException('capture_conflict', 'The submission identity was reused for another admission.');
                        }

                        return ['capture_id' => (string) $existing->id];
                    }
                    if (DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId)->exists()) {
                        throw new ImageGatewayException('capture_conflict', 'This X-ray admission already has a capture set.');
                    }
                    $captureId = (string) Str::uuid();
                    $now = $this->clock->now();
                    DB::table('image_gateway_capture_sets')->insert([
                        'id' => $captureId,
                        'submission_id' => $submissionId,
                        'admission_id' => $admissionId,
                        'booking_id' => (string) $admission->booking_id,
                        'member_schedule_id' => (string) $admission->member_schedule_id,
                        'operator_site_id' => $siteId,
                        'operator_profile_id' => $profileId,
                        'radiograph_count' => 1,
                        'status' => 'capturing',
                        'processing_status' => 'pending',
                        'attempts' => 0,
                        'radiograph_checksum' => $uploads['radiograph']['checksum'],
                        'gain_checksum' => $uploads['gain']['checksum'],
                        'radiograph_status' => 'pending',
                        'gain_status' => 'pending',
                        'mpips_status' => 'pending',
                        'dicom_status' => 'pending',
                        'accepted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return ['capture_id' => $captureId];
                },
            )->result;
            $capture = DB::table('image_gateway_capture_sets')->where('id', $result['capture_id'])->first();
            if ($capture === null) {
                throw new ImageGatewayException('capture_failure', 'The capture could not be accepted.');
            }
            $this->advance($capture, $admission, $profileId, $siteId, $operatorSiteId, $submissionId, $uploads, $captureContext);

            return $this->captureState((string) $capture->id);
        } catch (IdempotencyConflict $exception) {
            throw new ImageGatewayException('capture_conflict', 'The submission identity was reused with different capture data.', $exception);
        } catch (Throwable $exception) {
            if ($exception instanceof ImageGatewayException) {
                throw $exception;
            }
            throw new ImageGatewayException('capture_failure', 'The capture could not be accepted.', $exception);
        }
    }

    /** @return list<array{study_id: string, format: string, rows: ?int, columns: ?int, accepted_at: string}> */
    public function studies(AuthenticatedContext $context, string $profileId, string $siteId, string $operatorSiteId): array
    {
        $this->assertContext($context, self::STUDY_PURPOSE);

        return $this->authorizedStudiesQuery($profileId, $siteId, $operatorSiteId)
            ->select(['studies.id', 'studies.format', 'studies.rows', 'studies.columns', 'captures.accepted_at'])
            ->orderByDesc('captures.accepted_at')
            ->get()
            ->map(static fn (object $study): array => [
                'study_id' => (string) $study->id,
                'format' => (string) $study->format,
                'rows' => $study->rows === null ? null : (int) $study->rows,
                'columns' => $study->columns === null ? null : (int) $study->columns,
                'accepted_at' => (string) $study->accepted_at,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function study(AuthenticatedContext $context, string $profileId, string $siteId, string $operatorSiteId, string $studyId): array
    {
        $this->assertContext($context, self::STUDY_PURPOSE);
        $study = $this->authorizedStudy($profileId, $siteId, $operatorSiteId, $studyId);

        return [
            'study_id' => (string) $study->id,
            'capture_id' => (string) $study->capture_set_id,
            'admission_id' => (string) $study->admission_id,
            'format' => (string) $study->format,
            'checksum' => (string) $study->checksum,
            'bytes' => (int) $study->bytes,
            'filename' => (string) $study->filename,
            'window_center' => $study->window_center,
            'window_width' => $study->window_width,
            'rows' => $study->rows === null ? null : (int) $study->rows,
            'columns' => $study->columns === null ? null : (int) $study->columns,
        ];
    }

    public function dicom(AuthenticatedContext $context, string $profileId, string $siteId, string $operatorSiteId, string $studyId): string
    {
        $this->assertContext($context, self::STUDY_PURPOSE);
        $study = $this->authorizedStudy($profileId, $siteId, $operatorSiteId, $studyId);
        $object = new PrivateObject(
            OpaqueObjectKey::fromString((string) $study->object_key),
            (string) $study->checksum,
            (int) $study->bytes,
            new DateTimeImmutable((string) $study->created_at),
        );
        $studyContext = $context->forPurpose(self::STUDY_PURPOSE);
        $grant = $this->objects->grant(
            $object,
            $studyContext,
            self::AUDIENCE,
            self::STUDY_PURPOSE,
            $this->clock->now()->modify('+'.(int) config('mhcs.security.asset_grants.max_ttl_seconds', 300).' seconds'),
        );

        return $this->objects->get($grant, $studyContext, self::AUDIENCE, self::STUDY_PURPOSE);
    }

    private function assertContext(AuthenticatedContext $context, string $purpose): void
    {
        if ($context->actorId === null || $context->operationId === null || $context->purpose !== $purpose) {
            throw new ImageGatewayException('capture_forbidden', 'A trusted Operator context is required.');
        }
    }

    /** @return array{path: string, bytes: int, checksum: string}|null */
    private function assertUpload(?UploadedFile $file, bool $required): ?array
    {
        if ($file === null) {
            if ($required) {
                throw new ImageGatewayException('capture_invalid', 'Exactly one non-empty NPZ pair is required.');
            }

            return null;
        }
        $path = $file->getRealPath();
        $bytes = is_string($path) && is_file($path) ? filesize($path) : false;
        $name = strtolower((string) $file->getClientOriginalName());
        $header = false;
        if (is_string($path) && is_file($path)) {
            $handle = fopen($path, 'rb');
            if ($handle !== false) {
                $header = fread($handle, 4);
                fclose($handle);
            }
        }
        $maxFileBytes = (int) config('mhcs.upload.max_file_bytes');
        if ($file->getError() !== UPLOAD_ERR_OK || ! str_ends_with($name, '.npz') || ! is_int($bytes) || $bytes < 1 || $bytes > $maxFileBytes || $header !== "PK\x03\x04") {
            throw new ImageGatewayException('capture_invalid', 'NPZ uploads must be non-empty ZIP files within the size limit.');
        }
        $checksum = is_string($path) ? hash_file('sha256', $path) : false;
        if (! is_string($checksum)) {
            throw new ImageGatewayException('capture_invalid', 'The NPZ upload could not be checked.');
        }

        return ['path' => $path, 'bytes' => $bytes, 'checksum' => $checksum];
    }

    /** @param array{radiograph: array{checksum: string}|null, gain: array{checksum: string}|null} $uploads */
    private function assertRetryChecksums(?object $capture, array $uploads): void
    {
        if ($capture === null) {
            return;
        }

        foreach (['radiograph', 'gain'] as $type) {
            if ($uploads[$type] === null || (string) $capture->{$type.'_status'} === 'success') {
                continue;
            }

            $expected = $capture->{$type.'_checksum'} ?? null;
            if (! is_string($expected) || ! hash_equals($expected, $uploads[$type]['checksum'])) {
                throw new ImageGatewayException('capture_invalid', 'The NPZ does not match the original capture.');
            }
        }
    }

    /** @param array{path: string, bytes: int, checksum: string}|null $radiograph */
    /** @param array{path: string, bytes: int, checksum: string}|null $gain */
    private function advance(object $capture, object $admission, string $profileId, string $siteId, string $operatorSiteId, string $submissionId, array $uploads, AuthenticatedContext $context): void
    {
        $captureId = (string) $capture->id;
        $manifest = $this->ensureManifest($capture, $admission, $profileId, $siteId, $submissionId, $context);
        $objects = DB::table('image_gateway_capture_objects')->where('capture_set_id', $captureId)->get()->keyBy('object_type');
        $promises = [];
        $handles = [];
        foreach (['radiograph', 'gain'] as $type) {
            $upload = $uploads[$type];
            $status = (string) $capture->{$type.'_status'};
            if ($upload !== null && $status === 'success') {
                throw new ImageGatewayException('capture_conflict', 'A completed capture component cannot be replaced.');
            }
            if ($upload === null || $status === 'success') {
                continue;
            }
            $handle = fopen($upload['path'], 'rb');
            if ($handle === false) {
                throw new ImageGatewayException('capture_failure', 'The temporary NPZ upload is unavailable.');
            }
            $handles[] = $handle;
            $promises[$type] = $this->objects->putStreamAsync(
                $handle,
                $upload['bytes'],
                $upload['checksum'],
                $context,
                self::CAPTURE_PURPOSE,
                OpaqueObjectKey::fromString('objects/'.$captureId.'/'.$type),
            );
        }

        $mpipsPromise = null;
        $mpipsHandles = [];
        if ((string) $capture->mpips_status === 'pending' && ! DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->exists()) {
            $mpipsStreams = [];
            foreach (['radiograph', 'gain'] as $type) {
                $upload = $uploads[$type];
                if ($upload !== null && (string) $capture->{$type.'_status'} !== 'success') {
                    $stream = fopen($upload['path'], 'rb');
                } elseif ($objects->get($type) !== null) {
                    $stream = $this->readObjectStream($objects->get($type), $context);
                } else {
                    $stream = false;
                }
                if ($stream === false) {
                    $mpipsStreams = [];
                    break;
                }
                $mpipsStreams[$type] = $stream;
                $mpipsHandles[] = $stream;
            }
            if (count($mpipsStreams) === 2) {
                $mpipsPromise = $this->mpips->convertStreams($mpipsStreams['radiograph'], $mpipsStreams['gain'], $manifest['bytes']);
                $promises['mpips'] = $mpipsPromise;
            }
        }

        if ($promises !== []) {
            $settled = Utils::settle($promises)->wait();
            foreach (['radiograph', 'gain'] as $type) {
                if (! isset($settled[$type])) {
                    continue;
                }
                if ($settled[$type]['state'] === 'fulfilled') {
                    $this->recordObject($captureId, $type, $settled[$type]['value'], 'application/x-npz');
                    DB::table('image_gateway_capture_sets')->where('id', $captureId)->update([$type.'_status' => 'success', 'updated_at' => $this->clock->now()]);
                } else {
                    DB::table('image_gateway_capture_sets')->where('id', $captureId)->update([$type.'_status' => 'failed', 'updated_at' => $this->clock->now()]);
                }
            }
            if (isset($settled['mpips'])) {
                if ($settled['mpips']['state'] !== 'fulfilled') {
                    DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['mpips_status' => 'failed', 'processing_status' => 'retrying', 'last_error_code' => 'transport_failure', 'updated_at' => $this->clock->now()]);
                } else {
                    $this->acceptMpipsResponse($captureId, $settled['mpips']['value'], $manifest['signature'], $context);
                }
            }
        }
        foreach (array_merge($handles, $mpipsHandles) as $handle) {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $current = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
        if ($current === null) {
            return;
        }
        if ($current->radiograph_status === 'success' && $current->gain_status === 'success') {
            $this->acceptSources($current, $admission, $profileId, $operatorSiteId, $submissionId, $context);
            if ($current->mpips_status === 'failed' && ! DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->exists()) {
                ProcessCaptureSet::dispatch($captureId)->onQueue('image-gateway');
            }
        }
    }

    /** @return array{bytes: string, signature: string} */
    private function ensureManifest(object $capture, object $admission, string $profileId, string $siteId, string $submissionId, AuthenticatedContext $context): array
    {
        $objects = DB::table('image_gateway_capture_objects')->where('capture_set_id', $capture->id)->get()->keyBy('object_type');
        if ($objects->get('manifest') !== null && $objects->get('manifest_signature') !== null) {
            return [
                'bytes' => $this->objects->get($this->grantForRow($objects->get('manifest'), $context), $context, 'capture-intent', self::CAPTURE_PURPOSE),
                'signature' => $this->objects->get($this->grantForRow($objects->get('manifest_signature'), $context), $context, 'capture-intent', self::CAPTURE_PURPOSE),
            ];
        }
        $now = $this->clock->now();
        $bytes = $this->manifest($admission, $profileId, $siteId, $now);
        $signed = $this->signer->sign(new ConversionManifest(
            conversionJobId: (string) $capture->id,
            radiographChecksum: (string) $capture->radiograph_checksum,
            gainChecksum: (string) $capture->gain_checksum,
            metadataChecksum: hash('sha256', $bytes),
            manifestVersion: 1,
            issuedAt: $now,
            correlationId: $submissionId,
            keyId: (string) config('mhcs.security.manifest_key_id'),
        ));
        $signature = json_encode($signed->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->recordObject((string) $capture->id, 'manifest', $this->objects->put($bytes, $context, self::CAPTURE_PURPOSE), 'application/json');
        $this->recordObject((string) $capture->id, 'manifest_signature', $this->objects->put($signature, $context, self::CAPTURE_PURPOSE), 'application/json');
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update([
            'manifest_checksum' => hash('sha256', $bytes),
            'manifest_bytes' => strlen($bytes),
            'signature_checksum' => hash('sha256', $signature),
            'signature_bytes' => strlen($signature),
            'updated_at' => $now,
        ]);

        return ['bytes' => $bytes, 'signature' => $signature];
    }

    private function readObjectStream(object $row, AuthenticatedContext $context)
    {
        return $this->objects->getStream($this->grantForRow($row, $context), $context, 'capture-intent', self::CAPTURE_PURPOSE);
    }

    private function grantForRow(object $row, AuthenticatedContext $context): AccessGrant
    {
        return $this->objects->grant(new PrivateObject(
            OpaqueObjectKey::fromString((string) $row->object_key),
            (string) $row->checksum,
            (int) $row->bytes,
            new DateTimeImmutable((string) $row->created_at),
        ), $context, 'capture-intent', self::CAPTURE_PURPOSE, $this->clock->now()->modify('+300 seconds'));
    }

    private function recordObject(string $captureId, string $type, PrivateObject $object, string $format): void
    {
        DB::table('image_gateway_capture_objects')->updateOrInsert(
            ['capture_set_id' => $captureId, 'object_type' => $type, 'object_index' => 0],
            [
                'id' => (string) Str::uuid(),
                'object_key' => (string) $object->key,
                'checksum' => $object->checksum,
                'bytes' => $object->bytes,
                'format' => $format,
                'created_at' => $object->createdAt,
                'updated_at' => $object->createdAt,
            ],
        );
    }

    private function acceptSources(object $capture, object $admission, string $profileId, string $operatorSiteId, string $submissionId, AuthenticatedContext $context): void
    {
        $now = $this->clock->now();
        if ($capture->accepted_at !== null) {
            return;
        }
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update(['status' => 'accepted', 'accepted_at' => $now, 'updated_at' => $now]);
        DB::table('operator_queue_admissions')->where('id', $capture->admission_id)->update(['state' => 'awaiting_ai', 'updated_at' => $now]);
        DB::table('operator_queue_admission_history')->insert([
            'id' => (string) Str::uuid(), 'operator_queue_admission_id' => $capture->admission_id, 'operator_profile_id' => $profileId,
            'event_type' => 'capture_accepted', 'from_state' => (string) $admission->state, 'to_state' => 'awaiting_ai', 'operation_id' => $submissionId,
            'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $metadata = ['capture_id' => (string) $capture->id, 'admission_id' => (string) $capture->admission_id, 'operator_site_id' => $operatorSiteId, 'status' => 'accepted'];
        $this->audit->append(AuditEvent::fromContext($context, 'image-gateway.capture-accepted', 'image-gateway', 'success', $now, 'image-gateway.capture-set', (string) $capture->id, metadata: $metadata));
        $this->outbox->record(new VersionedDomainEvent(LocalId::fromString((string) Str::uuid()), 'image-gateway-capture-accepted', 1, $now, $metadata, LocalId::fromString((string) $capture->id), $context->operationId));
    }

    private function acceptMpipsResponse(string $captureId, mixed $response, string $signature, AuthenticatedContext $context): void
    {
        if (! $response instanceof Response || ! $response->successful()) {
            DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['mpips_status' => 'failed', 'processing_status' => 'retrying', 'last_error_code' => 'remote_failure', 'last_response_status' => $response instanceof Response ? $response->status() : null, 'updated_at' => $this->clock->now()]);

            return;
        }
        try {
            $result = $this->validateResponse($response);
            $signed = SignedManifest::fromArray(json_decode($signature, true, 512, JSON_THROW_ON_ERROR));
            $capture = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
            (new PermanentAcceptanceGate($this->signer))->accept($signed, new ValidationEvidence(true, $signed->manifest->conversionJobId, (string) $capture->radiograph_checksum, (string) $capture->gain_checksum, (string) $capture->manifest_checksum, $signed->signature, $this->uids($result['job_id'])), $this->uids($result['job_id']));
            try {
                $this->persistDicom($capture, $result, $context);
            } catch (Throwable) {
                $this->persistDicom($capture, $result, $context);
            }
            DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['mpips_status' => 'success', 'dicom_status' => 'success', 'processing_status' => 'completed', 'updated_at' => $this->clock->now()]);
        } catch (Throwable $exception) {
            DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['mpips_status' => 'failed', 'processing_status' => 'retrying', 'last_error_code' => str_contains($exception->getMessage(), 'invalid_transport_response') ? 'transport_invalid' : 'processing_failure', 'updated_at' => $this->clock->now()]);
        }
    }

    /** @return array{job_id: string, correlation_id: string, bytes: string, filename: string} */
    private function validateResponse(Response $response): array
    {
        $bytes = $response->body();
        $jobId = (string) $response->header('X-Conversion-Job-ID');
        $correlationId = (string) $response->header('X-Correlation-ID');
        if (strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0])) !== 'application/dicom' || strlen($bytes) < 132 || substr($bytes, 128, 4) !== 'DICM' || ! Str::isUuid($jobId) || ! Str::isUuid($correlationId)) {
            throw new \RuntimeException('invalid_transport_response');
        }

        return ['job_id' => $jobId, 'correlation_id' => $correlationId, 'bytes' => $bytes, 'filename' => 'capture-'.str_replace('-', '', $jobId).'.dcm'];
    }

    /** @param array{job_id: string, correlation_id: string, bytes: string, filename: string} $result */
    private function persistDicom(object $capture, array $result, AuthenticatedContext $context): void
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('dicom_persistence_failure');
        }
        fwrite($stream, $result['bytes']);
        rewind($stream);
        try {
            $object = $this->objects->putStream($stream, strlen($result['bytes']), hash('sha256', $result['bytes']), $context, self::CAPTURE_PURPOSE, OpaqueObjectKey::fromString('objects/'.$capture->id.'/dicom'));
            $identifiers = $this->uids($result['job_id']);
            DB::table('image_gateway_studies')->updateOrInsert(['capture_set_id' => $capture->id], [
                'id' => (string) Str::uuid(), 'object_key' => (string) $object->key, 'checksum' => $object->checksum, 'bytes' => $object->bytes, 'format' => 'application/dicom', 'filename' => $result['filename'],
                'study_instance_uid' => $identifiers['study'], 'series_instance_uid' => $identifiers['series'], 'sop_instance_uid' => $identifiers['sop'], 'transfer_syntax' => null, 'window_center' => null, 'window_width' => null, 'rows' => null, 'columns' => null, 'created_at' => $object->createdAt, 'updated_at' => $object->createdAt,
            ]);
        } finally {
            fclose($stream);
        }
    }

    /** @return array{study: string, series: string, sop: string} */
    private function uids(string $jobId): array
    {
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        return ['study' => '2.25.'.Uuid::uuid5($namespace, 'mpips:study:'.$jobId)->getInteger()->toString(), 'series' => '2.25.'.Uuid::uuid5($namespace, 'mpips:series:'.$jobId)->getInteger()->toString(), 'sop' => '2.25.'.Uuid::uuid5($namespace, 'mpips:sop:'.$jobId)->getInteger()->toString()];
    }

    /** @return array{capture_id: string, study_id: ?string, status: string, missing: list<string>} */
    private function captureState(string $captureId): array
    {
        $capture = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
        $study = DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->value('id');

        return ['capture_id' => $captureId, 'study_id' => $study === null ? null : (string) $study, 'status' => (string) $capture->status, 'missing' => array_values(array_filter([$capture->radiograph_status === 'success' ? null : 'radiograph', $capture->gain_status === 'success' ? null : 'gain']))];
    }

    private function admission(string $profileId, string $siteId, string $operatorSiteId, string $admissionId, bool $lock, bool $allowAccepted = false): object
    {
        $query = DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('admissions.id', $admissionId)
            ->where('admissions.operator_site_id', $siteId)
            ->where('member_sites.operator_site_id', $operatorSiteId)
            ->where('admissions.queue_class', 'advance')
            ->where('admissions.stage', 'xray')
            ->whereIn('admissions.state', $allowAccepted ? ['called', 'in_service', 'awaiting_ai'] : ['called', 'in_service'])
            ->where('admissions.operator_profile_id', $profileId)
            ->whereExists(function ($query) use ($profileId, $operatorSiteId): void {
                $query->selectRaw('1')
                    ->from('operator_shift_assignments as assignments')
                    ->join('operator_eligible_shifts as eligible', 'eligible.id', '=', 'assignments.operator_eligible_shift_id')
                    ->whereColumn('eligible.member_schedule_id', 'admissions.member_schedule_id')
                    ->where('eligible.operator_site_id', $operatorSiteId)
                    ->where('assignments.operator_profile_id', $profileId)
                    ->where('assignments.status', 'active')
                    ->where('eligible.sync_status', 'eligible');
            })
            ->select('admissions.*', 'tickets.booking_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $admission = $query->first();

        if ($admission === null) {
            throw new ImageGatewayException('capture_forbidden', 'The X-ray admission is unavailable to this Operator.');
        }

        return $admission;
    }

    private function manifest(object $admission, string $profileId, string $siteId, DateTimeImmutable $now): string
    {
        $source = DB::table('bookings')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->leftJoin('service_offerings', 'service_offerings.id', '=', 'bookings.service_offering_id')
            ->where('bookings.id', $admission->booking_id)
            ->select([
                'bookings.service_code_snapshot',
                'service_offerings.name as study_description',
                'members.id as member_id',
                'members.medical_record_number',
                'members.name as member_name',
                'members.administrative_gender',
                'members.birth_date',
            ])
            ->first();

        if ($source === null) {
            throw new ImageGatewayException('capture_failure', 'Authoritative capture metadata is unavailable.');
        }
        $site = DB::table('operator_sites')->where('id', $siteId)->first();
        $operator = DB::table('operator_profiles')->where('id', $profileId)->first();
        if ($site === null || $operator === null) {
            throw new ImageGatewayException('capture_failure', 'Authoritative capture metadata is unavailable.');
        }

        $manifest = [
            'examination' => [
                'study_description' => (string) ($source->study_description ?? $source->service_code_snapshot),
                'performed_at' => $now->format(DATE_ATOM),
                'service_request_id' => (string) $admission->booking_id,
                'encounter_id' => (string) $admission->booking_id,
            ],
            'patient' => [
                'member_id' => (string) $source->member_id,
                'medical_record_number' => (string) $source->medical_record_number,
                'name' => (string) $source->member_name,
                'birth_date' => (string) $source->birth_date,
            ],
            'operator' => [
                'operator_id' => $profileId,
                'name' => (string) ($operator->display_name ?? $profileId),
            ],
            'site' => [
                'organization_id' => (string) $site->organization_id,
                'site_id' => (string) $site->operator_site_id,
                'institution_name' => (string) $site->display_name,
            ],
            'capture' => ['captured_at' => $now->format(DATE_ATOM)],
        ];
        $sex = match (strtolower((string) $source->administrative_gender)) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other' => 'other',
            default => null,
        };
        if ($sex !== null) {
            $manifest['patient']['sex'] = $sex;
        }

        return json_encode($this->sortKeys($manifest), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $value */
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

    private function authorizedStudy(string $profileId, string $siteId, string $operatorSiteId, string $studyId): object
    {
        if (! Str::isUuid($studyId)) {
            throw new ImageGatewayException('study_forbidden', 'The DICOM study is unavailable.');
        }
        $study = $this->authorizedStudiesQuery($profileId, $siteId, $operatorSiteId)
            ->where('studies.id', $studyId)
            ->select('studies.*', 'captures.admission_id')
            ->first();
        if ($study === null) {
            throw new ImageGatewayException('study_forbidden', 'The DICOM study is unavailable to this Operator.');
        }

        return $study;
    }

    private function authorizedStudiesQuery(string $profileId, string $siteId, string $operatorSiteId): Builder
    {
        return DB::table('image_gateway_studies as studies')
            ->join('image_gateway_capture_sets as captures', 'captures.id', '=', 'studies.capture_set_id')
            ->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'captures.admission_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'captures.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('captures.status', 'accepted')
            ->where('captures.operator_site_id', $siteId)
            ->where('member_sites.operator_site_id', $operatorSiteId)
            ->whereExists(function ($query) use ($profileId, $operatorSiteId): void {
                $query->selectRaw('1')
                    ->from('operator_shift_assignments as assignments')
                    ->join('operator_eligible_shifts as eligible', 'eligible.id', '=', 'assignments.operator_eligible_shift_id')
                    ->whereColumn('eligible.member_schedule_id', 'captures.member_schedule_id')
                    ->where('eligible.operator_site_id', $operatorSiteId)
                    ->where('assignments.operator_profile_id', $profileId)
                    ->where('assignments.status', 'active')
                    ->where('eligible.sync_status', 'eligible');
            });
    }

    /** @param list<PrivateObject> $stored */
    private function deleteStored(array $stored): void
    {
        foreach ($stored as $object) {
            $this->objects->delete($object);
        }
    }
}
