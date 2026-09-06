<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\GrabberDicomIngestionService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorAiPacsIntegrationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.image_policy.permitted_mimes' => ['application/zip', 'application/x-zip-compressed'],
            'mhcs.image_policy.max_bytes' => 104857600,
            'mhcs.security.manifest_key_id' => 'k1',
            'mhcs.security.manifest_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'mhcs.mpips.base_url' => 'http://127.0.0.1:8014',
            'mhcs.mpips.api_key' => 'test-api-key',
            'mhcs.upload.max_file_bytes' => 104857600,
        ]);
        Storage::fake('local');
    }

    private function createSyntheticDicom(string $content = 'test-dicom-payload'): string
    {
        return str_repeat("\0", 128).'DICM'.$content;
    }

    private function createActiveRadiographySession(array $fixture): array
    {
        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();
        $now = now();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $fixture['bookingId'],
            'ticket_number' => '101',
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
            'state' => 'waiting',
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $locator = app(RadiographySessionLocatorService::class)->allocate(
            $admissionId,
            $fixture['siteLocalId'],
            $fixture['scheduleId'],
        );

        $grabber = app(GrabberClientService::class)->create(
            'GRABBER-DDR-'.Str::upper(Str::random(4)),
            'Test DDR Machine',
            $fixture['siteLocalId'],
        );

        return [
            'admissionId' => $admissionId,
            'ticketId' => $ticketId,
            'locator' => $locator,
            'grabberClient' => $grabber['client'],
        ];
    }

    public function test_dual_source_direct_dicom_ingestion_dispatches_ai_job_asynchronously(): void
    {
        Queue::fake();
        $fixture = $this->operatorFixture(administrator: true);
        $session = $this->createActiveRadiographySession($fixture);

        $ingestionService = app(GrabberDicomIngestionService::class);
        $result = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: (string) Str::uuid(),
            dicomBytes: $this->createSyntheticDicom('direct-dicom-bytes'),
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );

        $this->assertSame('success', $result['status']);
        $studyId = $result['study_id'];

        // AI job record must be created in queued state
        $aiJob = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertNotNull($aiJob);
        $this->assertSame(AiJobStatus::QUEUED, $aiJob->status);
        $this->assertSame($session['admissionId'], $aiJob->admission_id);
    }

    public function test_operator_ui_displays_laporan_ai_column_and_all_status_states_with_disclaimer(): void
    {
        Queue::fake();
        $fixture = $this->operatorFixture(administrator: true);
        $session = $this->createActiveRadiographySession($fixture);

        $ingestionService = app(GrabberDicomIngestionService::class);
        $result = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: (string) Str::uuid(),
            dicomBytes: $this->createSyntheticDicom('status-test-bytes'),
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );
        $studyId = $result['study_id'];

        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        // 1. Initial queued state
        $response = $this->get(route('operator.study.results'));
        $response->assertOk()
            ->assertSee('Laporan AI')
            ->assertSee('Menunggu antrean')
            ->assertSee('Keluaran AI — belum diverifikasi tenaga medis.')
            ->assertDontSee('Unduh Laporan AI');

        // 2. Processing state
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update(['status' => AiJobStatus::PROCESSING]);
        $response = $this->get(route('operator.study.results'));
        $response->assertOk()
            ->assertSee('Sedang dianalisis')
            ->assertSee('Keluaran AI — belum diverifikasi tenaga medis.')
            ->assertDontSee('Unduh Laporan AI');

        // 3. Retryable failure state
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::RETRYABLE_FAILURE,
            'attempts' => 1,
            'max_attempts' => 3,
            'last_error_code' => 'ai_timeout',
        ]);
        $response = $this->get(route('operator.study.results'));
        $response->assertOk()
            ->assertSee('Gagal dan dapat dicoba ulang')
            ->assertSee('Coba Lagi')
            ->assertSee('Keluaran AI — belum diverifikasi tenaga medis.')
            ->assertDontSee('Unduh Laporan AI');

        // 4. Report ready state with derived PDF
        $objects = app(PrivateObjectStore::class);
        $putContext = new AuthenticatedContext(
            actorId: LocalId::fromString($fixture['operator']->id),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        $pdfObject = $objects->put('%PDF-1.4 synthetic derived pdf', $putContext, ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);

        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update(['status' => AiJobStatus::REPORT_READY]);
        $reportId = (string) Str::uuid();
        DB::table('image_gateway_ai_reports')->insert([
            'id' => $reportId,
            'ai_job_id' => DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->value('id'),
            'study_id' => $studyId,
            'capture_set_id' => DB::table('image_gateway_studies')->where('id', $studyId)->value('capture_set_id'),
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'derived_object_key' => (string) $pdfObject->key,
            'derived_checksum' => $pdfObject->checksum,
            'derived_bytes' => $pdfObject->bytes,
            'derived_filename' => 'report.pdf',
            'status' => 'completed',
            'language' => 'id',
            'clinical_disclaimer' => 'Keluaran AI — belum diverifikasi tenaga medis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('operator.study.results'));
        $response->assertOk()
            ->assertSee('Unduh Laporan AI')
            ->assertSee(route('operator.study.ai-report.download', $studyId))
            ->assertSee('Keluaran AI — belum diverifikasi tenaga medis.');
    }

    public function test_authorized_download_streams_pdf_and_logs_audit_without_phi(): void
    {
        Queue::fake();
        $fixture = $this->operatorFixture(administrator: true);
        $session = $this->createActiveRadiographySession($fixture);

        $ingestionService = app(GrabberDicomIngestionService::class);
        $result = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: (string) Str::uuid(),
            dicomBytes: $this->createSyntheticDicom('download-test-dicom'),
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );
        $studyId = $result['study_id'];

        $objects = app(PrivateObjectStore::class);
        $pdfPayload = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
        $putContext = new AuthenticatedContext(
            actorId: LocalId::fromString($fixture['operator']->id),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        $pdfObject = $objects->put($pdfPayload, $putContext, ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);

        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update(['status' => AiJobStatus::REPORT_READY]);
        $reportId = (string) Str::uuid();
        DB::table('image_gateway_ai_reports')->insert([
            'id' => $reportId,
            'ai_job_id' => DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->value('id'),
            'study_id' => $studyId,
            'capture_set_id' => DB::table('image_gateway_studies')->where('id', $studyId)->value('capture_set_id'),
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'derived_object_key' => (string) $pdfObject->key,
            'derived_checksum' => $pdfObject->checksum,
            'derived_bytes' => $pdfObject->bytes,
            'derived_filename' => 'report.pdf',
            'status' => 'completed',
            'language' => 'id',
            'clinical_disclaimer' => 'Keluaran AI — belum diverifikasi tenaga medis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Authorized Operator download succeeds
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $downloadResponse = $this->get(route('operator.study.ai-report.download', $studyId));

        $downloadResponse->assertOk();
        $this->assertSame('application/pdf', $downloadResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $downloadResponse->headers->get('Content-Disposition'));
        $this->assertSame('no-store, private', $downloadResponse->headers->get('Cache-Control'));
        $this->assertSame($pdfPayload, $downloadResponse->streamedContent());

        // Audit log created
        $auditRow = DB::table('audit_events')
            ->where('action', 'operator.ai-report.download')
            ->where('target_id', $studyId)
            ->first();
        $this->assertNotNull($auditRow);
        $this->assertStringNotContainsString('PDF', (string) $auditRow->metadata);
        $this->assertStringNotContainsString('Synthetic Arrival Member', (string) $auditRow->metadata);
    }

    public function test_unauthenticated_download_is_denied_via_login_redirect(): void
    {
        $studyId = (string) Str::uuid();
        $response = $this->get(route('operator.study.ai-report.download', $studyId));
        $response->assertRedirect('/login');
    }

    public function test_cross_site_operator_download_is_forbidden_with_http_403(): void
    {
        $fixtureA = $this->operatorFixture(administrator: true, nik: '900000000010');
        $sessionA = $this->createActiveRadiographySession($fixtureA);

        $ingestionService = app(GrabberDicomIngestionService::class);
        $result = $ingestionService->ingest(
            client: $sessionA['grabberClient'],
            locatorCode: $sessionA['locator']->locator_code,
            submissionId: (string) Str::uuid(),
            dicomBytes: $this->createSyntheticDicom('cross-site-test'),
            requestedSiteId: $fixtureA['siteLocalId'],
            requestedShiftId: $fixtureA['scheduleId'],
        );
        $studyIdA = $result['study_id'];

        // Operator B is assigned to a different site
        $fixtureB = $this->operatorFixture(administrator: false, nik: '900000000020');
        $this->actingAs($fixtureB['operator'])->withSession(['operator.active_site_id' => $fixtureB['siteLocalId']]);

        // Attempting to download study A from site A must return 403 Forbidden
        $response = $this->get(route('operator.study.ai-report.download', $studyIdA));
        $response->assertForbidden();
    }

    public function test_operator_retry_is_idempotent_and_contained(): void
    {
        Queue::fake();
        $fixture = $this->operatorFixture(administrator: true);
        $session = $this->createActiveRadiographySession($fixture);

        $ingestionService = app(GrabberDicomIngestionService::class);
        $result = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: (string) Str::uuid(),
            dicomBytes: $this->createSyntheticDicom('retry-test-dicom'),
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );
        $studyId = $result['study_id'];

        // Mark as retryable failure
        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::RETRYABLE_FAILURE,
            'attempts' => 1,
            'max_attempts' => 3,
            'last_error_code' => 'vendor_timeout',
            'failed_at' => now(),
        ]);

        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $retryResponse = $this->post(route('operator.study.ai-report.retry', $studyId));

        $retryResponse->assertRedirect(route('operator.study.results'));

        $job = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertSame(AiJobStatus::QUEUED, $job->status);
        $this->assertNull($job->last_error_code);
        $this->assertNull($job->failed_at);

        // DICOM study still intact and accessible
        $study = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($study);

        $viewerResponse = $this->get(route('operator.study.show', $studyId));
        $viewerResponse->assertOk();
    }

    public function test_dual_source_legacy_npz_ingestion_dispatches_ai_job_asynchronously(): void
    {
        Queue::fake();
        $fixture = $this->operatorFixture(administrator: false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $ticketId = (string) Str::uuid();
        $admissionId = (string) Str::uuid();
        $now = now();

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $fixture['bookingId'],
            'ticket_number' => '102',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'queue_class' => 'advance',
            'stage' => 'xray',
            'state' => 'called',
            'claimed_at' => $now,
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Http::fake(static fn () => Http::response(
            str_repeat("\0", 128).'DICM'.'npz-converted-dicom-bytes',
            200,
            [
                'Content-Type' => 'application/dicom',
                'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
            ],
        ));

        $this->post(route('operator.xray-capture.store', $admissionId), [
            'submission_id' => (string) Str::uuid(),
            'metadata' => [
                'examination' => ['study_description' => 'CHEST RADIOGRAPH'],
                'capture' => ['detector_type' => 'BED', 'body_part_examined' => 'CHEST', 'laterality' => 'U', 'projection' => 'PA'],
            ],
            'radiograph_npz' => new UploadedFile(
                base_path('resources/fixtures/image-gateway/synthetic-radiograph-01.npz'),
                'synthetic-radiograph-01.npz',
                'application/octet-stream',
                null,
                true,
            ),
            'gain_npz' => new UploadedFile(
                base_path('resources/fixtures/image-gateway/synthetic-gain-01.npz'),
                'synthetic-gain-01.npz',
                'application/octet-stream',
                null,
                true,
            ),
        ])->assertRedirect(route('operator.study.results'));

        $captureId = (string) DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId)->value('id');
        $this->assertNotEmpty($captureId);

        // Run the background processing job for NPZ
        app()->call([new ProcessCaptureSet($captureId), 'handle']);

        // Verify that study was created
        $study = DB::table('image_gateway_studies')->where('capture_set_id', $captureId)->first();
        $this->assertNotNull($study);

        // Verify that AI evaluation job was asynchronously dispatched for the study
        $aiJob = DB::table('image_gateway_ai_jobs')->where('study_id', $study->id)->first();
        $this->assertNotNull($aiJob);
        $this->assertSame(AiJobStatus::QUEUED, $aiJob->status);
        $this->assertSame($admissionId, $aiJob->admission_id);
    }

    public function test_controlled_synthetic_rehearsal_full_flow(): void
    {
        Queue::fake();
        $fixture = $this->operatorFixture(administrator: true);
        $session = $this->createActiveRadiographySession($fixture);

        // 1. Ingest DICOM from DDR Grabber
        $ingestionService = app(GrabberDicomIngestionService::class);
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('rehearsal-full-flow-dicom');

        $result = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: $submissionId,
            dicomBytes: $dicomPayload,
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );

        $this->assertSame('success', $result['status']);
        $studyId = $result['study_id'];

        // 2. AI job is queued asynchronously
        $job = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertNotNull($job);
        $this->assertSame(AiJobStatus::QUEUED, $job->status);

        // 3. AI analysis simulation: transitions to report ready and derived Indonesian PDF generated
        $objects = app(PrivateObjectStore::class);
        $putContext = new AuthenticatedContext(
            actorId: LocalId::fromString($fixture['operator']->id),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        $derivedPdfContent = "%PDF-1.4 synthetic indonesian derived report\nKeluaran AI — belum diverifikasi tenaga medis.";
        $derivedPdfObject = $objects->put($derivedPdfContent, $putContext, ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);

        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update([
            'status' => AiJobStatus::REPORT_READY,
            'completed_at' => now(),
        ]);
        DB::table('image_gateway_ai_reports')->insert([
            'id' => (string) Str::uuid(),
            'ai_job_id' => $job->id,
            'study_id' => $studyId,
            'capture_set_id' => DB::table('image_gateway_studies')->where('id', $studyId)->value('capture_set_id'),
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'derived_object_key' => (string) $derivedPdfObject->key,
            'derived_checksum' => $derivedPdfObject->checksum,
            'derived_bytes' => $derivedPdfObject->bytes,
            'derived_filename' => 'laporan-ai.pdf',
            'status' => 'completed',
            'language' => 'id',
            'clinical_disclaimer' => 'Keluaran AI — belum diverifikasi tenaga medis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Operator logs in and accesses the DICOM results worklist
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $worklistResponse = $this->get(route('operator.study.results'));
        $worklistResponse->assertOk()
            ->assertSee('Laporan AI')
            ->assertSee('Unduh Laporan AI')
            ->assertSee('Keluaran AI — belum diverifikasi tenaga medis.')
            ->assertSee(route('operator.study.ai-report.download', $studyId))
            ->assertSee(route('operator.study.show', $studyId));

        // 5. Operator downloads the derived Indonesian PDF report
        $downloadResponse = $this->get(route('operator.study.ai-report.download', $studyId));
        $downloadResponse->assertOk();
        $this->assertSame('application/pdf', $downloadResponse->headers->get('Content-Type'));
        $this->assertSame($derivedPdfContent, $downloadResponse->streamedContent());

        // 6. DICOM viewer remains accessible and independent
        $viewerResponse = $this->get(route('operator.study.show', $studyId));
        $viewerResponse->assertOk();

        // 7. Audit log confirmed
        $audit = DB::table('audit_events')
            ->where('action', 'operator.ai-report.download')
            ->where('target_id', $studyId)
            ->first();
        $this->assertNotNull($audit);
    }
}
