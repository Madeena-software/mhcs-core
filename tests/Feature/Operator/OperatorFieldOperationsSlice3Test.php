<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\OperatorWorklistService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Modules\Operator\Domain\Models\GrabberClient;
use App\Modules\Operator\Domain\Models\RadiographySessionLocator;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorFieldOperationsSlice3Test extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.security.asset_grants' => [
                'max_ttl_seconds' => 300,
                'audiences' => ['operator-identity', 'operator-study'],
            ],
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.security.manifest_key' => str_repeat('m', 32),
            'mhcs.security.manifest_key_id' => 'test-key',
            'mhcs.mpips.base_url' => 'http://127.0.0.1:8014',
            'mhcs.mpips.api_key' => 'test-api-key',
            'mhcs.upload.max_file_bytes' => 104857600,
        ]);
        Storage::fake('local');
        RateLimiter::clear('grabber:dicom:total:*');
        RateLimiter::clear('grabber:dicom:failed:*');
    }

    /**
     * Helper to create a valid synthetic Part 10 DICOM binary string.
     */
    private function createSyntheticDicom(string $content = 'test-dicom-payload'): string
    {
        return str_repeat("\0", 128).'DICM'.$content;
    }

    /**
     * Helper to set up an active radiography session admission with a 4-digit locator.
     *
     * @return array{
     *     fixture: array<string, mixed>,
     *     admissionId: string,
     *     ticketId: string,
     *     locator: RadiographySessionLocator,
     *     grabberClient: GrabberClient,
     *     rawToken: string
     * }
     */
    private function createActiveRadiographySessionWithGrabber(string $nik = '900000000088'): array
    {
        $fixture = $this->operatorFixture(administrator: true, nik: $nik);

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

        $locatorService = app(RadiographySessionLocatorService::class);
        $locator = $locatorService->allocate(
            $admissionId,
            $fixture['siteLocalId'],
            $fixture['scheduleId'],
        );

        $grabberService = app(GrabberClientService::class);
        $created = $grabberService->create(
            'GRABBER-DDR-'.Str::upper(Str::random(4)),
            'Test Grabber',
            $fixture['siteLocalId'],
        );

        return [
            'fixture' => $fixture,
            'admissionId' => $admissionId,
            'ticketId' => $ticketId,
            'locator' => $locator,
            'grabberClient' => $created['client'],
            'rawToken' => $created['raw_token'],
        ];
    }

    // =========================================================================
    // 1. VALID DICOM UPLOAD & BINDING
    // =========================================================================

    public function test_authenticated_grabber_can_upload_valid_dicom_multipart(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('multipart-dicom-study');
        $checksum = hash('sha256', $dicomPayload);

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'admission_id' => $session['admissionId'],
            'locator_code' => $code,
            'terminal_state' => 'awaiting_ai',
            'checksum' => $checksum,
            'bytes' => strlen($dicomPayload),
            'replayed' => false,
        ]);
        $this->assertNotNull($response->json('study_id'));
        $this->assertStringStartsWith('DCM-', (string) $response->json('display_reference'));

        // Verify database persistence
        $study = DB::table('image_gateway_studies')->where('id', $response->json('study_id'))->first();
        $this->assertNotNull($study);
        $this->assertSame($checksum, $study->checksum);
        $this->assertSame(strlen($dicomPayload), (int) $study->bytes);
        $this->assertSame('application/dicom', $study->format);

        // Verify queue admission transition to awaiting_ai
        $admission = DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->first();
        $this->assertSame('awaiting_ai', $admission->state);
        $this->assertNull($admission->operator_profile_id);

        // Verify locator code invalidated (completed)
        $locator = DB::table('radiography_session_locators')->where('id', $session['locator']->id)->first();
        $this->assertSame('completed', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertNotNull($locator->invalidated_at);

        // Verify admission history recorded
        $history = DB::table('operator_queue_admission_history')
            ->where('operator_queue_admission_id', $session['admissionId'])
            ->where('event_type', 'dicom_ingested')
            ->first();
        $this->assertNotNull($history);
        $this->assertSame('waiting', $history->from_state);
        $this->assertSame('awaiting_ai', $history->to_state);
        $this->assertSame($submissionId, $history->operation_id);

        // Verify audit event and outbox message
        $audit = DB::table('audit_events')
            ->where('action', 'grabber.dicom.uploaded')
            ->where('target_id', $session['admissionId'])
            ->first();
        $this->assertNotNull($audit);

        $outbox = DB::table('outbox_messages')
            ->where('event_name', 'grabber-dicom-uploaded')
            ->where('subject_id', $response->json('study_id'))
            ->first();
        $this->assertNotNull($outbox);
    }

    public function test_authenticated_grabber_can_upload_valid_dicom_raw_binary(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('raw-binary-dicom-data');
        $checksum = hash('sha256', $dicomPayload);

        $server = $this->transformHeadersToServerVars([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
            'Content-Type' => 'application/dicom',
        ]);

        $response = $this->call(
            'POST',
            "/api/v1/grabber/radiography-sessions/{$code}/dicom",
            [],
            [],
            [],
            $server,
            $dicomPayload,
        );

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame($session['admissionId'], $data['admission_id']);
        $this->assertSame($checksum, $data['checksum']);
    }

    public function test_custom_terminal_state_completed_is_supported(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('completed-terminal-state');

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
            'X-Terminal-State' => 'completed',
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertSame('completed', $response->json('terminal_state'));

        $admission = DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->first();
        $this->assertSame('completed', $admission->state);
    }

    // =========================================================================
    // 2. AUTHENTICATION & AUTHORIZATION REJECTION
    // =========================================================================

    public function test_rejects_unauthenticated_requests(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $response = $this->postJson("/api/v1/grabber/radiography-sessions/{$code}/dicom");

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_rejects_invalid_token(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-value',
            'X-Submission-ID' => (string) Str::uuid(),
        ])->postJson("/api/v1/grabber/radiography-sessions/{$code}/dicom");

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_rejects_mismatched_grabber_id_header(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Grabber-ID' => 'DIFFERENT-GRABBER-ID',
            'X-Submission-ID' => (string) Str::uuid(),
        ])->postJson("/api/v1/grabber/radiography-sessions/{$code}/dicom");

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_rejects_inactive_grabber_client(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        // Deactivate client
        $session['grabberClient']->update(['status' => 'inactive']);

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_rejects_cross_site_upload_attempts(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        // Pass foreign X-Site-ID
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
            'X-Site-ID' => '01918000-0000-7000-8000-foreignsite01',
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden.']);
    }

    // =========================================================================
    // 3. SHIFT, SESSION SCOPING & ANTI-ENUMERATION
    // =========================================================================

    public function test_rejects_cross_shift_with_anti_enumeration_404(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
            'X-Shift-ID' => '01918000-0000-7000-8000-foreignshift01',
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_rejects_non_existent_locator_code(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post('/api/v1/grabber/radiography-sessions/0000/dicom', [
            'file' => $file,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_rejects_invalid_locator_format(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post('/api/v1/grabber/radiography-sessions/abc/dicom', [
            'file' => $file,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_rejects_expired_locator_code(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        // Invalidate locator code
        app(RadiographySessionLocatorService::class)->markCompleted($session['admissionId']);

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    // =========================================================================
    // 4. VALIDATION & INTEGRITY CHECKS
    // =========================================================================

    public function test_rejects_missing_submission_id(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Client submission identity is required.']);
    }

    public function test_rejects_empty_payload(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'No DICOM file or payload provided.']);
    }

    public function test_rejects_invalid_dicom_magic_bytes(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        // Corrupted payload: 128 bytes preamble but followed by 'NOPE' instead of 'DICM'
        $corruptPayload = str_repeat("\0", 128).'NOPE'.'corrupt-content';
        $file = UploadedFile::fake()->createWithContent('corrupt.dcm', $corruptPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Invalid DICOM file or magic bytes.']);
    }

    public function test_rejects_checksum_mismatch(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $dicomPayload = $this->createSyntheticDicom();

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
            'X-Checksum-SHA256' => '0000000000000000000000000000000000000000000000000000000000000000',
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Checksum does not match upload contents.']);
    }

    public function test_rejects_patient_mrn_mismatch_if_specified(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $dicomPayload = $this->createSyntheticDicom();

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
            'X-Patient-MRN' => 'MRN-WRONG-99999',
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Patient MRN does not match active session.']);
    }

    // =========================================================================
    // 5. NO FALSE COMPLETION AFTER FAILED UPLOAD ATTEMPTS
    // =========================================================================

    public function test_failed_upload_leaves_session_waiting_and_locator_active(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        // Attempt upload with bad checksum
        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
            'X-Checksum-SHA256' => 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(422);

        // Verification: Session MUST still be waiting, NEVER falsely completed!
        $admission = DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->first();
        $this->assertSame('waiting', $admission->state);

        // Verification: Locator code MUST still be active!
        $locator = DB::table('radiography_session_locators')->where('id', $session['locator']->id)->first();
        $this->assertSame('active', $locator->status);
        $this->assertNotNull($locator->active_key);

        // Verification: Zero studies created
        $this->assertDatabaseMissing('image_gateway_studies', [
            'checksum' => hash('sha256', $this->createSyntheticDicom()),
        ]);

        // Subsequent valid upload MUST succeed!
        $validSubmissionId = (string) Str::uuid();
        $validFile = UploadedFile::fake()->createWithContent('valid.dcm', $this->createSyntheticDicom('subsequent-valid'));
        $validChecksum = hash('sha256', $this->createSyntheticDicom('subsequent-valid'));

        $retryResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $validSubmissionId,
            'X-Checksum-SHA256' => $validChecksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $validFile,
        ]);

        $retryResponse->assertStatus(201);
        $this->assertSame('success', $retryResponse->json('status'));
    }

    // =========================================================================
    // 6. IDEMPOTENCY & DUPLICATE SUPPRESSION
    // =========================================================================

    public function test_exact_submission_retry_is_idempotent_and_replayed_without_duplicates(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = 'SUB-IDEMPOTENT-001';
        $dicomPayload = $this->createSyntheticDicom('idempotent-study-payload');
        $checksum = hash('sha256', $dicomPayload);

        $file1 = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        // First attempt: 201 Created, replayed: false
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file1,
        ]);

        $res1->assertStatus(201);
        $this->assertFalse($res1->json('replayed'));
        $studyId1 = $res1->json('study_id');
        $ref1 = $res1->json('display_reference');

        // Immediate retry with exact same submission ID and payload: 200 OK, replayed: true
        $file2 = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file2,
        ]);

        $res2->assertStatus(200);
        $this->assertTrue($res2->json('replayed'));
        $this->assertSame($studyId1, $res2->json('study_id'));
        $this->assertSame($ref1, $res2->json('display_reference'));

        // Assert no duplicate records in database
        $this->assertSame(1, DB::table('image_gateway_studies')->count());
        $this->assertSame(1, DB::table('image_gateway_capture_sets')->count());
        $this->assertSame(1, DB::table('operator_queue_admission_history')->where('event_type', 'dicom_ingested')->count());
    }

    public function test_reusing_submission_id_with_differing_payload_triggers_conflict(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = 'SUB-CONFLICT-001';

        $payload1 = $this->createSyntheticDicom('payload-one');
        $file1 = UploadedFile::fake()->createWithContent('study1.dcm', $payload1);

        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file1,
        ]);

        $res1->assertStatus(201);

        // Attempt reuse with different payload
        $payload2 = $this->createSyntheticDicom('payload-two-different');
        $file2 = UploadedFile::fake()->createWithContent('study2.dcm', $payload2);

        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file2,
        ]);

        $res2->assertStatus(409);
        $res2->assertJson(['message' => 'Idempotency conflict for submission ID.']);
    }

    // =========================================================================
    // 7. PRIVATE OBJECT STORAGE VERIFICATION
    // =========================================================================

    public function test_stored_dicom_is_securely_retrievable_via_private_object_store(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $dicomPayload = $this->createSyntheticDicom('private-storage-verification-study');
        $checksum = hash('sha256', $dicomPayload);

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $studyId = $response->json('study_id');

        $study = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($study);

        // Retrieve object via PrivateObjectStore
        $store = app(PrivateObjectStore::class);
        $object = new PrivateObject(
            OpaqueObjectKey::fromString((string) $study->object_key),
            (string) $study->checksum,
            (int) $study->bytes,
            new DateTimeImmutable((string) $study->created_at),
        );

        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $session['fixture']['operator']->id),
            operationId: CorrelationId::random(),
            purpose: ImageGatewayCaptureService::STUDY_PURPOSE,
        );

        $grant = $store->grant(
            $object,
            $context,
            'operator-study',
            ImageGatewayCaptureService::STUDY_PURPOSE,
            now()->addMinutes(5)->toDateTimeImmutable(),
        );

        $retrievedBytes = $store->get($grant, $context, 'operator-study', ImageGatewayCaptureService::STUDY_PURPOSE);
        $this->assertSame($dicomPayload, $retrievedBytes);
        $this->assertSame($checksum, hash('sha256', $retrievedBytes));
    }

    // =========================================================================
    // 8. ALTERNATIVE ENDPOINTS & HEADERS
    // =========================================================================

    public function test_upload_via_dicom_upload_endpoint_with_body_locator_code(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('body-code-dicom');

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
        ])->post('/api/v1/grabber/dicom/upload', [
            'locator_code' => $code,
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertSame('success', $response->json('status'));
        $this->assertSame($session['admissionId'], $response->json('admission_id'));
    }

    public function test_upload_via_universal_upload_endpoint_with_header_locator_code(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('universal-upload-dicom');

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
            'X-Locator-Code' => $code,
        ])->post('/api/v1/grabber/upload', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
        $this->assertSame('success', $response->json('status'));
    }

    // =========================================================================
    // 9. WORKLIST QUEUE TRANSITION & OPERATOR STUDY QUERY REGRESSION
    // =========================================================================

    public function test_ingested_session_leaves_xray_readiness_queue(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        // Verify session appears in worklist initially
        $this->actingAs($session['fixture']['operator']);
        $this->withSession(['operator.active_site_id' => $session['fixture']['siteLocalId']]);
        $worklistService = app(OperatorWorklistService::class);

        $admissionsBefore = $worklistService->xrayReadiness();
        $this->assertTrue(collect($admissionsBefore)->contains('admission_id', $session['admissionId']));

        // Perform Grabber DICOM upload
        $file = UploadedFile::fake()->createWithContent('study.dcm', $this->createSyntheticDicom());
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);
        $response->assertStatus(201);

        // Verification: Session MUST leave the xray readiness waiting queue
        $admissionsAfter = $worklistService->xrayReadiness();
        $this->assertFalse(collect($admissionsAfter)->contains('admission_id', $session['admissionId']));
    }

    public function test_different_submission_to_already_completed_session_is_rejected_with_404_anti_enumeration(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;

        // Complete the session with first submission
        $file1 = UploadedFile::fake()->createWithContent('study1.dcm', $this->createSyntheticDicom('first-upload'));
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file1,
        ]);
        $res1->assertStatus(201);

        // Attempting to upload to the completed session with a NEW submission ID must be rejected
        // because locator code is now completed/invalidated (anti-enumeration response)
        $file2 = UploadedFile::fake()->createWithContent('study2.dcm', $this->createSyntheticDicom('second-upload'));
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file2,
        ]);

        $res2->assertStatus(404);
        $res2->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_stored_dicom_is_queryable_by_operator_study_query(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $code = $session['locator']->locator_code;
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createSyntheticDicom('queryable-study');

        $file = UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload);
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $submissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $file,
        ]);
        $response->assertStatus(201);
        $studyId = (string) $response->json('study_id');

        // Grant operator permissions
        $this->actingAs($session['fixture']['operator']);
        $this->withSession(['operator.active_site_id' => $session['fixture']['siteLocalId']]);

        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $session['fixture']['operator']->id),
            operationId: CorrelationId::random(),
            purpose: ImageGatewayCaptureService::STUDY_PURPOSE,
        );

        $studyService = app(ImageGatewayCaptureService::class);
        $studies = $studyService->studies(
            $context,
            $session['fixture']['profileId'],
            $session['fixture']['siteLocalId'],
            $session['fixture']['siteStableId'],
        );

        $this->assertNotEmpty($studies);
        $matching = collect($studies)->firstWhere('study_id', $studyId);
        $this->assertNotNull($matching);
        $this->assertSame('application/dicom', $matching['format']);

        $retrievedBytes = $studyService->dicom(
            $context,
            $session['fixture']['profileId'],
            $session['fixture']['siteLocalId'],
            $session['fixture']['siteStableId'],
            $studyId,
        );
        $this->assertSame($dicomPayload, $retrievedBytes);
    }

    public function test_dual_pathway_coexistence_npz_and_direct_dicom_in_same_shift(): void
    {
        $session = $this->createActiveRadiographySessionWithGrabber();
        $fixture = $session['fixture'];
        $code = $session['locator']->locator_code;

        // --- Pathway 1: Legacy NPZ Upload for Admission A ---
        $ticketIdA = (string) Str::uuid();
        $admissionIdA = (string) Str::uuid();
        $now = now();

        $memberIdA = (string) Str::uuid();
        $userA = User::factory()->create(['email' => 'member-npz-'.Str::lower(Str::random(6)).'@example.test']);
        $protectedA = app(ProtectedIdentifierService::class)->protect('900000000099');
        DB::table('members')->insert([
            'id' => $memberIdA,
            'user_id' => $userA->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.substr($memberIdA, 0, 8),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protectedA['encrypted_display'],
            'nik_lookup_digest' => $protectedA['lookup_digest'],
            'name' => 'Member A Legacy NPZ',
            'birth_date' => '1990-01-01',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bookingIdA = (string) Str::uuid();
        $existingBooking = DB::table('bookings')->where('id', $fixture['bookingId'])->first();
        $rateId = (string) $existingBooking->point_exchange_rate_id;
        DB::table('bookings')->insert([
            'id' => $bookingIdA,
            'member_id' => $memberIdA,
            'shift_schedule_id' => $fixture['scheduleId'],
            'service_offering_id' => $fixture['serviceId'],
            'examination_site_id_snapshot' => $fixture['siteReferenceId'],
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => 'RAD-01',
            'point_cost_snapshot' => '2.5000',
            'point_exchange_rate_id' => $rateId,
            'includes_ai_snapshot' => true,
            'includes_doctor_snapshot' => false,
            'site_code_snapshot' => 'SITE-01',
            'site_name_snapshot' => 'Site 01',
            'site_timezone_snapshot' => 'Asia/Jakarta',
            'created_at' => $now,
            'confirmed_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketIdA,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $bookingIdA,
            'ticket_number' => '102',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionIdA,
            'operator_paper_ticket_id' => $ticketIdA,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'queue_class' => 'advance',
            'stage' => 'xray',
            'state' => 'called',
            'operator_profile_id' => $fixture['profileId'],
            'claimed_at' => $now,
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        Queue::fake([ProcessCaptureSet::class]);
        Http::fake([
            '*' => Http::response(
                str_repeat("\0", 128).'DICM'.'valid mpips dicom payload',
                200,
                [
                    'Content-Type' => 'application/dicom',
                    'X-Conversion-Job-ID' => '6ba7b810-9dad-51d1-80b4-00c04fd430c8',
                    'X-Correlation-ID' => '6ba7b810-9dad-41d1-80b4-00c04fd430c8',
                ],
            ),
        ]);

        $this->post(route('operator.xray-capture.store', $admissionIdA), [
            'submission_id' => (string) Str::uuid(),
            'metadata' => [
                'examination' => [
                    'study_description' => 'CHEST RADIOGRAPH',
                ],
                'capture' => [
                    'detector_type' => 'BED',
                    'body_part_examined' => 'CHEST',
                    'laterality' => 'U',
                    'projection' => 'PA',
                ],
            ],
            'radiograph_npz' => $this->fixtureUpload('synthetic-radiograph-01.npz'),
            'gain_npz' => $this->fixtureUpload('synthetic-gain-01.npz'),
        ])->assertRedirect(route('operator.study.results'));

        $captureIdA = (string) DB::table('image_gateway_capture_sets')->where('admission_id', $admissionIdA)->value('id');
        app()->call([new ProcessCaptureSet($captureIdA), 'handle']);

        // Assert Pathway 1 produced a study
        $this->assertSame(1, DB::table('image_gateway_studies')->where('capture_set_id', $captureIdA)->count());

        // --- Pathway 2: DDR Direct DICOM Upload for Admission B ---
        $dicomSubmissionId = (string) Str::uuid();
        $directDicomPayload = $this->createSyntheticDicom('ddr-direct-grabber-dicom');
        $fileB = UploadedFile::fake()->createWithContent('direct.dcm', $directDicomPayload);

        $responseB = $this->withHeaders([
            'Authorization' => 'Bearer '.$session['rawToken'],
            'X-Submission-ID' => $dicomSubmissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$code}/dicom", [
            'file' => $fileB,
        ]);
        $responseB->assertStatus(201);
        $studyIdB = (string) $responseB->json('study_id');

        // --- Coexistence Assertions ---
        // Total studies in database is exactly 2 (one from NPZ, one from direct DICOM)
        $this->assertSame(2, DB::table('image_gateway_studies')->count());

        // Query studies via operator study query
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            purpose: ImageGatewayCaptureService::STUDY_PURPOSE,
        );

        $studyService = app(ImageGatewayCaptureService::class);
        $allStudies = $studyService->studies(
            $context,
            $fixture['profileId'],
            $fixture['siteLocalId'],
            $fixture['siteStableId'],
        );

        $this->assertCount(2, $allStudies);
        $studyIds = collect($allStudies)->pluck('study_id')->all();
        $this->assertContains($studyIdB, $studyIds);

        // Both DICOM payloads are retrievable
        $retrievedDirect = $studyService->dicom(
            $context,
            $fixture['profileId'],
            $fixture['siteLocalId'],
            $fixture['siteStableId'],
            $studyIdB,
        );
        $this->assertSame($directDicomPayload, $retrievedDirect);
    }

    private function fixtureUpload(string $name): UploadedFile
    {
        return new UploadedFile(
            base_path('resources/fixtures/image-gateway/'.$name),
            $name,
            'application/octet-stream',
            null,
            true,
        );
    }
}
