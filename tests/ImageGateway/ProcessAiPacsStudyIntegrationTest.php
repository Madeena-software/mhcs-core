<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Application\Contracts\AiPacsAdapterContract;
use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Jobs\ProcessAiPacsStudy;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class ProcessAiPacsStudyIntegrationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    private string $baseUrl = 'http://124.225.183.175:8361';

    private ImageGatewayAiServiceContract $aiService;

    private Clock $clock;

    private AuditStore $audit;

    private PrivateObjectStore $objects;

    private AiPacsAdapterContract $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.private_object_disk' => 'local',
            'services.ai_pacs.base_url' => $this->baseUrl,
            'services.ai_pacs.username' => 'test_user',
            'services.ai_pacs.password' => 'test_password',
            'services.ai_pacs.timeout_seconds' => 5,
            'services.ai_pacs.max_polling_attempts' => 5,
        ]);

        $this->aiService = app(ImageGatewayAiServiceContract::class);
        $this->clock = app(Clock::class);
        $this->audit = app(AuditStore::class);
        $this->objects = app(PrivateObjectStore::class);
        $this->adapter = app(AiPacsAdapterContract::class);
    }

    public function test_process_study_full_flow_success(): void
    {
        $validPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\nxref\n0 1\n0000000000 65535 f\ntrailer<</Size 1>>\nstartxref\n50\n%%EOF";
        $validPdf = str_pad($validPdf, 256, "\n")."%%EOF";

        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 0,
                'data' => ['token' => 'test-token-jwt-123'],
            ], 200),
            "{$this->baseUrl}/api/v1/studies" => Http::response([
                'code' => 0,
                'data' => ['sid' => 54321, 'aiCalcId' => 88],
            ], 200),
            "{$this->baseUrl}/api/v1/study/ai/calc*" => Http::sequence()
                ->push(['code' => 0, 'data' => ['status' => 'calculating', 'progress' => 50]], 200)
                ->push(['code' => 0, 'data' => ['status' => 'success', 'progress' => 100, 'aiCalcId' => 88]], 200),
            "{$this->baseUrl}/api/v1/view-report/download*" => Http::response(
                $validPdf,
                200,
                ['Content-Type' => 'application/pdf'],
            ),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        // Verify AI job status transitioned to report_ready
        $job = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertNotNull($job);
        $this->assertSame(AiJobStatus::REPORT_READY, $job->status);
        $this->assertNull($job->last_error_code);
        $this->assertNotNull($job->completed_at);

        // Verify report record in image_gateway_ai_reports
        $report = DB::table('image_gateway_ai_reports')->where('ai_job_id', $aiJobId)->first();
        $this->assertNotNull($report);
        $this->assertSame($studyId, $report->study_id);
        $this->assertSame($fixture['captureSetId'], $report->capture_set_id);
        $this->assertSame($fixture['memberId'], $report->member_id);
        $this->assertSame(hash('sha256', $validPdf), $report->original_checksum);
        $this->assertSame(strlen($validPdf), (int) $report->original_bytes);
        $this->assertSame('original_ready', $report->status);

        // Verify original PDF exists in PrivateObjectStore
        $pdfObject = new \App\Shared\Storage\PrivateObject(
            \App\Shared\Storage\OpaqueObjectKey::fromString((string) $report->original_object_key),
            $report->original_checksum,
            (int) $report->original_bytes,
            new \DateTimeImmutable((string) $report->created_at),
        );
        $reportReaderContext = new AuthenticatedContext(
            actorId: LocalId::fromString((string) Str::uuid()),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        $readGrant = $this->objects->grant(
            $pdfObject,
            $reportReaderContext,
            'test-reader',
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            $this->clock->now()->modify('+60 seconds'),
        );
        $retrievedPdf = $this->objects->get(
            $readGrant,
            $reportReaderContext,
            'test-reader',
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        $this->assertSame($validPdf, $retrievedPdf);

        // Verify audit trail
        $this->assertNotNull(DB::table('audit_events')
            ->where('action', 'image-gateway.ai-pacs-authenticated')
            ->where('target_id', $aiJobId)
            ->first());
        $this->assertNotNull(DB::table('audit_events')
            ->where('action', 'image-gateway.ai-pacs-study-uploaded')
            ->where('target_id', $aiJobId)
            ->first());
        $this->assertNotNull(DB::table('audit_events')
            ->where('action', 'image-gateway.ai-pacs-report-downloaded')
            ->where('target_id', $aiJobId)
            ->first());
        $this->assertNotNull(DB::table('audit_events')
            ->where('action', 'image-gateway.ai-job-completed')
            ->where('target_id', $aiJobId)
            ->first());

        // Verify radiography capture set semantics remained intact
        $capture = DB::table('image_gateway_capture_sets')->where('id', $fixture['captureSetId'])->first();
        $this->assertSame('completed', $capture->processing_status);
        $this->assertSame('success', $capture->dicom_status);
    }

    public function test_process_study_authentication_failure(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 1002,
                'message' => '账号不存在',
            ], 200),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        $job = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertSame(AiJobStatus::RETRYABLE_FAILURE, $job->status);
        $this->assertSame(AiErrorCode::AI_PACS_AUTH_FAILED, $job->last_error_code);

        // Radiography capture set must NOT be affected
        $capture = DB::table('image_gateway_capture_sets')->where('id', $fixture['captureSetId'])->first();
        $this->assertSame('completed', $capture->processing_status);
        $this->assertSame('success', $capture->dicom_status);
    }

    public function test_process_study_upload_failure(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 0,
                'data' => ['token' => 'valid-token'],
            ], 200),
            "{$this->baseUrl}/api/v1/studies" => Http::response([
                'code' => 5001,
                'message' => 'file format error',
            ], 200),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        $job = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertSame(AiJobStatus::RETRYABLE_FAILURE, $job->status);
        $this->assertSame(AiErrorCode::AI_PACS_UPLOAD_FAILED, $job->last_error_code);
    }

    public function test_process_study_polling_timeout(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 0,
                'data' => ['token' => 'valid-token'],
            ], 200),
            "{$this->baseUrl}/api/v1/studies" => Http::response([
                'code' => 0,
                'data' => ['sid' => 99999, 'aiCalcId' => 11],
            ], 200),
            // Always pending
            "{$this->baseUrl}/api/v1/study/ai/calc*" => Http::response([
                'code' => 0,
                'data' => ['status' => 'calculating', 'progress' => 10],
            ], 200),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        $job = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertSame(AiJobStatus::RETRYABLE_FAILURE, $job->status);
        $this->assertSame(AiErrorCode::AI_PACS_TIMEOUT, $job->last_error_code);
    }

    public function test_process_study_terminal_failure_when_budget_exhausted(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => fn () => throw new ConnectionException('Network down'),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        // Set attempts to 2 so next attempt hits attempt 3 (max_attempts = 3)
        DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->update(['attempts' => 2]);

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        $job = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertSame(AiJobStatus::TERMINAL_FAILURE, $job->status);
        $this->assertSame(AiErrorCode::RETRY_BUDGET_EXHAUSTED, $job->last_error_code);

        // Radiography capture set is NOT failed
        $capture = DB::table('image_gateway_capture_sets')->where('id', $fixture['captureSetId'])->first();
        $this->assertSame('completed', $capture->processing_status);
        $this->assertSame('success', $capture->dicom_status);
    }

    public function test_controlled_smoke_test_synthetic_dicom_isolation(): void
    {
        // Controlled smoke test verifying that only synthetic / deidentified data is processed
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];

        $study = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($study);

        $member = DB::table('members')->where('id', $fixture['memberId'])->first();
        $this->assertSame('Synthetic Arrival Member', $member->name);

        // Verify DICOM study payload is synthetic and contains no patient PHI
        $context = $this->createContext();
        $dicomObject = new \App\Shared\Storage\PrivateObject(
            \App\Shared\Storage\OpaqueObjectKey::fromString((string) $study->object_key),
            (string) $study->checksum,
            (int) $study->bytes,
            new \DateTimeImmutable((string) $study->created_at),
        );
        $grant = $this->objects->grant($dicomObject, $context, 'smoke-test', ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE, $this->clock->now()->modify('+60 seconds'));
        $payload = $this->objects->get($grant, $context, 'smoke-test', ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE);

        $this->assertStringContainsString('SYNTHETIC-DICOM-FIXTURE', $payload);
        $this->assertStringNotContainsString('NIK', $payload);
        $this->assertStringNotContainsString('patient_real_name', $payload);
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
            'ticket_number' => 'TEST-TICKET-02',
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

        $syntheticDicom = str_repeat("\0", 128).'DICM'.'SYNTHETIC-DICOM-FIXTURE-DATA-NO-PHI';
        $storedDicom = $this->objects->put(
            $syntheticDicom,
            new AuthenticatedContext(
                actorId: LocalId::fromString($captureSetId),
                operationId: new CorrelationId($captureSetId),
                purpose: ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE,
            ),
            ImageGatewayAiServiceContract::AI_DISPATCH_PURPOSE,
        );

        DB::table('image_gateway_studies')->insert([
            'id' => $studyId,
            'capture_set_id' => $captureSetId,
            'display_reference' => 'DCM-'.Str::upper(Str::random(8)),
            'object_key' => (string) $storedDicom->key,
            'checksum' => $storedDicom->checksum,
            'bytes' => $storedDicom->bytes,
            'format' => 'application/dicom',
            'filename' => 'synthetic-study.dcm',
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
