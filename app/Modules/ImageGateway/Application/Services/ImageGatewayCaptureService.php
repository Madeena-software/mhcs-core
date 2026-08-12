<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Domain\Security\ConversionManifest;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class ImageGatewayCaptureService
{
    public const CAPTURE_PURPOSE = 'image-gateway.capture.submit';

    public const STUDY_PURPOSE = 'image-gateway.study.read';

    private const AUDIENCE = 'operator-study';

    private const MAX_FILE_BYTES = 104857600;

    private const MAX_PAIR_BYTES = 314572800;

    public function __construct(
        private PrivateObjectStore $objects,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
        private ManifestSigner $signer,
    ) {}

    /** @return array<string, string> */
    public function captureForm(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
    ): array {
        $this->assertContext($context, self::CAPTURE_PURPOSE);
        $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, false);

        return [];
    }

    /** @return array{capture_id: string, study_id: null, status: string} */
    public function submit(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
        string $submissionId,
        UploadedFile $radiograph,
        UploadedFile $gain,
    ): array {
        $this->assertContext($context, self::CAPTURE_PURPOSE);
        $submissionId = trim($submissionId);
        if (! Str::isUuid($submissionId)) {
            throw new ImageGatewayException('capture_invalid', 'A valid submission identity is required.');
        }

        $admission = $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, false, true);
        [$radiographBytes, $gainBytes] = $this->assertUploads($radiograph, $gain);
        $payload = [
            'admission_id' => $admissionId,
            'booking_id' => (string) $admission->booking_id,
            'member_schedule_id' => (string) $admission->member_schedule_id,
            'operator_site_id' => $operatorSiteId,
            'operator_profile_id' => $profileId,
            'radiograph_checksum' => hash('sha256', $radiographBytes),
            'gain_checksum' => hash('sha256', $gainBytes),
        ];
        $stored = [];
        $captureContext = $context->forPurpose(self::CAPTURE_PURPOSE);

        try {
            return $this->idempotency->run(
                $submissionId,
                self::CAPTURE_PURPOSE,
                $payload,
                function () use (
                    &$stored,
                    $captureContext,
                    $profileId,
                    $siteId,
                    $operatorSiteId,
                    $admissionId,
                    $submissionId,
                    $radiographBytes,
                    $gainBytes,
                ): array {
                    $admission = $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, true, true);
                    if (DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId)->exists()) {
                        throw new ImageGatewayException('capture_conflict', 'This X-ray admission already has a capture set.');
                    }

                    $captureId = (string) Str::uuid();
                    $now = $this->clock->now();
                    $manifestBytes = $this->manifest($admission, $profileId, $siteId, $now);
                    $signed = $this->signer->sign(new ConversionManifest(
                        conversionJobId: $captureId,
                        radiographChecksum: hash('sha256', $radiographBytes),
                        gainChecksum: hash('sha256', $gainBytes),
                        metadataChecksum: hash('sha256', $manifestBytes),
                        manifestVersion: 1,
                        issuedAt: $now,
                        correlationId: $captureContext->operationId === null ? $captureId : (string) $captureContext->operationId,
                        keyId: (string) config('mhcs.security.manifest_key_id'),
                    ));
                    $signatureBytes = json_encode($signed->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

                    $objects = [
                        'radiograph' => $this->objects->put($radiographBytes, $captureContext, self::CAPTURE_PURPOSE),
                        'gain' => $this->objects->put($gainBytes, $captureContext, self::CAPTURE_PURPOSE),
                        'manifest' => $this->objects->put($manifestBytes, $captureContext, self::CAPTURE_PURPOSE),
                        'manifest_signature' => $this->objects->put($signatureBytes, $captureContext, self::CAPTURE_PURPOSE),
                    ];
                    foreach ($objects as $object) {
                        $stored[] = $object;
                    }

                    DB::table('image_gateway_capture_sets')->insert([
                        'id' => $captureId,
                        'submission_id' => $submissionId,
                        'admission_id' => $admissionId,
                        'booking_id' => (string) $admission->booking_id,
                        'member_schedule_id' => (string) $admission->member_schedule_id,
                        'operator_site_id' => $siteId,
                        'operator_profile_id' => $profileId,
                        'radiograph_count' => 1,
                        'status' => 'accepted',
                        'processing_status' => 'queued',
                        'attempts' => 0,
                        'manifest_checksum' => hash('sha256', $manifestBytes),
                        'manifest_bytes' => strlen($manifestBytes),
                        'signature_checksum' => hash('sha256', $signatureBytes),
                        'signature_bytes' => strlen($signatureBytes),
                        'accepted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $rows = [];
                    foreach ($objects as $type => $object) {
                        $rows[] = [
                            'id' => (string) Str::uuid(),
                            'capture_set_id' => $captureId,
                            'object_type' => $type,
                            'object_index' => 0,
                            'object_key' => (string) $object->key,
                            'checksum' => $object->checksum,
                            'bytes' => $object->bytes,
                            'format' => $type === 'radiograph' || $type === 'gain' ? 'application/x-npz' : 'application/json',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('image_gateway_capture_objects')->insert($rows);
                    DB::table('operator_queue_admissions')->where('id', $admissionId)->update([
                        'state' => 'awaiting_ai',
                        'updated_at' => $now,
                    ]);
                    DB::table('operator_queue_admission_history')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_queue_admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'event_type' => 'capture_accepted',
                        'from_state' => (string) $admission->state,
                        'to_state' => 'awaiting_ai',
                        'operation_id' => $submissionId,
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $metadata = [
                        'capture_id' => $captureId,
                        'admission_id' => $admissionId,
                        'booking_id' => (string) $admission->booking_id,
                        'operator_site_id' => $operatorSiteId,
                        'operator_profile_id' => $profileId,
                        'source_checksums' => [$objects['radiograph']->checksum, $objects['gain']->checksum],
                        'status' => 'accepted',
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $captureContext,
                        'image-gateway.capture-accepted',
                        'image-gateway',
                        'success',
                        $now,
                        'image-gateway.capture-set',
                        $captureId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'image-gateway-capture-accepted',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($captureId),
                        $captureContext->operationId,
                    ));
                    ProcessCaptureSet::dispatch($captureId)->onQueue('image-gateway')->afterCommit();

                    return ['capture_id' => $captureId, 'study_id' => null, 'status' => 'accepted'];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            $this->deleteStored($stored);
            throw new ImageGatewayException('capture_conflict', 'The submission identity was reused with different capture data.', $exception);
        } catch (ImageGatewayException $exception) {
            $this->deleteStored($stored);
            throw $exception;
        } catch (Throwable $exception) {
            $this->deleteStored($stored);
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
            'AES-256-GCM',
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

    /** @return array{0: string, 1: string} */
    private function assertUploads(UploadedFile $radiograph, UploadedFile $gain): array
    {
        $bytes = [];
        foreach ([$radiograph, $gain] as $file) {
            $name = strtolower((string) $file->getClientOriginalName());
            if ($file->getError() !== UPLOAD_ERR_OK || ! str_ends_with($name, '.npz')) {
                throw new ImageGatewayException('capture_invalid', 'Exactly one non-empty NPZ pair is required.');
            }
            $contents = $file->get();
            if (! is_string($contents) || $contents === '' || strlen($contents) > self::MAX_FILE_BYTES || ! preg_match('/\APK(?:\x03\x04|\x05\x06|\x07\x08)/', $contents)) {
                throw new ImageGatewayException('capture_invalid', 'NPZ uploads must be non-empty ZIP files within the size limit.');
            }
            $bytes[] = $contents;
        }
        if (strlen($bytes[0]) + strlen($bytes[1]) > self::MAX_PAIR_BYTES) {
            throw new ImageGatewayException('capture_invalid', 'The NPZ pair exceeds the request size limit.');
        }

        return [$bytes[0], $bytes[1]];
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
