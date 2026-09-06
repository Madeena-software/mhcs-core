<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Jobs\ProcessAiPacsStudy;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\SensitiveDataSanitizer;
use App\Shared\Time\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class ImageGatewayAiDispatchTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    private ImageGatewayAiServiceContract $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = app(ImageGatewayAiServiceContract::class);
        Queue::fake();
    }

    public function test_dispatch_study_idempotently_creates_ai_job_and_dispatches_queue_job(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $result1 = $this->aiService->dispatchStudy($studyId, $context);

        $this->assertIsArray($result1);
        $this->assertArrayHasKey('ai_job_id', $result1);
        $this->assertSame($studyId, $result1['study_id']);
        $this->assertSame(AiJobStatus::QUEUED, $result1['status']);
        $this->assertFalse($result1['can_retry']);
        $this->assertNull($result1['last_error_code']);
        $this->assertSame((string) $context->operationId, $result1['correlation_id']);

        $jobRow = DB::table('image_gateway_ai_jobs')->where('id', $result1['ai_job_id'])->first();
        $this->assertNotNull($jobRow);
        $this->assertSame($studyId, $jobRow->study_id);
        $this->assertSame($fixture['captureSetId'], $jobRow->capture_set_id);
        $this->assertSame($fixture['bookingId'], $jobRow->booking_id);
        $this->assertSame($fixture['memberId'], $jobRow->member_id);
        $this->assertSame($fixture['admissionId'], $jobRow->admission_id);
        $this->assertSame(AiJobStatus::QUEUED, $jobRow->status);
        $this->assertSame(0, (int) $jobRow->attempts);
        $this->assertSame(3, (int) $jobRow->max_attempts);

        Queue::assertPushed(ProcessAiPacsStudy::class, 1);
        Queue::assertPushed(ProcessAiPacsStudy::class, function (ProcessAiPacsStudy $job) use ($result1): bool {
            return $job->aiJobId === $result1['ai_job_id'] && $job->queue === 'image-gateway';
        });

        // Repeated dispatch for the same study must return the exact same AI job identity with no duplicate queued work
        $result2 = $this->aiService->dispatchStudy($studyId, $context);

        $this->assertSame($result1['ai_job_id'], $result2['ai_job_id']);
        $this->assertSame($result1['status'], $result2['status']);
        $this->assertSame(1, DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->count());
        Queue::assertPushed(ProcessAiPacsStudy::class, 1);
    }

    public function test_provenance_is_traceable_and_report_provenance_skeleton_preserves_relationships(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        // Bidirectional traversal: AI Job -> DICOM Study -> Radiography Session -> Examination -> Member
        $aiJob = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertNotNull($aiJob);

        $study = DB::table('image_gateway_studies')->where('id', $aiJob->study_id)->first();
        $this->assertNotNull($study);
        $this->assertSame($fixture['studyId'], $study->id);

        $capture = DB::table('image_gateway_capture_sets')->where('id', $aiJob->capture_set_id)->first();
        $this->assertNotNull($capture);
        $this->assertSame($fixture['captureSetId'], $capture->id);

        $booking = DB::table('bookings')->where('id', $aiJob->booking_id)->first();
        $this->assertNotNull($booking);
        $this->assertSame($fixture['bookingId'], $booking->id);

        $member = DB::table('members')->where('id', $aiJob->member_id)->first();
        $this->assertNotNull($member);
        $this->assertSame($fixture['memberId'], $member->id);

        // Verify report provenance skeleton can link to AI job, study, capture, booking, member
        $reportId = (string) Str::uuid();
        $now = now();
        DB::table('image_gateway_ai_reports')->insert([
            'id' => $reportId,
            'ai_job_id' => $aiJobId,
            'study_id' => $studyId,
            'capture_set_id' => $fixture['captureSetId'],
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'original_object_key' => 'reports/original/'.Str::uuid().'.pdf',
            'original_checksum' => hash('sha256', 'original-ai-pdf-contents'),
            'original_bytes' => 1024,
            'original_filename' => 'original-ai-report.pdf',
            'derived_object_key' => 'reports/derived/'.Str::uuid().'.pdf',
            'derived_checksum' => hash('sha256', 'derived-indonesia-pdf-contents'),
            'derived_bytes' => 2048,
            'derived_filename' => 'derived-indonesia-report.pdf',
            'status' => 'ready',
            'language' => 'id',
            'clinical_disclaimer' => 'Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $report = DB::table('image_gateway_ai_reports')->where('id', $reportId)->first();
        $this->assertNotNull($report);
        $this->assertSame($aiJobId, $report->ai_job_id);
        $this->assertSame($studyId, $report->study_id);
        $this->assertSame($fixture['memberId'], $report->member_id);
    }

    public function test_status_contract_redaction_exposes_only_safe_fields_and_no_sensitive_data(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $this->aiService->dispatchStudy($studyId, $context);
        $status = $this->aiService->getStatus($studyId, $context);

        $this->assertNotNull($status);
        $expectedKeys = ['ai_job_id', 'study_id', 'status', 'can_retry', 'last_error_code', 'correlation_id'];
        sort($expectedKeys);
        $actualKeys = array_keys($status);
        sort($actualKeys);
        $this->assertSame($expectedKeys, $actualKeys);

        // Verify zero leakage of storage keys, checksums, credentials, or internal details
        $this->assertArrayNotHasKey('object_key', $status);
        $this->assertArrayNotHasKey('checksum', $status);
        $this->assertArrayNotHasKey('bytes', $status);
        $this->assertArrayNotHasKey('dicom', $status);
        $this->assertArrayNotHasKey('password', $status);
        $this->assertArrayNotHasKey('last_error_message', $status);

        // Check retryable failure status shape
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::RETRYABLE_FAILURE,
            'attempts' => 1,
            'last_error_code' => AiErrorCode::AI_PACS_TIMEOUT,
        ]);

        $retryableStatus = $this->aiService->getStatus($studyId, $context);
        $this->assertNotNull($retryableStatus);
        $this->assertSame(AiJobStatus::RETRYABLE_FAILURE, $retryableStatus['status']);
        $this->assertTrue($retryableStatus['can_retry']);
        $this->assertSame(AiErrorCode::AI_PACS_TIMEOUT, $retryableStatus['last_error_code']);

        // Check raw unsanitized error code is redacted to safe processing_error
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'last_error_code' => 'SQLSTATE[42S02]: Base table or view not found: 1146',
        ]);
        $sanitizedStatus = $this->aiService->getStatus($studyId, $context);
        $this->assertNotNull($sanitizedStatus);
        $this->assertSame(AiErrorCode::PROCESSING_ERROR, $sanitizedStatus['last_error_code']);

        // Check terminal failure status shape
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::TERMINAL_FAILURE,
            'attempts' => 3,
            'last_error_code' => AiErrorCode::RETRY_BUDGET_EXHAUSTED,
        ]);

        $terminalStatus = $this->aiService->getStatus($studyId, $context);
        $this->assertNotNull($terminalStatus);
        $this->assertSame(AiJobStatus::TERMINAL_FAILURE, $terminalStatus['status']);
        $this->assertFalse($terminalStatus['can_retry']);
        $this->assertSame(AiErrorCode::RETRY_BUDGET_EXHAUSTED, $terminalStatus['last_error_code']);
    }

    public function test_audit_events_are_recorded_for_dispatch_and_safe_lifecycle_transitions(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        // Verify dispatch audit event
        $dispatchAudit = DB::table('audit_events')
            ->where('action', 'image-gateway.ai-job-dispatched')
            ->where('target_id', $aiJobId)
            ->first();

        $this->assertNotNull($dispatchAudit);
        $this->assertSame('image-gateway', $dispatchAudit->source);
        $this->assertSame('success', $dispatchAudit->outcome);
        $metadata = json_decode((string) $dispatchAudit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($studyId, $metadata['study_id']);
        $this->assertSame('queued', $metadata['status']);
        SensitiveDataSanitizer::assertSafe($metadata);

        // Execute queue worker handle to verify processing claim audit event
        $clock = app(Clock::class);
        $audit = app(AuditStore::class);
        $job = new ProcessAiPacsStudy($aiJobId);
        $job->handle($clock, $audit);

        $processingAudit = DB::table('audit_events')
            ->where('action', 'image-gateway.ai-job-processing')
            ->where('target_id', $aiJobId)
            ->first();

        $this->assertNotNull($processingAudit);
        $this->assertSame('image-gateway.ai-worker', $processingAudit->source);
        $this->assertSame('success', $processingAudit->outcome);
        $procMetadata = json_decode((string) $processingAudit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($studyId, $procMetadata['study_id']);
        $this->assertSame('processing', $procMetadata['status']);
        SensitiveDataSanitizer::assertSafe($procMetadata);

        // Simulate failure via worker
        $claimId = (string) DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->value('processing_claim_id');
        $job->recordFailure($claimId, AiErrorCode::AI_PACS_TIMEOUT, $clock, $audit);

        $failureAudit = DB::table('audit_events')
            ->where('action', 'image-gateway.ai-job-retryable-failure')
            ->where('target_id', $aiJobId)
            ->first();

        $this->assertNotNull($failureAudit);
        $this->assertSame('image-gateway.ai-worker', $failureAudit->source);
        $this->assertSame('failure', $failureAudit->outcome);
        $failMetadata = json_decode((string) $failureAudit->metadata, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(AiErrorCode::AI_PACS_TIMEOUT, $failMetadata['last_error_code']);
        SensitiveDataSanitizer::assertSafe($failMetadata);

        // Retry the study
        $this->aiService->retryStudy($studyId, $context);

        $retryAudit = DB::table('audit_events')
            ->where('action', 'image-gateway.ai-job-retried')
            ->where('target_id', $aiJobId)
            ->first();

        $this->assertNotNull($retryAudit);
        $this->assertSame('image-gateway', $retryAudit->source);
        $this->assertSame('success', $retryAudit->outcome);
    }

    public function test_ai_job_failure_does_not_alter_radiography_session_or_dicom_completion_semantics(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $captureSetId = $fixture['captureSetId'];
        $context = $this->createContext();

        // Radiography capture set is completed with successful DICOM
        $initialCapture = DB::table('image_gateway_capture_sets')->where('id', $captureSetId)->first();
        $this->assertSame('completed', $initialCapture->processing_status);
        $this->assertSame('success', $initialCapture->dicom_status);
        $this->assertSame('success', $initialCapture->mpips_status);

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        $clock = app(Clock::class);
        $audit = app(AuditStore::class);
        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($clock, $audit);

        $claimId = (string) DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->value('processing_claim_id');

        // Exhaust all attempts to force terminal failure of AI job
        DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->update(['attempts' => 3]);
        $worker->recordFailure($claimId, AiErrorCode::AI_PACS_UNAVAILABLE, $clock, $audit);

        $terminalJob = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertSame(AiJobStatus::TERMINAL_FAILURE, $terminalJob->status);

        // Crucial verification: AI failure must NOT alter radiography capture or DICOM completion semantics
        $captureAfterAiFailure = DB::table('image_gateway_capture_sets')->where('id', $captureSetId)->first();
        $this->assertSame('completed', $captureAfterAiFailure->processing_status);
        $this->assertSame('success', $captureAfterAiFailure->dicom_status);
        $this->assertSame('success', $captureAfterAiFailure->mpips_status);
        $this->assertNull($captureAfterAiFailure->failed_at);

        $studyAfterAiFailure = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($studyAfterAiFailure);
        $this->assertSame($fixture['studyId'], $studyAfterAiFailure->id);
    }

    public function test_dispatch_study_rejects_nonexistent_study(): void
    {
        $this->expectException(ImageGatewayException::class);
        $this->expectExceptionMessage('The specified DICOM study does not exist or has incomplete provenance.');

        $this->aiService->dispatchStudy((string) Str::uuid(), $this->createContext());
    }

    public function test_retry_study_enforces_retryable_state_and_budget(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $this->aiService->dispatchStudy($studyId, $context);

        // Cannot retry a queued job
        try {
            $this->aiService->retryStudy($studyId, $context);
            $this->fail('Expected ImageGatewayException when retrying queued job');
        } catch (ImageGatewayException $exception) {
            $this->assertSame('cannot_retry', $exception->category);
        }

        // Set to retryable failure
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::RETRYABLE_FAILURE,
            'attempts' => 1,
            'max_attempts' => 3,
            'last_error_code' => AiErrorCode::AI_PACS_TIMEOUT,
        ]);

        $retried = $this->aiService->retryStudy($studyId, $context);
        $this->assertSame(AiJobStatus::QUEUED, $retried['status']);
        $this->assertNull($retried['last_error_code']);
        Queue::assertPushed(ProcessAiPacsStudy::class, 2);

        // Set to exhausted retry budget
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::RETRYABLE_FAILURE,
            'attempts' => 3,
            'max_attempts' => 3,
        ]);

        try {
            $this->aiService->retryStudy($studyId, $context);
            $this->fail('Expected ImageGatewayException when retrying job with exhausted budget');
        } catch (ImageGatewayException $exception) {
            $this->assertSame('retry_budget_exhausted', $exception->category);
        }
    }

    /** @return array{studyId: string, captureSetId: string, bookingId: string, memberId: string, admissionId: string} */
    private function createStudyFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $now = now();
        $bookingId = $fixture['bookingId'];
        $admissionId = (string) Str::uuid();
        $ticketId = (string) Str::uuid();
        $captureSetId = (string) Str::uuid();
        $studyId = (string) Str::uuid();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'booking_id' => $bookingId,
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'ticket_number' => 'TEST-TICKET-01',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'xray',
            'state' => 'awaiting_ai',
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('image_gateway_capture_sets')->insert([
            'id' => $captureSetId,
            'submission_id' => (string) Str::uuid(),
            'admission_id' => $admissionId,
            'booking_id' => $bookingId,
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_site_id' => $fixture['siteLocalId'],
            'operator_profile_id' => $fixture['profileId'],
            'radiograph_count' => 1,
            'status' => 'accepted',
            'accepted_at' => $now,
            'processing_status' => 'completed',
            'dicom_status' => 'success',
            'mpips_status' => 'success',
            'attempts' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('image_gateway_studies')->insert([
            'id' => $studyId,
            'capture_set_id' => $captureSetId,
            'display_reference' => 'DCM-'.Str::upper(Str::random(8)),
            'object_key' => 'dicom/'.Str::uuid().'.dcm',
            'checksum' => hash('sha256', 'synthetic-dicom-data'),
            'bytes' => 4096,
            'format' => 'application/dicom',
            'study_instance_uid' => '2.25.'.random_int(1000000, 9999999),
            'series_instance_uid' => '2.25.'.random_int(1000000, 9999999),
            'sop_instance_uid' => '2.25.'.random_int(1000000, 9999999),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'studyId' => $studyId,
            'captureSetId' => $captureSetId,
            'bookingId' => $bookingId,
            'memberId' => $fixture['memberId'],
            'admissionId' => $admissionId,
        ];
    }

    private function createContext(): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: LocalId::fromString((string) Str::uuid()),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE,
        );
    }
}
