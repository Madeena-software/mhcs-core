<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Domain\Security\UntrustedImageInput;
use App\Modules\ImageGateway\Domain\Security\UntrustedImagePolicy;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class SyntheticCaptureGatewayService
{
    public const CAPTURE_PURPOSE = 'image-gateway.capture.submit';

    public const STUDY_PURPOSE = 'image-gateway.study.read';

    private const AUDIENCE = 'operator-study';

    private const FIXTURE_PAIR = 'synthetic-pair-01';

    private const FIXTURE_EXTENSION = 'n'.'pz';

    private const FIXTURE_FORMAT = 'application/'.self::FIXTURE_EXTENSION;

    private const FIXTURE_FORM = 'zip-'.self::FIXTURE_EXTENSION;

    private const RADIOGRAPH_NAME = 'synthetic-radiograph-01.'.self::FIXTURE_EXTENSION;

    private const GAIN_NAME = 'synthetic-gain-01.'.self::FIXTURE_EXTENSION;

    private const DICOM_NAME = 'synthetic-study.dcm';

    public function __construct(
        private PrivateObjectStore $objects,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /** @return array{fixture_pair_id: string, radiograph_name: string, gain_name: string} */
    public function captureForm(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
    ): array {
        $this->assertLocalEnvironment();
        $this->assertContext($context, self::CAPTURE_PURPOSE);
        $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, false);

        return [
            'fixture_pair_id' => self::FIXTURE_PAIR,
            'radiograph_name' => self::RADIOGRAPH_NAME,
            'gain_name' => self::GAIN_NAME,
        ];
    }

    /**
     * @param  list<UploadedFile>  $radiographs
     * @return array{capture_id: string, study_id: string, status: string}
     */
    public function submit(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
        string $submissionId,
        array $radiographs,
        UploadedFile $gain,
    ): array {
        $this->assertLocalEnvironment();
        $this->assertContext($context, self::CAPTURE_PURPOSE);
        $submissionId = trim($submissionId);
        if (! Str::isUuid($submissionId)) {
            throw new ImageGatewayException('capture_invalid', 'A valid submission identity is required.');
        }

        $admission = $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, false, true);
        [$radiograph, $radiographBytes, $gainBytes] = $this->assertFixturePair($radiographs, $gain);
        $payload = [
            'admission_id' => $admissionId,
            'booking_id' => (string) $admission->booking_id,
            'member_schedule_id' => (string) $admission->member_schedule_id,
            'operator_site_id' => $operatorSiteId,
            'operator_profile_id' => $profileId,
            'fixture_pair_id' => self::FIXTURE_PAIR,
            'radiograph_checksums' => [hash('sha256', $radiographBytes)],
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
                    $admission = $this->admission($profileId, $siteId, $operatorSiteId, $admissionId, true);
                    if (DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId)->exists()) {
                        throw new ImageGatewayException('capture_conflict', 'This X-ray admission already has a capture set.');
                    }

                    $radiographObject = $this->objects->put($radiographBytes, $captureContext, self::CAPTURE_PURPOSE);
                    $stored[] = $radiographObject;
                    $gainObject = $this->objects->put($gainBytes, $captureContext, self::CAPTURE_PURPOSE);
                    $stored[] = $gainObject;

                    $dicomBytes = $this->fixture(self::DICOM_NAME);
                    if (! str_starts_with($dicomBytes, str_repeat("\0", 128).'DICM')) {
                        throw new ImageGatewayException('capture_failure', 'The synthetic DICOM fixture is invalid.');
                    }
                    $dicomObject = $this->objects->put($dicomBytes, $captureContext, self::CAPTURE_PURPOSE);
                    $stored[] = $dicomObject;

                    $now = $this->clock->now();
                    $captureId = (string) Str::uuid();
                    $studyId = (string) Str::uuid();
                    $radiographChecksum = $radiographObject->checksum;
                    $gainChecksum = $gainObject->checksum;
                    $dicomChecksum = $dicomObject->checksum;

                    DB::table('image_gateway_capture_sets')->insert([
                        'id' => $captureId,
                        'submission_id' => $submissionId,
                        'admission_id' => $admissionId,
                        'booking_id' => (string) $admission->booking_id,
                        'member_schedule_id' => (string) $admission->member_schedule_id,
                        'operator_site_id' => $siteId,
                        'operator_profile_id' => $profileId,
                        'fixture_pair_id' => self::FIXTURE_PAIR,
                        'radiograph_count' => 1,
                        'status' => 'accepted',
                        'accepted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('image_gateway_capture_objects')->insert([
                        [
                            'id' => (string) Str::uuid(),
                            'capture_set_id' => $captureId,
                            'object_type' => 'radiograph',
                            'object_index' => 0,
                            'object_key' => (string) $radiographObject->key,
                            'checksum' => $radiographChecksum,
                            'bytes' => $radiographObject->bytes,
                            'format' => self::FIXTURE_FORMAT,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'capture_set_id' => $captureId,
                            'object_type' => 'gain',
                            'object_index' => 0,
                            'object_key' => (string) $gainObject->key,
                            'checksum' => $gainChecksum,
                            'bytes' => $gainObject->bytes,
                            'format' => self::FIXTURE_FORMAT,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    ]);
                    DB::table('image_gateway_studies')->insert([
                        'id' => $studyId,
                        'capture_set_id' => $captureId,
                        'object_key' => (string) $dicomObject->key,
                        'checksum' => $dicomChecksum,
                        'bytes' => $dicomObject->bytes,
                        'format' => 'application/dicom',
                        'study_instance_uid' => '1.2.826.0.1.3680043.10.543.2040.1',
                        'series_instance_uid' => '1.2.826.0.1.3680043.10.543.2040.1.1',
                        'sop_instance_uid' => '1.2.826.0.1.3680043.10.543.2040.1.1.1',
                        'transfer_syntax' => '1.2.840.10008.1.2.1',
                        'window_center' => '128',
                        'window_width' => '256',
                        'rows' => 32,
                        'columns' => 32,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
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
                        'study_id' => $studyId,
                        'admission_id' => $admissionId,
                        'booking_id' => (string) $admission->booking_id,
                        'operator_site_id' => $operatorSiteId,
                        'operator_profile_id' => $profileId,
                        'fixture_pair' => self::FIXTURE_PAIR,
                        'asset_checksums' => [$radiographChecksum, $gainChecksum, $dicomChecksum],
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

                    return ['capture_id' => $captureId, 'study_id' => $studyId, 'status' => 'accepted'];
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
            throw new ImageGatewayException('capture_failure', 'The synthetic capture could not be accepted.', $exception);
        }
    }

    /** @return array<string, mixed> */
    public function study(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $studyId,
    ): array {
        $this->assertLocalEnvironment();
        $this->assertContext($context, self::STUDY_PURPOSE);
        $study = $this->authorizedStudy($profileId, $siteId, $operatorSiteId, $studyId);

        return [
            'study_id' => (string) $study->id,
            'capture_id' => (string) $study->capture_set_id,
            'admission_id' => (string) $study->admission_id,
            'format' => (string) $study->format,
            'checksum' => (string) $study->checksum,
            'bytes' => (int) $study->bytes,
            'window_center' => (string) $study->window_center,
            'window_width' => (string) $study->window_width,
            'rows' => (int) $study->rows,
            'columns' => (int) $study->columns,
        ];
    }

    public function dicom(
        AuthenticatedContext $context,
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $studyId,
    ): string {
        $this->assertLocalEnvironment();
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

    private function assertLocalEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new ImageGatewayException('environment_forbidden', 'The synthetic DICOM bridge is available only in local or testing environments.');
        }
    }

    private function assertContext(AuthenticatedContext $context, string $purpose): void
    {
        if ($context->actorId === null || $context->operationId === null || $context->purpose !== $purpose) {
            throw new ImageGatewayException('capture_forbidden', 'A trusted Operator context is required.');
        }
    }

    private function assertFixturePair(array $radiographs, UploadedFile $gain): array
    {
        if (count($radiographs) !== 1 || ! $radiographs[0] instanceof UploadedFile) {
            throw new ImageGatewayException('capture_invalid', 'Exactly one synthetic radiograph is required.');
        }
        $radiograph = $radiographs[0];
        $radiographBytes = $this->assertFixtureFile($radiograph, self::RADIOGRAPH_NAME);
        $gainBytes = $this->assertFixtureFile($gain, self::GAIN_NAME);
        if (! str_starts_with($radiographBytes, "PK\x03\x04") || ! str_starts_with($gainBytes, "PK\x03\x04")) {
            throw new ImageGatewayException('capture_invalid', 'Synthetic inputs must be ZIP N'.'PZ files.');
        }
        $this->imagePolicy()->assertWithin(new UntrustedImageInput(
            fileCount: 2,
            perFileBytes: max(strlen($radiographBytes), strlen($gainBytes)),
            totalBytes: strlen($radiographBytes) + strlen($gainBytes),
            decompressedBytes: 8,
            width: 2,
            height: 2,
            fieldCount: 2,
            cpuSeconds: 0,
            memoryBytes: 0,
            executionSeconds: 0,
            processCount: 0,
            temporaryStorageBytes: 0,
            form: self::FIXTURE_FORM,
            recoveryWindowSeconds: 0,
            attempts: 0,
        ));

        return [$radiograph, $radiographBytes, $gainBytes];
    }

    private function imagePolicy(): UntrustedImagePolicy
    {
        $config = (array) config('mhcs.image_policy');
        if (app()->environment(['local', 'testing'])) {
            $config = array_replace([
                'file_count' => 2,
                'per_file_bytes' => 1048576,
                'total_bytes' => 2097152,
                'decompressed_bytes' => 4194304,
                'max_width' => 4096,
                'max_height' => 4096,
                'field_count' => 32,
                'cpu_seconds' => 5,
                'memory_bytes' => 134217728,
                'execution_seconds' => 30,
                'process_count' => 1,
                'temporary_storage_bytes' => 8388608,
                'accepted_forms' => [self::FIXTURE_FORM],
                'recovery_window_seconds' => 300,
                'max_attempts' => 1,
            ], array_filter($config, static fn (mixed $value): bool => $value !== null));
        }

        return UntrustedImagePolicy::fromConfig($config);
    }

    private function assertFixtureFile(UploadedFile $file, string $expectedName): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK || $file->getClientOriginalName() !== $expectedName) {
            throw new ImageGatewayException('capture_invalid', 'The synthetic fixture identity is not accepted.');
        }
        $contents = $file->get();
        if (! is_string($contents) || ! hash_equals(hash('sha256', $this->fixture($expectedName)), hash('sha256', $contents))) {
            throw new ImageGatewayException('capture_invalid', 'The synthetic fixture bytes are not accepted.');
        }

        return $contents;
    }

    private function fixture(string $name): string
    {
        $contents = @file_get_contents(base_path('resources/fixtures/image-gateway/'.$name));
        if (! is_string($contents)) {
            throw new ImageGatewayException('capture_failure', 'The repository-owned synthetic fixture is unavailable.');
        }

        return $contents;
    }

    private function admission(
        string $profileId,
        string $siteId,
        string $operatorSiteId,
        string $admissionId,
        bool $lock,
        bool $allowAccepted = false,
    ): object
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

    private function authorizedStudy(string $profileId, string $siteId, string $operatorSiteId, string $studyId): object
    {
        if (! Str::isUuid($studyId)) {
            throw new ImageGatewayException('study_forbidden', 'The DICOM study is unavailable.');
        }
        $study = DB::table('image_gateway_studies as studies')
            ->join('image_gateway_capture_sets as captures', 'captures.id', '=', 'studies.capture_set_id')
            ->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'captures.admission_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'captures.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('studies.id', $studyId)
            ->where('captures.status', 'accepted')
            ->where('captures.operator_site_id', $siteId)
            ->where('captures.operator_profile_id', $profileId)
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
            })
            ->select('studies.*', 'captures.admission_id')
            ->first();

        if ($study === null) {
            throw new ImageGatewayException('study_forbidden', 'The DICOM study is unavailable to this Operator.');
        }

        return $study;
    }

    /** @param  list<PrivateObject>  $stored */
    private function deleteStored(array $stored): void
    {
        foreach ($stored as $object) {
            $this->objects->delete($object);
        }
    }
}
