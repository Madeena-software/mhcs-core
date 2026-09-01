<?php

declare(strict_types=1);

namespace Tests\Feature\ImageGateway;

use App\Modules\ImageGateway\Application\Contracts\DicomConverter;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Application\Services\ProductionDicomRemediationService;
use App\Modules\ImageGateway\Domain\Security\ConversionManifest;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
use App\Shared\Audit\AuditStore;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class ProductionDicomRemediationT005Test extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.private_object_disk' => 'local',
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.security.manifest_key' => str_repeat('m', 32),
            'mhcs.security.manifest_key_id' => 'test-key',
            'mhcs.mpips.max_attempts' => 5,
        ]);
        Storage::fake('local');
        Queue::fake();
    }

    public function test_exact_t005_transition_preserves_sources_and_completes_once(): void
    {
        $case = $this->seedFailedT005();
        $before = $this->captureObjects($case['capture']->id);
        $service = app(ProductionDicomRemediationService::class);

        $preflight = $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight'));
        $this->assertSame(ProductionDicomRemediationService::T005_ADMISSION_ID, $preflight['target_admission_id']);
        $this->assertSame((string) $case['capture']->id, $preflight['capture_id']);
        $this->assertSame('BED', $preflight['detector_type']);
        $this->assertTrue($preflight['relationship_integrity_verified']);
        $this->assertSame((int) $before['radiograph']->bytes, $preflight['radiograph_bytes']);

        $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
        Queue::assertPushed(ProcessCaptureSet::class, 1);

        $afterCorrection = DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->first();
        $this->assertSame((int) $case['capture']->attempts, (int) $afterCorrection->attempts);
        $this->assertSame('TRX', json_decode((string) $afterCorrection->capture_metadata, true, 512, JSON_THROW_ON_ERROR)['capture']['detector_type']);
        $afterObjects = $this->captureObjects($case['capture']->id);
        foreach (['radiograph', 'gain'] as $type) {
            $this->assertSame($before[$type]->object_key, $afterObjects[$type]->object_key);
            $this->assertSame($before[$type]->checksum, $afterObjects[$type]->checksum);
            $this->assertSame($before[$type]->bytes, $afterObjects[$type]->bytes);
            $this->assertTrue(Storage::disk('local')->exists((string) $before[$type]->object_key));
        }
        $this->assertNotSame($before['manifest']->object_key, $afterObjects['manifest']->object_key);
        $this->assertNotSame($before['manifest_signature']->object_key, $afterObjects['manifest_signature']->object_key);
        $this->assertTrue(Storage::disk('local')->exists((string) $before['manifest']->object_key));
        $this->assertTrue(Storage::disk('local')->exists((string) $before['manifest_signature']->object_key));

        $manifest = json_decode(Storage::disk('local')->get((string) $afterObjects['manifest']->object_key), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('TRX', $manifest['capture']['detector_type']);
        app('App\Modules\ImageGateway\Domain\Security\ManifestSigner')->verify(
            SignedManifest::fromArray(json_decode(Storage::disk('local')->get((string) $afterObjects['manifest_signature']->object_key), true, 512, JSON_THROW_ON_ERROR)),
        );
        $audit = DB::table('audit_events')->where('action', 't005_detector_corrected')->where('target_id', $case['capture']->id)->first();
        $auditMetadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($before['manifest']->object_key, implode('', $auditMetadata['previous_manifest_object_key']));
        $this->assertSame($before['manifest_signature']->object_key, implode('', $auditMetadata['previous_signature_object_key']));
        $this->assertSame($afterObjects['manifest']->object_key, implode('', $auditMetadata['replacement_manifest_object_key']));
        $this->assertSame($afterObjects['manifest_signature']->checksum, implode('', $auditMetadata['replacement_signature_checksum']));

        Queue::fake();
        Http::fake(['*' => Http::response(str_repeat("\0", 128).'DICM'.'t005-dicom', 200, [
            'Content-Type' => 'application/dicom',
            'X-Conversion-Job-ID' => $case['capture']->id,
            'X-Correlation-ID' => (string) Str::uuid(),
        ])]);
        app()->call([new ProcessCaptureSet((string) $case['capture']->id), 'handle']);
        Queue::assertNothingPushed();

        $verification = $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'verify'));
        $this->assertSame('complete', $verification['verification_status']);
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $case['capture']->id)->count());

        Queue::fake();
        $rerunPreflight = $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight'));
        $this->assertSame('already_completed', $rerunPreflight['processing']);
        $this->assertSame('already_completed', $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'))['processing']);
        $this->assertSame('complete', $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'verify'))['verification_status']);
        Queue::assertNothingPushed();
    }

    #[DataProvider('relationshipMismatches')]
    public function test_relationship_mismatch_fails_before_t005_mutation(string $kind): void
    {
        $case = $this->seedFailedT005();
        $other = $this->operatorFixture(false, '900000000002');
        $changes = [
            'booking' => ['image_gateway_capture_sets', ['booking_id' => $other['bookingId']]],
            'schedule' => ['image_gateway_capture_sets', ['member_schedule_id' => $other['scheduleId']]],
            'site' => ['image_gateway_capture_sets', ['operator_site_id' => $other['siteLocalId']]],
            'ticket_operator' => ['operator_paper_tickets', ['operator_profile_id' => $other['profileId']]],
            'admission_operator' => ['operator_queue_admissions', ['operator_profile_id' => $other['profileId']]],
            'admission_site' => ['operator_queue_admissions', ['operator_site_id' => $other['siteLocalId']]],
            'admission_schedule' => ['operator_queue_admissions', ['member_schedule_id' => $other['scheduleId']]],
            'member' => ['bookings', ['member_id' => $other['memberId']]],
        ];
        if ($kind === 'admission_capture') {
            $otherTicketId = (string) Str::uuid();
            DB::table('operator_paper_tickets')->insert([
                'id' => $otherTicketId,
                'booking_id' => $other['bookingId'],
                'member_schedule_id' => $other['scheduleId'],
                'operator_site_id' => $other['siteLocalId'],
                'operator_profile_id' => $other['profileId'],
                'ticket_number' => 'T-005-OTHER',
                'issued_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('operator_queue_admissions')->where('id', ProductionDicomRemediationService::T005_ADMISSION_ID)->update(['operator_paper_ticket_id' => $otherTicketId]);
        } else {
            [$table, $values] = $changes[$kind];
            $query = DB::table($table);
            if ($table === 'image_gateway_capture_sets') {
                $query->where('id', $case['capture']->id);
            } elseif ($table === 'operator_paper_tickets') {
                $query->where('booking_id', $case['fixture']['bookingId']);
            } elseif ($table === 'bookings') {
                $query->where('id', $case['fixture']['bookingId']);
            } else {
                $query->where('id', ProductionDicomRemediationService::T005_ADMISSION_ID);
            }
            $query->update($values);
        }

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
            $this->fail('The relationship mismatch must be rejected.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame('failed', DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->value('processing_status'));
    }

    public static function relationshipMismatches(): array
    {
        return [
            'booking' => ['booking'],
            'schedule' => ['schedule'],
            'site' => ['site'],
            'ticket operator' => ['ticket_operator'],
            'admission operator' => ['admission_operator'],
            'admission site' => ['admission_site'],
            'admission schedule' => ['admission_schedule'],
            'admission/capture' => ['admission_capture'],
            'member' => ['member'],
        ];
    }

    #[DataProvider('eligibilityMismatches')]
    public function test_ineligible_t005_state_fails_closed(string $kind): void
    {
        $case = $this->seedFailedT005();
        if ($kind === 'existing study') {
            $this->insertBlockingStudy($case['capture']->id);
        }
        $updates = match ($kind) {
            'detector' => ['capture_metadata' => json_encode(['examination' => ['study_description' => 'CHEST RADIOGRAPH'], 'capture' => ['detector_type' => 'TRX', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA']], JSON_THROW_ON_ERROR)],
            'processing' => ['processing_status' => 'pending'],
            'attempts' => ['attempts' => 5],
            'claim' => ['processing_claim_id' => (string) Str::uuid()],
            'lease' => ['processing_lease_expires_at' => now()->addMinute()],
            'existing study' => [],
        };
        if ($updates !== []) {
            DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->update($updates);
        }
        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight'));
            $this->fail('The ineligible T-005 state must be rejected.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
    }

    public static function eligibilityMismatches(): array
    {
        return ['detector' => ['detector'], 'processing' => ['processing'], 'attempts' => ['attempts'], 'claim' => ['claim'], 'lease' => ['lease'], 'existing study' => ['existing study']];
    }

    #[DataProvider('integrityMismatches')]
    public function test_t005_integrity_and_identity_failures_are_fail_closed(string $kind): void
    {
        $case = $this->seedFailedT005();
        $before = $this->captureObjects($case['capture']->id);
        $this->mutateIntegrityEvidence($case, $kind);
        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight'));
            $this->fail('Corrupt or mismatched evidence must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $after = $this->captureObjects($case['capture']->id);
        $this->assertSame($before['manifest']->object_key, $after['manifest']->object_key);
        $this->assertSame($before['manifest_signature']->object_key, $after['manifest_signature']->object_key);
        $this->assertSame('failed', DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->value('processing_status'));
        Queue::assertNothingPushed();
    }

    public static function integrityMismatches(): array
    {
        return [
            'radiograph checksum' => ['radiograph_checksum'],
            'radiograph bytes' => ['radiograph_bytes'],
            'gain checksum' => ['gain_checksum'],
            'gain bytes' => ['gain_bytes'],
            'manifest corruption' => ['manifest_corruption'],
            'manifest checksum' => ['manifest_checksum'],
            'signature corruption' => ['signature_corruption'],
            'conversion identity' => ['conversion_identity'],
            'source identity' => ['source_identity'],
            'manifest relationship identity' => ['manifest_relationship_identity'],
        ];
    }

    public function test_audit_failure_rolls_back_t005_pointers_and_prevents_dispatch(): void
    {
        $case = $this->seedFailedT005();
        $before = $this->captureObjects($case['capture']->id);
        $audit = Mockery::mock(AuditStore::class);
        $audit->shouldReceive('append')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $this->app->instance(AuditStore::class, $audit);
        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
            $this->fail('The audit failure must abort T-005 mutation.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $after = $this->captureObjects($case['capture']->id);
        $this->assertSame($before['manifest']->object_key, $after['manifest']->object_key);
        $this->assertSame($before['manifest_signature']->object_key, $after['manifest_signature']->object_key);
        $this->assertSame('failed', DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->value('processing_status'));
        Queue::assertNothingPushed();
    }

    public function test_pending_verification_and_terminal_failure_are_distinguished(): void
    {
        $case = $this->seedFailedT005();
        $service = app(ProductionDicomRemediationService::class);
        $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
        $this->assertSame('pending', $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'verify'))['verification_status']);
        DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->update(['processing_status' => 'failed', 'm'.'pips_status' => 'failed']);
        $this->expectException(\RuntimeException::class);
        $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'verify'));
    }

    public function test_inconsistent_completed_state_fails_closed_instead_of_being_accepted(): void
    {
        $case = $this->seedFailedT005();
        app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
        $this->completeT005($case);
        DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->update(['manifest_checksum' => str_repeat('f', 64)]);

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight'));
            $this->fail('An inconsistent completed state must fail closed.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
        Queue::assertNothingPushed();
    }

    public function test_unrelated_records_remain_unchanged_after_t005_completion(): void
    {
        $unrelated = $this->operatorFixture(false, '900000000003');
        $before = DB::table('bookings')->where('id', $unrelated['bookingId'])->first();
        $case = $this->seedFailedT005();
        $service = app(ProductionDicomRemediationService::class);
        $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
        $this->completeT005($case);

        $this->assertEquals((array) $before, (array) DB::table('bookings')->where('id', $unrelated['bookingId'])->first());
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $case['capture']->id)->count());
    }

    public function test_dcm_regeneration_preserves_logical_study_and_activates_valid_candidate(): void
    {
        $unrelated = $this->operatorFixture(false, '900000000004');
        $unrelatedBooking = DB::table('bookings')->where('id', $unrelated['bookingId'])->first();
        $case = $this->seedCompletedDcm();
        $beforeStudy = $case['study'];
        $beforeObjects = $this->captureObjects($case['capture']->id);
        $beforeFiles = Storage::disk('local')->allFiles();
        $relationships = $this->relationshipSnapshot($case['capture']->id);
        $this->fakeDcmResponse((string) $case['capture']->id, 'dcm-replacement');
        $service = app(ProductionDicomRemediationService::class);

        $preflight = $service->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'preflight'));
        $this->assertSame(ProductionDicomRemediationService::DCM_STUDY_ID, $preflight['study_id']);
        $this->assertSame(ProductionDicomRemediationService::DCM_REFERENCE, $preflight['display_reference']);
        $this->assertTrue($preflight['relationship_integrity_verified']);
        $this->assertSame((int) $beforeObjects['radiograph']->bytes, $preflight['radiograph_bytes']);

        $result = $service->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'execute'));
        $afterStudy = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->assertSame(ProductionDicomRemediationService::DCM_STUDY_ID, $result['study_id']);
        $this->assertSame($beforeStudy->capture_set_id, $afterStudy->capture_set_id);
        $this->assertSame($beforeStudy->display_reference, $afterStudy->display_reference);
        $this->assertSame($beforeStudy->study_instance_uid, $afterStudy->study_instance_uid);
        $this->assertSame($beforeStudy->series_instance_uid, $afterStudy->series_instance_uid);
        $this->assertSame($beforeStudy->sop_instance_uid, $afterStudy->sop_instance_uid);
        $this->assertNotSame($beforeStudy->object_key, $afterStudy->object_key);
        $this->assertNotSame($beforeStudy->checksum, $afterStudy->checksum);
        $this->assertSame(strlen('dcm-replacement') + 132, (int) $afterStudy->bytes);
        $this->assertTrue(Storage::disk('local')->exists((string) $beforeStudy->object_key));
        $this->assertTrue(Storage::disk('local')->exists((string) $afterStudy->object_key));

        $afterObjects = $this->captureObjects($case['capture']->id);
        foreach (['radiograph', 'gain', 'manifest', 'manifest_signature'] as $type) {
            $this->assertSame($beforeObjects[$type]->object_key, $afterObjects[$type]->object_key);
            $this->assertSame($beforeObjects[$type]->checksum, $afterObjects[$type]->checksum);
            $this->assertSame($beforeObjects[$type]->bytes, $afterObjects[$type]->bytes);
        }
        $this->assertSame($relationships, $this->relationshipSnapshot($case['capture']->id));
        $this->assertEquals((array) $unrelatedBooking, (array) DB::table('bookings')->where('id', $unrelated['bookingId'])->first());

        $audit = DB::table('audit_events')->where('action', 'dcm_zshnsx90_replaced')->where('target_id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $auditMetadata = json_decode((string) $audit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($beforeStudy->object_key, implode('', $auditMetadata['old_object_key']));
        $this->assertSame($beforeStudy->checksum, implode('', $auditMetadata['old_object_checksum']));
        $this->assertSame($afterStudy->object_key, implode('', $auditMetadata['replacement_object_key']));
        $this->assertSame($afterStudy->checksum, implode('', $auditMetadata['new_object_checksum']));
        $this->assertSame((int) $afterStudy->bytes, (int) $auditMetadata['new_object_bytes']);
        $afterFiles = Storage::disk('local')->allFiles();
        $this->assertCount(count($beforeFiles) + 2, $afterFiles);
        $this->assertContains((string) $afterStudy->object_key, $afterFiles);
        $this->assertContains((string) $afterStudy->object_key.'.meta.json', $afterFiles);
        $this->assertSame('complete', $service->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'verify'))['verification_status']);
    }

    #[DataProvider('dcmRelationshipStates')]
    public function test_dcm_relationship_and_state_mismatches_fail_closed(string $kind): void
    {
        $case = $this->seedCompletedDcm();
        $other = $this->operatorFixture(false, '900000000005');
        $this->mutateDcmRelationshipState($case, $other, $kind);
        $before = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'preflight'));
            $this->fail('The DCM relationship/state mismatch must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $after = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->assertEquals((array) $before, (array) $after);
        $this->assertDatabaseMissing('audit_events', ['action' => 'dcm_zshnsx90_replaced', 'target_id' => ProductionDicomRemediationService::DCM_STUDY_ID]);
    }

    public static function dcmRelationshipStates(): array
    {
        return [
            'study reference' => ['study_reference'],
            'admission/capture' => ['admission_capture'],
            'booking' => ['booking'],
            'schedule' => ['schedule'],
            'site' => ['site'],
            'ticket operator' => ['ticket_operator'],
            'admission operator' => ['admission_operator'],
            'detector' => ['detector'],
            'not accepted' => ['not_accepted'],
            'not completed' => ['not_completed'],
            'claim' => ['claim'],
            'lease' => ['lease'],
        ];
    }

    #[DataProvider('dcmSourceFailures')]
    public function test_dcm_source_and_security_failures_leave_active_study_untouched(string $kind): void
    {
        $case = $this->seedCompletedDcm();
        $before = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->mutateDcmSourceEvidence($case, $kind);

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'preflight'));
            $this->fail('The DCM source/security mismatch must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $after = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->assertEquals((array) $before, (array) $after);
        $this->assertTrue(Storage::disk('local')->exists((string) $before->object_key));
    }

    public static function dcmSourceFailures(): array
    {
        return [
            'radiograph corruption' => ['radiograph_corruption'],
            'gain corruption' => ['gain_corruption'],
            'manifest corruption' => ['manifest_corruption'],
            'invalid signature' => ['invalid_signature'],
            'source checksum mismatch' => ['source_checksum'],
            'conversion identity mismatch' => ['conversion_identity'],
        ];
    }

    #[DataProvider('dcmCandidateFailures')]
    public function test_dcm_candidate_validation_rejects_without_deactivating_old_object(string $kind): void
    {
        $case = $this->seedCompletedDcm();
        if (in_array($kind, ['study_uid', 'series_uid', 'sop_uid'], true)) {
            $column = ['study_uid' => 'study_instance_uid', 'series_uid' => 'series_instance_uid', 'sop_uid' => 'sop_instance_uid'][$kind];
            DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->update([$column => '2.25.'.random_int(100000, 999999)]);
        }
        $before = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->fakeDcmResponse((string) $case['capture']->id, $kind === 'malformed' ? 'not-dicom' : 'candidate-dicom', match ($kind) {
            'non_success' => 500,
            default => 200,
        }, $kind === 'missing_job' ? false : ($kind === 'wrong_job' ? (string) Str::uuid() : null), $kind === 'invalid_correlation' ? 'not-a-uuid' : (string) Str::uuid());

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'execute'));
            $this->fail('The invalid DCM candidate must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $after = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->assertEquals((array) $before, (array) $after);
        $this->assertTrue(Storage::disk('local')->exists((string) $before->object_key));
        $this->assertDatabaseMissing('audit_events', ['action' => 'dcm_zshnsx90_replaced', 'target_id' => ProductionDicomRemediationService::DCM_STUDY_ID]);
    }

    public static function dcmCandidateFailures(): array
    {
        return [
            'MPIPS non-success' => ['non_success'],
            'malformed DICOM' => ['malformed'],
            'missing conversion job ID' => ['missing_job'],
            'wrong conversion job ID' => ['wrong_job'],
            'invalid correlation ID' => ['invalid_correlation'],
            'Study UID mismatch' => ['study_uid'],
            'Series UID mismatch' => ['series_uid'],
            'SOP UID mismatch' => ['sop_uid'],
        ];
    }

    public function test_dcm_transaction_and_audit_failures_preserve_history_and_delete_only_candidate(): void
    {
        $case = $this->seedCompletedDcm();
        $before = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $beforeFiles = Storage::disk('local')->allFiles();
        $audit = Mockery::mock(AuditStore::class);
        $audit->shouldReceive('append')->once()->andThrow(new \RuntimeException('audit unavailable'));
        $this->app->instance(AuditStore::class, $audit);
        $this->fakeDcmResponse((string) $case['capture']->id, 'candidate-dicom');

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'execute'));
            $this->fail('The audit failure must abort DCM activation.');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $after = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->assertEquals((array) $before, (array) $after);
        $this->assertTrue(Storage::disk('local')->exists((string) $before->object_key));
        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('audit_events', ['action' => 'dcm_zshnsx90_replaced', 'target_id' => ProductionDicomRemediationService::DCM_STUDY_ID]);
    }

    #[DataProvider('t005RaceDrifts')]
    public function test_t005_locked_transaction_rejects_race_drift_without_dispatch(string $kind): void
    {
        $case = $this->seedFailedT005();
        $before = $this->captureObjects($case['capture']->id);
        $beforeFiles = Storage::disk('local')->allFiles();
        $other = $kind === 'admission_operator' ? $this->operatorFixture(false, '900000000006') : null;
        $this->installPutDrift(function () use ($case, $kind, $other): void {
            match ($kind) {
                'admission_operator' => DB::table('operator_queue_admissions')->where('id', ProductionDicomRemediationService::T005_ADMISSION_ID)->update(['operator_profile_id' => $other['profileId']]),
                'object_snapshot' => DB::table('image_gateway_capture_objects')->where('capture_set_id', $case['capture']->id)->where('object_type', 'manifest')->update(['checksum' => str_repeat('a', 64)]),
                'capture_state' => DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->update(['processing_status' => 'pending']),
                'attempts' => DB::table('image_gateway_capture_sets')->where('id', $case['capture']->id)->update(['attempts' => (int) $case['capture']->attempts + 1]),
                default => throw new \InvalidArgumentException('Unknown T-005 race case.'),
            };
        });

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
            $this->fail('The locked T-005 race must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $after = $this->captureObjects($case['capture']->id);
        $this->assertSame($before['manifest']->object_key, $after['manifest']->object_key);
        $this->assertSame($before['manifest_signature']->object_key, $after['manifest_signature']->object_key);
        $this->assertSame(0, DB::table('image_gateway_studies')->where('capture_set_id', $case['capture']->id)->count());
        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public static function t005RaceDrifts(): array
    {
        return [
            'admission operator drift' => ['admission_operator'],
            'object snapshot drift' => ['object_snapshot'],
            'capture state drift' => ['capture_state'],
            'attempt drift' => ['attempts'],
        ];
    }

    #[DataProvider('dcmRaceDrifts')]
    public function test_dcm_locked_activation_rejects_race_drift_and_cleans_candidate(string $kind): void
    {
        $case = $this->seedCompletedDcm();
        $before = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $beforeFiles = Storage::disk('local')->allFiles();
        $this->fakeDcmResponse((string) $case['capture']->id, 'candidate-dicom');
        $this->installPutDrift(function () use ($case, $kind): void {
            match ($kind) {
                'study_object' => DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->update(['checksum' => str_repeat('a', 64)]),
                'study_uid' => DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->update(['study_instance_uid' => '2.25.'.random_int(100000, 999999)]),
                'series_uid' => DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->update(['series_instance_uid' => '2.25.'.random_int(100000, 999999)]),
                'sop_uid' => DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->update(['sop_instance_uid' => '2.25.'.random_int(100000, 999999)]),
                'source_snapshot' => DB::table('image_gateway_capture_objects')->where('capture_set_id', $case['capture']->id)->where('object_type', 'gain')->update(['checksum' => str_repeat('a', 64)]),
                default => throw new \InvalidArgumentException('Unknown DCM race case.'),
            };
        });

        try {
            app(ProductionDicomRemediationService::class)->run(...$this->runtime(ProductionDicomRemediationService::DCM_ZSHNSX90_REGENERATE, 'execute'));
            $this->fail('The locked DCM race must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $after = DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail();
        $this->assertSame($before->object_key, $after->object_key);
        $this->assertSame($before->bytes, $after->bytes);
        $this->assertSame($before->display_reference, $after->display_reference);
        $this->assertSame($beforeFiles, Storage::disk('local')->allFiles());
        $this->assertDatabaseMissing('audit_events', ['action' => 'dcm_zshnsx90_replaced', 'target_id' => ProductionDicomRemediationService::DCM_STUDY_ID]);
    }

    public static function dcmRaceDrifts(): array
    {
        return [
            'study object drift' => ['study_object'],
            'Study UID drift' => ['study_uid'],
            'Series UID drift' => ['series_uid'],
            'SOP UID drift' => ['sop_uid'],
            'source snapshot drift' => ['source_snapshot'],
        ];
    }

    private function installPutDrift(callable $drift): void
    {
        $real = app(PrivateObjectStore::class);
        $store = Mockery::mock(PrivateObjectStore::class);
        $putCount = 0;
        $store->shouldReceive('put')->andReturnUsing(function (string $contents, $context, string $purpose) use ($real, $drift, &$putCount): object {
            $object = $real->put($contents, $context, $purpose);
            $putCount++;
            if ($putCount === 1) {
                $drift();
            }

            return $object;
        });
        $store->shouldReceive('grant')->andReturnUsing(fn ($object, $context, $audience, $purpose, $expiresAt) => $real->grant($object, $context, $audience, $purpose, $expiresAt));
        $store->shouldReceive('get')->andReturnUsing(fn ($grant, $context, $audience, $purpose) => $real->get($grant, $context, $audience, $purpose));
        $store->shouldReceive('delete')->andReturnUsing(fn ($object): mixed => $real->delete($object));
        $this->app->instance(PrivateObjectStore::class, $store);
    }

    /** @return array{fixture: array<string, mixed>, capture: object} */
    private function seedFailedT005(): array
    {
        $fixture = $this->operatorFixture(false);
        $now = now();
        $ticketId = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $fixture['bookingId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'T-005',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => ProductionDicomRemediationService::T005_ADMISSION_ID,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'queue_class' => 'advance',
            'stage' => 'xray',
            'state' => 'called',
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->post(route('operator.xray-capture.store', ProductionDicomRemediationService::T005_ADMISSION_ID), [
            'submission_id' => (string) Str::uuid(),
            'metadata' => ['examination' => ['study_description' => 'CHEST RADIOGRAPH'], 'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA']],
            'radiograph_npz' => new UploadedFile(base_path('resources/fixtures/image-gateway/synthetic-radiograph-01.npz'), 'radiograph.npz', null, null, true),
            'gain_npz' => new UploadedFile(base_path('resources/fixtures/image-gateway/synthetic-gain-01.npz'), 'gain.npz', null, null, true),
        ])->assertRedirect();
        $capture = DB::table('image_gateway_capture_sets')->where('admission_id', ProductionDicomRemediationService::T005_ADMISSION_ID)->first();
        DB::table('image_gateway_capture_sets')->where('id', $capture->id)->update([
            'processing_status' => 'failed',
            'm'.'pips_status' => 'failed',
            'dicom_status' => 'failed',
            'failed_at' => $now,
            'last_error_code' => 'test_failure',
        ]);
        Queue::fake();

        return ['fixture' => $fixture, 'capture' => DB::table('image_gateway_capture_sets')->where('id', $capture->id)->first()];
    }

    private function completeT005(array $case): object
    {
        Queue::fake();
        Http::fake(['*' => Http::response(str_repeat("\0", 128).'DICM'.'t005-dicom', 200, [
            'Content-Type' => 'application/dicom',
            'X-Conversion-Job-ID' => $case['capture']->id,
            'X-Correlation-ID' => (string) Str::uuid(),
        ])]);
        app()->call([new ProcessCaptureSet((string) $case['capture']->id), 'handle']);

        return DB::table('image_gateway_studies')->where('capture_set_id', $case['capture']->id)->firstOrFail();
    }

    /** @return array{fixture: array<string, mixed>, capture: object, study: object} */
    private function seedCompletedDcm(): array
    {
        $case = $this->seedFailedT005();
        $service = app(ProductionDicomRemediationService::class);
        $service->run(...$this->runtime(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'execute'));
        $study = $this->completeT005($case);
        DB::table('image_gateway_studies')->where('id', $study->id)->update([
            'id' => ProductionDicomRemediationService::DCM_STUDY_ID,
            'display_reference' => ProductionDicomRemediationService::DCM_REFERENCE,
            'filename' => ProductionDicomRemediationService::DCM_REFERENCE.'.dcm',
        ]);

        return ['fixture' => $case['fixture'], 'capture' => $case['capture'], 'study' => DB::table('image_gateway_studies')->where('id', ProductionDicomRemediationService::DCM_STUDY_ID)->firstOrFail()];
    }

    private function fakeDcmResponse(string $jobId, string $suffix, int $status = 200, string|false|null $responseJobId = null, ?string $correlationId = null): void
    {
        $body = $suffix === 'not-dicom' ? $suffix : str_repeat("\0", 128).'DICM'.$suffix;
        $headers = ['Content-Type' => 'application/dicom', 'X-Correlation-ID' => $correlationId ?? (string) Str::uuid()];
        if ($responseJobId !== false) {
            $headers['X-Conversion-Job-ID'] = $responseJobId ?? $jobId;
        }
        $converter = Mockery::mock(DicomConverter::class);
        $converter->shouldReceive('convert')->once()->andReturn(new Response(Http::psr7Response($body, $status, $headers)));
        $this->app->instance(DicomConverter::class, $converter);
    }

    /** @return array<string, array<string, mixed>> */
    private function relationshipSnapshot(string $captureId): array
    {
        $capture = DB::table('image_gateway_capture_sets')->where('id', $captureId)->firstOrFail();
        $admission = DB::table('operator_queue_admissions')->where('id', $capture->admission_id)->firstOrFail();
        $ticket = DB::table('operator_paper_tickets')->where('id', $admission->operator_paper_ticket_id)->firstOrFail();

        return [
            'capture' => (array) $capture,
            'admission' => (array) $admission,
            'ticket' => (array) $ticket,
            'booking' => (array) DB::table('bookings')->where('id', $capture->booking_id)->firstOrFail(),
            'schedule' => (array) DB::table('shift_schedules')->where('id', $capture->member_schedule_id)->firstOrFail(),
            'site' => (array) DB::table('operator_sites')->where('id', $capture->operator_site_id)->firstOrFail(),
            'operator' => (array) DB::table('operator_profiles')->where('id', $capture->operator_profile_id)->firstOrFail(),
        ];
    }

    /** @param array<string, mixed> $other */
    private function insertOtherTicket(array $other): string
    {
        $id = (string) Str::uuid();
        $now = now();
        DB::table('operator_paper_tickets')->insert([
            'id' => $id,
            'booking_id' => $other['bookingId'],
            'member_schedule_id' => $other['scheduleId'],
            'operator_site_id' => $other['siteLocalId'],
            'operator_profile_id' => $other['profileId'],
            'ticket_number' => 'OTHER-'.Str::upper(Str::random(8)),
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /** @param array{capture: object, study: object} $case @param array<string, mixed> $other */
    private function mutateDcmRelationshipState(array $case, array $other, string $kind): void
    {
        $captureId = (string) $case['capture']->id;
        $studyId = ProductionDicomRemediationService::DCM_STUDY_ID;
        match ($kind) {
            'study_reference' => DB::table('image_gateway_studies')->where('id', $studyId)->update(['display_reference' => 'DCM-OTHER01']),
            'admission_capture' => DB::table('operator_queue_admissions')->where('id', ProductionDicomRemediationService::T005_ADMISSION_ID)->update(['operator_paper_ticket_id' => $this->insertOtherTicket($other)]),
            'booking' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['booking_id' => $other['bookingId']]),
            'schedule' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['member_schedule_id' => $other['scheduleId']]),
            'site' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['operator_site_id' => $other['siteLocalId']]),
            'ticket_operator' => DB::table('operator_paper_tickets')->where('booking_id', $case['fixture']['bookingId'])->update(['operator_profile_id' => $other['profileId']]),
            'admission_operator' => DB::table('operator_queue_admissions')->where('id', ProductionDicomRemediationService::T005_ADMISSION_ID)->update(['operator_profile_id' => $other['profileId']]),
            'detector' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['capture_metadata' => json_encode(['examination' => ['study_description' => 'CHEST RADIOGRAPH'], 'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA']], JSON_THROW_ON_ERROR)]),
            'not_accepted' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['status' => 'pending']),
            'not_completed' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['processing_status' => 'pending']),
            'claim' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['processing_claim_id' => (string) Str::uuid()]),
            'lease' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['processing_lease_expires_at' => now()->addMinute()]),
            default => throw new \InvalidArgumentException('Unknown DCM relationship case.'),
        };
    }

    /** @param array{capture: object, study: object} $case */
    private function mutateDcmSourceEvidence(array $case, string $kind): void
    {
        $captureId = (string) $case['capture']->id;
        $objects = $this->captureObjects($captureId);
        match ($kind) {
            'radiograph_corruption' => Storage::disk('local')->put((string) $objects['radiograph']->object_key, 'corrupt-radiograph'),
            'gain_corruption' => Storage::disk('local')->put((string) $objects['gain']->object_key, 'corrupt-gain'),
            'manifest_corruption' => Storage::disk('local')->put((string) $objects['manifest']->object_key, 'corrupt-manifest'),
            'invalid_signature' => $this->invalidateSignature($captureId, $objects['manifest_signature']),
            'source_checksum' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['radiograph_checksum' => str_repeat('a', 64)]),
            'conversion_identity' => $this->rewriteSignedEvidence($case, static function (array &$manifest): void {}, (string) Str::uuid()),
            default => throw new \InvalidArgumentException('Unknown DCM source case.'),
        };
    }

    private function invalidateSignature(string $captureId, object $row): void
    {
        $signed = json_decode(Storage::disk('local')->get((string) $row->object_key), true, 512, JSON_THROW_ON_ERROR);
        $signed['signature'] = str_repeat('0', strlen((string) $signed['signature']));
        $this->replaceObjectContents($captureId, $row, json_encode($signed, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function insertBlockingStudy(string $captureId): void
    {
        $now = now();
        DB::table('image_gateway_studies')->insert([
            'id' => (string) Str::uuid(),
            'capture_set_id' => $captureId,
            'display_reference' => 'DCM-'.Str::upper(Str::random(8)),
            'object_key' => 'unrelated-study-'.Str::uuid(),
            'checksum' => str_repeat('a', 64),
            'bytes' => 1,
            'format' => 'application/dicom',
            'filename' => 'unrelated.dcm',
            'study_instance_uid' => '2.25.1',
            'series_instance_uid' => '2.25.2',
            'sop_instance_uid' => '2.25.3',
            'transfer_syntax' => '1.2.840.10008.1.2',
            'window_center' => '0',
            'window_width' => '1',
            'rows' => 1,
            'columns' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array{fixture: array<string, mixed>, capture: object} $case */
    private function mutateIntegrityEvidence(array $case, string $kind): void
    {
        $captureId = (string) $case['capture']->id;
        $objects = $this->captureObjects($captureId);
        match ($kind) {
            'radiograph_checksum' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['radiograph_checksum' => str_repeat('a', 64)]),
            'radiograph_bytes' => DB::table('image_gateway_capture_objects')->where('id', $objects['radiograph']->id)->update(['bytes' => (int) $objects['radiograph']->bytes + 1]),
            'gain_checksum' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['gain_checksum' => str_repeat('a', 64)]),
            'gain_bytes' => DB::table('image_gateway_capture_objects')->where('id', $objects['gain']->id)->update(['bytes' => (int) $objects['gain']->bytes + 1]),
            'manifest_corruption' => $this->replaceObjectContents($captureId, $objects['manifest'], '{not-json'),
            'manifest_checksum' => DB::table('image_gateway_capture_sets')->where('id', $captureId)->update(['manifest_checksum' => str_repeat('a', 64)]),
            'signature_corruption' => $this->replaceObjectContents($captureId, $objects['manifest_signature'], '{not-json'),
            'conversion_identity' => $this->rewriteSignedEvidence($case, static function (array &$manifest): void {}, (string) Str::uuid()),
            'source_identity' => $this->rewriteSignedEvidence($case, static function (array &$manifest): void {}, null, str_repeat('b', 64)),
            'manifest_relationship_identity' => $this->rewriteSignedEvidence($case, static function (array &$manifest): void {
                $manifest['patient']['member_id'] = (string) Str::uuid();
            }),
            default => throw new \InvalidArgumentException('Unknown integrity case.'),
        };
    }

    private function replaceObjectContents(string $captureId, object $row, string $contents): void
    {
        Storage::disk('local')->put((string) $row->object_key, $contents);
        $values = ['checksum' => hash('sha256', $contents), 'bytes' => strlen($contents)];
        DB::table('image_gateway_capture_objects')->where('id', $row->id)->update($values);
        DB::table('image_gateway_capture_sets')->where('id', $captureId)->update($row->object_type === 'manifest'
            ? ['manifest_checksum' => $values['checksum'], 'manifest_bytes' => $values['bytes']]
            : ['signature_checksum' => $values['checksum'], 'signature_bytes' => $values['bytes']]);
    }

    /** @param array{capture: object} $case */
    private function rewriteSignedEvidence(array $case, callable $mutate, ?string $conversionJobId = null, ?string $radiographChecksum = null): void
    {
        $captureId = (string) $case['capture']->id;
        $objects = $this->captureObjects($captureId);
        $manifest = json_decode(Storage::disk('local')->get((string) $objects['manifest']->object_key), true, 512, JSON_THROW_ON_ERROR);
        $mutate($manifest);
        $manifestBytes = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->replaceObjectContents($captureId, $objects['manifest'], $manifestBytes);
        $old = SignedManifest::fromArray(json_decode(Storage::disk('local')->get((string) $objects['manifest_signature']->object_key), true, 512, JSON_THROW_ON_ERROR));
        $signed = app(ManifestSigner::class)->sign(new ConversionManifest(
            conversionJobId: $conversionJobId ?? $old->manifest->conversionJobId,
            radiographChecksum: $radiographChecksum ?? $old->manifest->radiographChecksum,
            gainChecksum: $old->manifest->gainChecksum,
            metadataChecksum: hash('sha256', $manifestBytes),
            manifestVersion: $old->manifest->manifestVersion,
            issuedAt: $old->manifest->issuedAt,
            correlationId: $old->manifest->correlationId,
            keyId: $old->manifest->keyId,
        ));
        $this->replaceObjectContents($captureId, $objects['manifest_signature'], json_encode($signed->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function runtime(string $mode, string $stage): array
    {
        return [$mode, $stage, ProductionDicomRemediationService::REQUIRED_RUNTIME_FIX, 'verified-ancestor:'.ProductionDicomRemediationService::REQUIRED_RUNTIME_FIX];
    }

    /** @return array<string, object> */
    private function captureObjects(string $captureId): array
    {
        return DB::table('image_gateway_capture_objects')->where('capture_set_id', $captureId)->get()->keyBy('object_type')->all();
    }
}
