<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Application\Contracts\AiPacsAdapterContract;
use App\Modules\ImageGateway\Application\Contracts\AiPacsDerivedPdfGeneratorContract;
use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Jobs\ProcessAiPacsStudy;
use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\ImageGateway\Domain\AiPacsDerivedPdfResult;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class AiPacsDerivedPdfIntegrationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    private string $baseUrl = 'http://124.225.183.175:8361';

    private ImageGatewayAiServiceContract $aiService;

    private Clock $clock;

    private AuditStore $audit;

    private PrivateObjectStore $objects;

    private AiPacsAdapterContract $adapter;

    private string $validOriginalPdfContent;

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
            'services.ai_pacs.polling_interval_seconds' => 0,
        ]);

        $this->aiService = app(ImageGatewayAiServiceContract::class);
        $this->clock = app(Clock::class);
        $this->audit = app(AuditStore::class);
        $this->objects = app(PrivateObjectStore::class);
        $this->adapter = app(AiPacsAdapterContract::class);

        $mpdf = new Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML('<p>Patient Name: Purnomo</p><p>MRN: MRN-TEST</p><p>Temuan Radiologis: Toraks simetris</p><p>Kesan: Normal</p>');
        $this->validOriginalPdfContent = $mpdf->Output('', Destination::STRING_RETURN);
    }

    public function test_process_study_generates_derived_indonesian_pdf_with_full_provenance(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response([
                'code' => 0,
                'data' => ['token' => 'test-token-jwt-123'],
            ], 200),
            "{$this->baseUrl}/api/v1/study/upload" => Http::response([
                'code' => 0,
                'message' => 'success',
                'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => 88121, 'aiCalcId' => 88124],
            ], 200),
            "{$this->baseUrl}/api/v1/studies*" => Http::response([
                'code' => 0,
                'data' => ['list' => []],
            ], 200),
            "{$this->baseUrl}/api/v1/study/ai/calc*" => Http::response([
                'code' => 0,
                'data' => ['status' => 'success', 'progress' => 100, 'aiCalcId' => 88124],
            ], 200),
            "{$this->baseUrl}/api/v1/view-report/download*" => Http::response(
                $this->validOriginalPdfContent,
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

        // 1. Verify report record in image_gateway_ai_reports
        $report = DB::table('image_gateway_ai_reports')->where('ai_job_id', $aiJobId)->first();
        $this->assertNotNull($report);
        $this->assertSame('derived_ready', $report->status);
        $this->assertNotNull($report->original_object_key);
        $this->assertNotNull($report->derived_object_key);
        $this->assertNotNull($report->derived_at);
        $this->assertNull($report->derived_error_code);

        // 2. Verify immutability & distinctness: derived != original
        $this->assertNotSame($report->original_object_key, $report->derived_object_key);
        $this->assertNotSame($report->original_checksum, $report->derived_checksum);
        $this->assertSame(hash('sha256', $this->validOriginalPdfContent), $report->original_checksum);

        // 3. Verify original vendor PDF in PrivateObjectStore is completely unchanged
        $origObject = new PrivateObject(
            key: OpaqueObjectKey::fromString((string) $report->original_object_key),
            checksum: (string) $report->original_checksum,
            bytes: (int) $report->original_bytes,
            createdAt: new \DateTimeImmutable((string) $report->created_at),
        );
        $reportReaderContext = new AuthenticatedContext(
            actorId: LocalId::fromString((string) Str::uuid()),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        $origGrant = $this->objects->grant(
            $origObject,
            $reportReaderContext,
            'test-verifier',
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            $this->clock->now()->modify('+60 seconds'),
        );
        $origBytes = $this->objects->get($origGrant, $reportReaderContext, 'test-verifier', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);
        $this->assertSame($this->validOriginalPdfContent, $origBytes);

        // 4. Verify derived PDF exists in PrivateObjectStore and has valid content
        $derivedObject = new PrivateObject(
            key: OpaqueObjectKey::fromString((string) $report->derived_object_key),
            checksum: (string) $report->derived_checksum,
            bytes: (int) $report->derived_bytes,
            createdAt: new \DateTimeImmutable((string) $report->created_at),
        );
        $derivedGrant = $this->objects->grant(
            $derivedObject,
            $reportReaderContext,
            'test-verifier',
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            $this->clock->now()->modify('+60 seconds'),
        );
        $derivedBytes = $this->objects->get($derivedGrant, $reportReaderContext, 'test-verifier', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);
        $this->assertSame($report->derived_checksum, hash('sha256', $derivedBytes));
        $this->assertStringStartsWith('%PDF-', $derivedBytes);

        // 5. Verify full durable provenance linkage
        $this->assertSame($studyId, $report->study_id);
        $this->assertSame($fixture['captureSetId'], $report->capture_set_id);
        $this->assertSame($fixture['memberId'], $report->member_id);
        $this->assertSame(88121, (int) $report->pacs_sid);
        $this->assertSame(88124, (int) $report->pacs_ai_calc_id);

        // 6. Verify audit trail records ai-pdf-derived
        $auditRow = DB::table('audit_events')
            ->where('target_type', 'image-gateway.ai-report')
            ->where('action', 'image-gateway.ai-pdf-derived')
            ->first();
        $this->assertNotNull($auditRow);

        // 7. Verify ImageGatewayAiService protected accessor for both derived and original
        $serviceDerivedGrant = $this->aiService->getReportAccess($studyId, $reportReaderContext, 'derived');
        $this->assertNotNull($serviceDerivedGrant);

        $serviceOrigGrant = $this->aiService->getReportAccess($studyId, $reportReaderContext, 'original');
        $this->assertNotNull($serviceOrigGrant);
    }

    public function test_derived_pdf_generation_idempotency_prevents_duplicate_objects(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response(['code' => 0, 'data' => ['token' => 'jwt']], 200),
            "{$this->baseUrl}/api/v1/study/upload" => Http::response(['code' => 0, 'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => 1, 'aiCalcId' => 2]], 200),
            "{$this->baseUrl}/api/v1/studies*" => Http::response(['code' => 0, 'data' => ['list' => []]], 200),
            "{$this->baseUrl}/api/v1/study/ai/calc*" => Http::response(['code' => 0, 'data' => ['status' => 'success', 'progress' => 100, 'aiCalcId' => 2]], 200),
            "{$this->baseUrl}/api/v1/view-report/download*" => Http::response($this->validOriginalPdfContent, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        $reportAfterFirst = DB::table('image_gateway_ai_reports')->where('ai_job_id', $aiJobId)->first();
        $this->assertNotNull($reportAfterFirst);
        $derivedKey1 = $reportAfterFirst->derived_object_key;
        $derivedChecksum1 = $reportAfterFirst->derived_checksum;

        // Second run: should be completely idempotent
        $worker->handle($this->clock, $this->audit, $this->adapter, $this->objects);

        $reportAfterSecond = DB::table('image_gateway_ai_reports')->where('ai_job_id', $aiJobId)->first();
        $this->assertSame($derivedKey1, $reportAfterSecond->derived_object_key);
        $this->assertSame($derivedChecksum1, $reportAfterSecond->derived_checksum);
    }

    public function test_derived_pdf_failure_is_isolated_and_does_not_fail_original_or_radiography_session(): void
    {
        Http::fake([
            "{$this->baseUrl}/api/v1/login" => Http::response(['code' => 0, 'data' => ['token' => 'jwt']], 200),
            "{$this->baseUrl}/api/v1/study/upload" => Http::response(['code' => 0, 'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => 50, 'aiCalcId' => 60]], 200),
            "{$this->baseUrl}/api/v1/studies*" => Http::response(['code' => 0, 'data' => ['list' => []]], 200),
            "{$this->baseUrl}/api/v1/study/ai/calc*" => Http::response(['code' => 0, 'data' => ['status' => 'success', 'progress' => 100, 'aiCalcId' => 60]], 200),
            "{$this->baseUrl}/api/v1/view-report/download*" => Http::response($this->validOriginalPdfContent, 200, ['Content-Type' => 'application/pdf']),
        ]);

        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $aiJobId = $dispatch['ai_job_id'];

        // Mock a failing derived generator
        $failingGenerator = new class implements AiPacsDerivedPdfGeneratorContract
        {
            public function generateDerivedPdf(string $originalPdfPath, array $provenanceData, string $destinationPath): AiPacsDerivedPdfResult
            {
                throw new ImageGatewayException(AiErrorCode::AI_PACS_INVALID_REPORT, 'Simulated derivation layout failure.');
            }
        };

        $worker = new ProcessAiPacsStudy($aiJobId);
        $worker->handle(
            clock: $this->clock,
            audit: $this->audit,
            adapter: $this->adapter,
            objects: $this->objects,
            derivedGenerator: $failingGenerator,
        );

        // AI Job status remains REPORT_READY (original vendor PDF is safe!)
        $job = DB::table('image_gateway_ai_jobs')->where('id', $aiJobId)->first();
        $this->assertSame(AiJobStatus::REPORT_READY, $job->status);

        // Report has original object key and tracks derived error code
        $report = DB::table('image_gateway_ai_reports')->where('ai_job_id', $aiJobId)->first();
        $this->assertNotNull($report->original_object_key);
        $this->assertNull($report->derived_object_key);
        $this->assertSame(AiErrorCode::AI_PACS_INVALID_REPORT, $report->derived_error_code);

        // Radiography session & capture set remain completed and untouched
        $captureSet = DB::table('image_gateway_capture_sets')->where('id', $fixture['captureSetId'])->first();
        $this->assertSame('completed', $captureSet->processing_status);
        $this->assertSame('success', $captureSet->dicom_status);

        // DICOM study remains untouched
        $study = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($study);

        // Audit records derivation failure
        $failAudit = DB::table('audit_events')
            ->where('target_type', 'image-gateway.ai-report')
            ->where('action', 'image-gateway.ai-pdf-derivation-failed')
            ->first();
        $this->assertNotNull($failAudit);
    }

    public function test_derived_pdf_cannot_masquerade_as_doctor_final_report_or_leak_private_data(): void
    {
        $fixture = $this->createStudyFixture();
        $studyId = $fixture['studyId'];
        $context = $this->createContext();

        $dispatch = $this->aiService->dispatchStudy($studyId, $context);
        $status = $this->aiService->getStatus($studyId, $context);

        $this->assertNotNull($status);
        // Only safe fields exposed
        $expectedKeys = ['ai_job_id', 'study_id', 'status', 'can_retry', 'last_error_code', 'correlation_id'];
        $this->assertSame($expectedKeys, array_keys($status));

        // Zero credentials, storage keys, cookies, or byte arrays
        foreach ($status as $val) {
            $this->assertStringNotContainsString('s3://', (string) $val);
            $this->assertStringNotContainsString('private/', (string) $val);
            $this->assertStringNotContainsString('password', (string) $val);
        }

        // Clinical separation: operator admission remains in radiography/AI stage, not doctor finalized
        $admission = DB::table('operator_queue_admissions')->where('id', $fixture['admissionId'])->first();
        $this->assertNotNull($admission);
        $this->assertSame('awaiting_ai', $admission->state);
        $this->assertSame('xray', $admission->stage);
    }

    private function createStudyFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $now = now();
        $bookingId = $fixture['bookingId'];
        $admissionId = (string) Str::uuid();
        $ticketId = (string) Str::uuid();
        $captureSetId = (string) Str::uuid();
        $studyId = (string) Str::uuid();

        DB::table('members')->where('id', $fixture['memberId'])->update([
            'name' => 'Purnomo',
            'administrative_gender' => 'laki-laki',
            'medical_record_number' => 'MRN-TEST',
        ]);

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

        $im = imagecreatetruecolor(100, 120);
        $bg = imagecolorallocate($im, 20, 20, 20);
        imagefilledrectangle($im, 0, 0, 99, 119, (int) $bg);
        ob_start();
        imagepng($im);
        $syntheticImgBytes = (string) ob_get_clean();
        imagedestroy($im);

        $storedImg = $this->objects->put(
            $syntheticImgBytes,
            new AuthenticatedContext(
                actorId: LocalId::fromString($captureSetId),
                operationId: new CorrelationId($captureSetId),
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            ),
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );

        DB::table('image_gateway_capture_objects')->insert([
            'id' => (string) Str::uuid(),
            'capture_set_id' => $captureSetId,
            'object_type' => 'radiograph_image',
            'object_index' => 0,
            'object_key' => (string) $storedImg->key,
            'checksum' => $storedImg->checksum,
            'bytes' => $storedImg->bytes,
            'format' => 'image/png',
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
