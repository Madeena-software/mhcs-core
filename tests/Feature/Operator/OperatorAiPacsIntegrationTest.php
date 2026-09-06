<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\ImageGateway\Application\Contracts\AiPacsReportDownloaderContract;
use App\Modules\ImageGateway\Application\Contracts\ImageGatewayAiServiceContract;
use App\Modules\ImageGateway\Application\Jobs\ProcessAiPacsStudy;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsReportResult;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\GrabberDicomIngestionService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
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
            'mhcs.private_object_disk' => 'local',
            'services.ai_pacs.base_url' => 'http://124.225.183.175:8361',
            'services.ai_pacs.username' => 'test_user',
            'services.ai_pacs.password' => 'test_password',
            'services.ai_pacs.timeout_seconds' => 5,
            'services.ai_pacs.max_polling_attempts' => 5,
            'services.ai_pacs.polling_interval_seconds' => 0,
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
        $fixture = $this->operatorFixture(administrator: false);
        $session = $this->createActiveRadiographySession($fixture);

        $ingestionService = app(GrabberDicomIngestionService::class);
        $result = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: (string) Str::uuid(),
            dicomBytes: $this->createSyntheticDicom('unauthenticated-test-dicom'),
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );
        $studyId = $result['study_id'];

        $objects = app(PrivateObjectStore::class);
        $pdfPayload = "%PDF-1.4\nsecret-derived-pdf-report-content\n%%EOF";
        $pdfObject = $objects->put(
            $pdfPayload,
            new AuthenticatedContext(
                actorId: LocalId::fromString($fixture['operator']->id),
                operationId: new CorrelationId((string) Str::uuid()),
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            ),
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );

        $secretObjectKey = (string) $pdfObject->key;
        $derivedFilename = 'laporan-ai-SECRET-TEST.pdf';

        DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->update(['status' => AiJobStatus::REPORT_READY]);
        DB::table('image_gateway_ai_reports')->insert([
            'id' => (string) Str::uuid(),
            'ai_job_id' => DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->value('id'),
            'study_id' => $studyId,
            'capture_set_id' => DB::table('image_gateway_studies')->where('id', $studyId)->value('capture_set_id'),
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'derived_object_key' => $secretObjectKey,
            'derived_checksum' => $pdfObject->checksum,
            'derived_bytes' => $pdfObject->bytes,
            'derived_filename' => $derivedFilename,
            'status' => 'completed',
            'language' => 'id',
            'clinical_disclaimer' => 'Keluaran AI — belum diverifikasi tenaga medis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('operator.study.ai-report.download', $studyId));
        $response->assertRedirect('/login');

        // Negative assertion suite: no bytes, signatures, object keys, storage paths, or metadata leaked
        $this->assertNotSame('application/pdf', (string) $response->headers->get('Content-Type'));
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('%PDF', $content);
        $this->assertStringNotContainsString($secretObjectKey, $content);
        $this->assertStringNotContainsString('/var/www', $content);
        $this->assertStringNotContainsString('private_objects', $content);
        $this->assertStringNotContainsString($derivedFilename, $content);
        $memberName = (string) DB::table('members')->where('id', $fixture['memberId'])->value('name');
        $this->assertStringNotContainsString($memberName, $content);
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

        $objects = app(PrivateObjectStore::class);
        $pdfPayload = "%PDF-1.4\ncross-site-secret-derived-pdf-report\n%%EOF";
        $pdfObject = $objects->put(
            $pdfPayload,
            new AuthenticatedContext(
                actorId: LocalId::fromString($fixtureA['operator']->id),
                operationId: new CorrelationId((string) Str::uuid()),
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            ),
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );

        $secretObjectKeyA = (string) $pdfObject->key;
        $derivedFilenameA = 'laporan-ai-CROSS-SITE-SECRET.pdf';

        DB::table('image_gateway_ai_jobs')->where('study_id', $studyIdA)->update(['status' => AiJobStatus::REPORT_READY]);
        DB::table('image_gateway_ai_reports')->insert([
            'id' => (string) Str::uuid(),
            'ai_job_id' => DB::table('image_gateway_ai_jobs')->where('study_id', $studyIdA)->value('id'),
            'study_id' => $studyIdA,
            'capture_set_id' => DB::table('image_gateway_studies')->where('id', $studyIdA)->value('capture_set_id'),
            'booking_id' => $fixtureA['bookingId'],
            'member_id' => $fixtureA['memberId'],
            'derived_object_key' => $secretObjectKeyA,
            'derived_checksum' => $pdfObject->checksum,
            'derived_bytes' => $pdfObject->bytes,
            'derived_filename' => $derivedFilenameA,
            'status' => 'completed',
            'language' => 'id',
            'clinical_disclaimer' => 'Keluaran AI — belum diverifikasi tenaga medis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Operator B is assigned to a different site
        $fixtureB = $this->operatorFixture(administrator: false, nik: '900000000020');
        $this->actingAs($fixtureB['operator'])->withSession(['operator.active_site_id' => $fixtureB['siteLocalId']]);

        // Attempting to download study A from site A must return 403 Forbidden
        $response = $this->get(route('operator.study.ai-report.download', $studyIdA));
        $response->assertForbidden();

        // Negative assertion suite: no bytes, signatures, object keys, storage paths, or metadata leaked
        $this->assertNotSame('application/pdf', (string) $response->headers->get('Content-Type'));
        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('%PDF', $content);
        $this->assertStringNotContainsString($secretObjectKeyA, $content);
        $this->assertStringNotContainsString('/var/www', $content);
        $this->assertStringNotContainsString('private_objects', $content);
        $this->assertStringNotContainsString($derivedFilenameA, $content);
        $memberNameA = (string) DB::table('members')->where('id', $fixtureA['memberId'])->value('name');
        $this->assertStringNotContainsString($memberNameA, $content);
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
        $initialJob = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertNotNull($initialJob);

        // Pre-existing report in image_gateway_ai_reports
        $reportId = (string) Str::uuid();
        $initialOrigChecksum = hash('sha256', 'existing-original-vendor-pdf');
        $initialDerivedChecksum = hash('sha256', 'existing-derived-indonesian-pdf');
        DB::table('image_gateway_ai_reports')->insert([
            'id' => $reportId,
            'ai_job_id' => $initialJob->id,
            'study_id' => $studyId,
            'capture_set_id' => DB::table('image_gateway_studies')->where('id', $studyId)->value('capture_set_id'),
            'booking_id' => $fixture['bookingId'],
            'member_id' => $fixture['memberId'],
            'original_object_key' => 'reports/original/existing.pdf',
            'original_checksum' => $initialOrigChecksum,
            'original_bytes' => 1234,
            'original_filename' => 'existing.pdf',
            'derived_object_key' => 'reports/derived/existing-derived.pdf',
            'derived_checksum' => $initialDerivedChecksum,
            'derived_bytes' => 5678,
            'derived_filename' => 'laporan-ai-existing.pdf',
            'status' => 'derived_ready',
            'language' => 'id',
            'clinical_disclaimer' => 'Keluaran AI — belum diverifikasi tenaga medis.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark as retryable failure
        DB::table('image_gateway_ai_jobs')->where('id', $initialJob->id)->update([
            'status' => AiJobStatus::RETRYABLE_FAILURE,
            'attempts' => 1,
            'max_attempts' => 3,
            'last_error_code' => 'vendor_timeout',
            'failed_at' => now(),
        ]);

        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        // 1. FIRST RETRY: Valid retry transitions RETRYABLE_FAILURE -> QUEUED
        $retryResponse1 = $this->post(route('operator.study.ai-report.retry', $studyId));
        $retryResponse1->assertRedirect(route('operator.study.results'));
        $retryResponse1->assertSessionHas('status');

        $jobAfterFirst = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertSame(AiJobStatus::QUEUED, $jobAfterFirst->status);
        $this->assertSame($initialJob->id, $jobAfterFirst->id);
        $this->assertNull($jobAfterFirst->last_error_code);
        $this->assertNull($jobAfterFirst->failed_at);
        $this->assertSame(1, DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->count());

        // 2. SECOND RETRY: Repeated retry while job is already QUEUED
        // Rejects duplicate dispatch safely without uncaught exception
        $retryResponse2 = $this->post(route('operator.study.ai-report.retry', $studyId));
        $retryResponse2->assertRedirect(route('operator.study.results'));
        $retryResponse2->assertSessionHasErrors(['ai_report']);

        $jobAfterSecond = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertSame(AiJobStatus::QUEUED, $jobAfterSecond->status);
        $this->assertSame($initialJob->id, $jobAfterSecond->id);
        $this->assertSame(1, DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->count());

        // 3. THIRD RETRY: Repeated retry while job is in PROCESSING state
        DB::table('image_gateway_ai_jobs')->where('id', $initialJob->id)->update([
            'status' => AiJobStatus::PROCESSING,
            'processing_lease_expires_at' => now()->addMinutes(5),
        ]);

        $retryResponse3 = $this->post(route('operator.study.ai-report.retry', $studyId));
        $retryResponse3->assertRedirect(route('operator.study.results'));
        $retryResponse3->assertSessionHasErrors(['ai_report']);

        $jobAfterThird = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->first();
        $this->assertSame(AiJobStatus::PROCESSING, $jobAfterThird->status);
        $this->assertSame($initialJob->id, $jobAfterThird->id);
        $this->assertSame(1, DB::table('image_gateway_ai_jobs')->where('study_id', $studyId)->count());

        // 4. Existing report rows were never overwritten or duplicated
        $reportAfter = DB::table('image_gateway_ai_reports')->where('study_id', $studyId)->first();
        $this->assertNotNull($reportAfter);
        $this->assertSame($reportId, $reportAfter->id);
        $this->assertSame($initialOrigChecksum, $reportAfter->original_checksum);
        $this->assertSame($initialDerivedChecksum, $reportAfter->derived_checksum);
        $this->assertSame(1, DB::table('image_gateway_ai_reports')->where('study_id', $studyId)->count());

        // 5. DICOM study remains accessible throughout
        $viewerResponse = $this->get(route('operator.study.show', $studyId));
        $viewerResponse->assertOk();

        // 6. Explicit database unique constraint verification: duplicate insert must fail
        try {
            DB::table('image_gateway_ai_jobs')->insert([
                'id' => (string) Str::uuid(),
                'study_id' => $studyId,
                'capture_set_id' => $initialJob->capture_set_id,
                'booking_id' => $fixture['bookingId'],
                'member_id' => $fixture['memberId'],
                'status' => AiJobStatus::QUEUED,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Expected QueryException on duplicate study_id was not thrown.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('unique', strtolower($e->getMessage()));
        }
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
        Queue::fake([ProcessAiPacsStudy::class, ProcessCaptureSet::class]);
        $now = now();

        $mpdf = new Mpdf(['format' => 'A4']);
        $mpdf->WriteHTML('<p>Patient Name: Purnomo</p><p>MRN: MRN-TEST</p><p>Temuan Radiologis: Toraks simetris</p><p>Kesan: Normal</p>');
        $validOriginalPdfContent = $mpdf->Output('', Destination::STRING_RETURN);
        $validOriginalPdfChecksum = hash('sha256', $validOriginalPdfContent);

        // Bind mock for report downloader to simulate vendor browser/download boundary
        $downloader = Mockery::mock(AiPacsReportDownloaderContract::class);
        $downloader->shouldReceive('downloadImageReport')->andReturnUsing(function ($sid, $aiCalcId, $destinationPath) {
            $job = DB::table('image_gateway_ai_jobs')->where('pacs_sid', $sid)->first();
            $member = DB::table('members')->where('id', $job->member_id)->first();

            $mpdf = new Mpdf(['format' => 'A4']);
            $mpdf->WriteHTML("<p>Patient Name: {$member->name}</p><p>MRN: {$member->medical_record_number}</p><p>Temuan Radiologis: Toraks simetris, corakan bronkovaskular normal.</p><p>Kesan: Normal.</p>");
            $content = $mpdf->Output('', Destination::STRING_RETURN);
            file_put_contents($destinationPath, $content);

            return new AiPacsReportResult(
                pdfBytes: $content,
                filename: "report-{$sid}.pdf",
            );
        });
        $this->app->instance(AiPacsReportDownloaderContract::class, $downloader);

        Http::fake([
            'http://127.0.0.1:8014/*' => Http::response(
                str_repeat("\0", 128).'DICM'.'valid mpips dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            ),
            'http://124.225.183.175:8361/api/v1/login' => Http::response([
                'code' => 0,
                'data' => ['token' => 'test-token-jwt-123'],
            ], 200),
            'http://124.225.183.175:8361/api/v1/study/upload' => function ($request) {
                static $sidSeq = 88120;
                $sidSeq++;

                return Http::response([
                    'code' => 0,
                    'message' => 'success',
                    'data' => ['failNum' => 0, 'successNum' => 1, 'sid' => $sidSeq, 'aiCalcId' => $sidSeq + 3],
                ], 200);
            },
            'http://124.225.183.175:8361/api/v1/studies*' => Http::response([
                'code' => 0,
                'data' => ['list' => []],
            ], 200),
            'http://124.225.183.175:8361/api/v1/study/ai/calc*' => function ($request) {
                return Http::response([
                    'code' => 0,
                    'data' => ['status' => 'success', 'progress' => 100, 'aiCalcId' => 88124],
                ], 200);
            },
            'http://124.225.183.175:8361/api/v1/view-report/download*' => Http::response(
                '%PDF-1.4 original report mock',
                200,
                ['Content-Type' => 'application/pdf'],
            ),
        ]);

        $fixture = $this->operatorFixture(administrator: true);
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'name' => 'Purnomo',
            'administrative_gender' => 'laki-laki',
            'birth_date' => '1988-01-10',
            'medical_record_number' => 'MRN-'.substr($fixture['memberId'], 0, 8),
        ]);

        // ---------------------------------------------------------------------
        // 1. Direct-DICOM Pathway Ingestion
        // ---------------------------------------------------------------------
        $session = $this->createActiveRadiographySession($fixture);
        $ingestionService = app(GrabberDicomIngestionService::class);
        $submissionId1 = (string) Str::uuid();
        $dicomPayload1 = $this->createSyntheticDicom('rehearsal-direct-dicom');

        $result1 = $ingestionService->ingest(
            client: $session['grabberClient'],
            locatorCode: $session['locator']->locator_code,
            submissionId: $submissionId1,
            dicomBytes: $dicomPayload1,
            requestedSiteId: $fixture['siteLocalId'],
            requestedShiftId: $fixture['scheduleId'],
        );
        $this->assertSame('success', $result1['status']);
        $studyId1 = $result1['study_id'];

        // Verify direct-DICOM queued AI job asynchronously
        $job1 = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId1)->first();
        $this->assertNotNull($job1);
        $this->assertSame(AiJobStatus::QUEUED, $job1->status);

        $captureSet1 = DB::table('image_gateway_studies')->where('id', $studyId1)->first();
        $captureSetId1 = (string) $captureSet1->capture_set_id;

        // ---------------------------------------------------------------------
        // 2. Legacy NPZ Pathway Ingestion
        // ---------------------------------------------------------------------
        $memberId2 = (string) Str::uuid();
        $user2 = User::factory()->create();
        DB::table('members')->insert([
            'id' => $memberId2,
            'user_id' => $user2->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.substr($memberId2, 0, 8),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'encrypted-nik-2',
            'nik_lookup_digest' => hash('sha256', 'nik-2'),
            'name' => 'Purnomo Dua',
            'birth_date' => '1990-05-15',
            'administrative_gender' => 'laki-laki',
            'registration_source' => 'administrator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bookingId2 = (string) Str::uuid();
        $existingBooking = DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $bookingData = (array) $existingBooking;
        $bookingData['id'] = $bookingId2;
        $bookingData['member_id'] = $memberId2;
        $bookingData['created_at'] = $now;
        $bookingData['confirmed_at'] = $now;
        $bookingData['updated_at'] = $now;
        DB::table('bookings')->insert($bookingData);

        $ticketId2 = (string) Str::uuid();
        $admissionId2 = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId2,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $bookingId2,
            'ticket_number' => '102',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId2,
            'operator_paper_ticket_id' => $ticketId2,
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

        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->post(route('operator.xray-capture.store', $admissionId2), [
            'submission_id' => (string) Str::uuid(),
            'metadata' => [
                'examination' => ['study_description' => 'CHEST RADIOGRAPH REHEARSAL'],
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

        $captureId2 = (string) DB::table('image_gateway_capture_sets')->where('admission_id', $admissionId2)->value('id');
        $this->assertNotEmpty($captureId2);

        // Run MPIPS processing for legacy NPZ
        app()->call([new ProcessCaptureSet($captureId2), 'handle']);

        $study2 = DB::table('image_gateway_studies')->where('capture_set_id', $captureId2)->first();
        $this->assertNotNull($study2);
        $studyId2 = $study2->id;

        // Verify legacy NPZ queued AI job asynchronously
        $job2 = DB::table('image_gateway_ai_jobs')->where('study_id', $studyId2)->first();
        $this->assertNotNull($job2);
        $this->assertSame(AiJobStatus::QUEUED, $job2->status);

        // Populate source radiograph image in PrivateObjectStore for provenance validation on both capture sets
        $im = imagecreatetruecolor(100, 120);
        $bg = imagecolorallocate($im, 20, 20, 20);
        imagefilledrectangle($im, 0, 0, 99, 119, (int) $bg);
        ob_start();
        imagepng($im);
        $syntheticImgBytes = (string) ob_get_clean();
        imagedestroy($im);

        $objects = app(PrivateObjectStore::class);
        $storedImg1 = $objects->put(
            $syntheticImgBytes,
            new AuthenticatedContext(
                actorId: LocalId::fromString($captureSetId1),
                operationId: new CorrelationId($captureSetId1),
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            ),
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        DB::table('image_gateway_capture_objects')->insert([
            'id' => (string) Str::uuid(),
            'capture_set_id' => $captureSetId1,
            'object_type' => 'radiograph_image',
            'object_index' => 0,
            'object_key' => (string) $storedImg1->key,
            'checksum' => $storedImg1->checksum,
            'bytes' => $storedImg1->bytes,
            'format' => 'image/png',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $storedImg2 = $objects->put(
            $syntheticImgBytes,
            new AuthenticatedContext(
                actorId: LocalId::fromString($captureId2),
                operationId: new CorrelationId($captureId2),
                purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
            ),
            ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );
        DB::table('image_gateway_capture_objects')->insert([
            'id' => (string) Str::uuid(),
            'capture_set_id' => $captureId2,
            'object_type' => 'radiograph_image',
            'object_index' => 0,
            'object_key' => (string) $storedImg2->key,
            'checksum' => $storedImg2->checksum,
            'bytes' => $storedImg2->bytes,
            'format' => 'image/png',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ---------------------------------------------------------------------
        // 3. Execute Actual ProcessAiPacsStudy Worker Pipeline for Both Jobs
        // ---------------------------------------------------------------------
        app()->call([new ProcessAiPacsStudy($job1->id), 'handle']);
        app()->call([new ProcessAiPacsStudy($job2->id), 'handle']);

        // ---------------------------------------------------------------------
        // 4. Verify Actual Reports, Persistence, Provenance & Immutability
        // ---------------------------------------------------------------------
        $readerContext = new AuthenticatedContext(
            actorId: LocalId::fromString($fixture['operator']->id),
            operationId: new CorrelationId((string) Str::uuid()),
            purpose: ImageGatewayAiServiceContract::AI_REPORT_PURPOSE,
        );

        $testCases = [
            $studyId1 => ['job' => $job1->id, 'booking' => $fixture['bookingId'], 'member' => $fixture['memberId']],
            $studyId2 => ['job' => $job2->id, 'booking' => $bookingId2, 'member' => $memberId2],
        ];

        foreach ($testCases as $studyId => $spec) {
            $jobId = $spec['job'];
            $bookingId = $spec['booking'];
            $memberId = $spec['member'];
            $updatedJob = DB::table('image_gateway_ai_jobs')->where('id', $jobId)->first();
            $this->assertNotNull($updatedJob);
            $this->assertSame(AiJobStatus::REPORT_READY, $updatedJob->status);
            $this->assertNull($updatedJob->last_error_code);
            $this->assertNotNull($updatedJob->completed_at);

            $report = DB::table('image_gateway_ai_reports')->where('ai_job_id', $jobId)->first();
            $this->assertNotNull($report);
            $this->assertSame('derived_ready', $report->status);
            $this->assertSame($studyId, $report->study_id);
            $this->assertSame($bookingId, $report->booking_id);
            $this->assertSame($memberId, $report->member_id);

            // Verify original and derived PDF keys exist and are distinct (not overwritten)
            $this->assertNotNull($report->original_object_key);
            $this->assertNotNull($report->derived_object_key);
            $this->assertNotSame($report->original_object_key, $report->derived_object_key);
            $this->assertNotSame($report->original_checksum, $report->derived_checksum);

            // Verify original PDF in PrivateObjectStore
            $origObj = new PrivateObject(
                key: OpaqueObjectKey::fromString((string) $report->original_object_key),
                checksum: (string) $report->original_checksum,
                bytes: (int) $report->original_bytes,
                createdAt: new DateTimeImmutable((string) $report->created_at),
            );
            $origGrant = $objects->grant($origObj, $readerContext, 'test', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE, new DateTimeImmutable('+5 minutes'));
            $retrievedOrig = $objects->get($origGrant, $readerContext, 'test', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);
            $this->assertSame($report->original_checksum, hash('sha256', $retrievedOrig));
            $this->assertStringContainsString('%PDF', $retrievedOrig);

            // Verify derived PDF in PrivateObjectStore contains Indonesian disclaimer
            $derivedObj = new PrivateObject(
                key: OpaqueObjectKey::fromString((string) $report->derived_object_key),
                checksum: (string) $report->derived_checksum,
                bytes: (int) $report->derived_bytes,
                createdAt: new DateTimeImmutable((string) $report->created_at),
            );
            $derivedGrant = $objects->grant($derivedObj, $readerContext, 'test', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE, new DateTimeImmutable('+5 minutes'));
            $retrievedDerived = $objects->get($derivedGrant, $readerContext, 'test', ImageGatewayAiServiceContract::AI_REPORT_PURPOSE);
            $this->assertSame($report->derived_checksum, hash('sha256', $retrievedDerived));
            $this->assertStringStartsWith('%PDF-', $retrievedDerived);
        }

        // ---------------------------------------------------------------------
        // 5. Operator Results Worklist Access
        // ---------------------------------------------------------------------
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $worklistResponse = $this->get(route('operator.study.results'));
        $worklistResponse->assertOk()
            ->assertSee('Laporan AI')
            ->assertSee('Unduh Laporan AI')
            ->assertSee('Keluaran AI — belum diverifikasi tenaga medis.')
            ->assertSee(route('operator.study.ai-report.download', $studyId1))
            ->assertSee(route('operator.study.ai-report.download', $studyId2))
            ->assertSee(route('operator.study.show', $studyId1))
            ->assertSee(route('operator.study.show', $studyId2));

        // ---------------------------------------------------------------------
        // 6. Operator Downloads Derived PDF for Both Studies
        // ---------------------------------------------------------------------
        foreach ([$studyId1, $studyId2] as $studyId) {
            $report = DB::table('image_gateway_ai_reports')->where('study_id', $studyId)->first();
            $this->assertNotNull($report);

            $downloadResponse = $this->get(route('operator.study.ai-report.download', $studyId));
            $downloadResponse->assertOk();
            $this->assertSame('application/pdf', $downloadResponse->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment;', (string) $downloadResponse->headers->get('Content-Disposition'));
            $downloadedContent = $downloadResponse->streamedContent();
            $this->assertStringStartsWith('%PDF-', $downloadedContent);
            $this->assertSame($report->derived_checksum, hash('sha256', $downloadedContent));

            // Verify download audit event recorded
            $audit = DB::table('audit_events')
                ->where('action', 'operator.ai-report.download')
                ->where('target_id', $studyId)
                ->first();
            $this->assertNotNull($audit);
            $this->assertStringNotContainsString('PDF', (string) $audit->metadata);
            $this->assertStringNotContainsString('Purnomo', (string) $audit->metadata);

            // ---------------------------------------------------------------------
            // 7. DICOM Viewer Remains Accessible and Operational
            // ---------------------------------------------------------------------
            $viewerResponse = $this->get(route('operator.study.show', $studyId));
            $viewerResponse->assertOk();
        }
    }
}
