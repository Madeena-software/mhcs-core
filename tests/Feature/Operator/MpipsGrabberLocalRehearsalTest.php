<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Shared\Security\ProtectedIdentifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class MpipsGrabberLocalRehearsalTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    private const string SYNTHETIC_DICOM_PREAMBLE = "DICM-SYNTHETIC-PART-10-HEADER-BYTES-LOCAL-REHEARSAL";

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
        RateLimiter::clear('grabber:manifest:total:*');
        RateLimiter::clear('grabber:manifest:failed:*');
        RateLimiter::clear('grabber:dicom:total:*');
        RateLimiter::clear('grabber:dicom:failed:*');
    }

    private function createDicomPart10Payload(string $extraData = 'rehearsal-payload-01'): string
    {
        return str_repeat("\0", 128).'DICM'.$extraData;
    }

    /**
     * @return array{
     *     fixture: array<string, mixed>,
     *     admission: object,
     *     locator: object,
     *     locator_code: string,
     *     grabber: array<string, mixed>,
     *     token: string
     * }
     */
    private function setupRehearsalSession(string $nik = '3171099900020002', string $patientName = 'Siti Walkin Rehearsal'): array
    {
        $fixture = $this->operatorFixture(administrator: true);
        $this->startOperatorSession($fixture);

        $memberRegPayload = [
            'name' => $patientName,
            'administrative_gender' => 'female',
            'nik' => $nik,
            'birth_date' => '1995-05-15',
            'phone' => '+6281234560000',
            'affiliation' => 'PT Rehearsal Sejahtera',
            'office_location' => 'Building B Floor 2',
        ];

        $regResult = app(\App\Modules\Operator\Application\Services\OperatorFieldOperationsService::class)->registerAndAdmitMember(
            $memberRegPayload,
            $fixture['scheduleId'],
            (string) Str::uuid()
        );

        $consentService = app(\App\Modules\Operator\Application\Services\OperatorReusableConsentService::class);
        $consentService->recordMasterConsent(
            $regResult['case_id'],
            'member',
            now()->toDateString(),
            (string) Str::uuid()
        );

        $checkInService = app(\App\Modules\Operator\Application\Services\OperatorCheckInTicketService::class);
        $ticketResult = $checkInService->issue(
            $regResult['case_id'],
            'R-'.Str::upper(Str::random(4)),
            (string) Str::uuid(),
            bypassBasicExamination: true
        );
        $ticketId = $ticketResult['ticket_id'];

        $admission = DB::table('operator_queue_admissions')
            ->where('operator_paper_ticket_id', $ticketId)
            ->where('stage', 'xray')
            ->first();
        $this->assertNotNull($admission);

        $locatorService = app(RadiographySessionLocatorService::class);
        $locator = $locatorService->allocate($admission->id, $fixture['siteLocalId'], $fixture['scheduleId']);

        $grabberService = app(GrabberClientService::class);
        $grabberId = 'GRABBER-REHEARSAL-'.Str::upper(Str::random(4));
        $grabber = $grabberService->create(
            $grabberId,
            'Local Rehearsal Grabber Client',
            $fixture['siteLocalId']
        );

        return [
            'fixture' => $fixture,
            'admission' => $admission,
            'locator' => $locator,
            'locator_code' => $locator->locator_code,
            'grabber' => $grabber,
            'token' => $grabber['raw_token'],
        ];
    }

    private function startOperatorSession(array $fixture): void
    {
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $permissions = [
            'operator.portal.access',
            'operator.shift.manage',
            'operator.arrival.record',
            'operator.identity.verify',
            'operator.consent.manage',
            'operator.check-in.issue',
            'operator.queue.manage',
            'operator.study.view',
        ];
        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->updateOrInsert(
                ['user_id' => $fixture['operator']->id, 'permission' => $permission],
                ['id' => (string) Str::uuid(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    // =========================================================================
    // 1. LOCAL SERVICE HEALTH & DISPOSABLE TEST DATABASE VERIFICATION
    // =========================================================================

    public function test_local_service_and_disposable_database_are_healthy(): void
    {
        $this->assertTrue(DB::connection()->getPdo() !== null);
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('operator_queue_admissions'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('radiography_session_locators'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('grabber_clients'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('image_gateway_studies'));
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('audit_events'));
    }

    // =========================================================================
    // 2. MANIFEST LOOKUP SUCCESS AND AUTHENTICATION FAILURE
    // =========================================================================

    public function test_manifest_lookup_succeeds_with_valid_token_and_filters_pii(): void
    {
        $s = $this->setupRehearsalSession();

        // Check both GET /api/v1/grabber/manifest/{code} and /radiography-sessions/{code}/manifest
        $responseA = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
        ])->getJson("/api/v1/grabber/manifest/{$s['locator_code']}");

        $responseA->assertOk()
            ->assertJsonPath('patient.name', 'Siti Walkin Rehearsal')
            ->assertJsonMissing(['nik' => '3171099900020002'])
            ->assertJsonMissing(['phone' => '+6281234560000']);

        $responseB = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
        ])->getJson("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/manifest");

        $responseB->assertOk()
            ->assertJsonPath('patient.name', 'Siti Walkin Rehearsal');
    }

    public function test_manifest_lookup_rejects_unauthenticated_and_invalid_clients(): void
    {
        $s = $this->setupRehearsalSession();

        // 1. Missing Authorization header -> 401
        $this->getJson("/api/v1/grabber/manifest/{$s['locator_code']}")
            ->assertStatus(401);

        // 2. Invalid bearer token -> 401
        $this->withHeaders(['Authorization' => 'Bearer invalid-token-123'])
            ->getJson("/api/v1/grabber/manifest/{$s['locator_code']}")
            ->assertStatus(401);

        // 3. Inactive grabber client -> 403
        $s['grabber']['client']->update(['status' => 'inactive']);
        $this->withHeaders(['Authorization' => 'Bearer '.$s['token']])
            ->getJson("/api/v1/grabber/manifest/{$s['locator_code']}")
            ->assertStatus(403);
    }

    // =========================================================================
    // 3. INITIAL UPLOAD 201 AND TERMINAL_STATE:AWAITING_AI
    // =========================================================================

    public function test_initial_dicom_upload_produces_201_awaiting_ai_and_replayed_false(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createDicomPart10Payload('unique-dicom-bytes-001');
        $file = UploadedFile::fake()->createWithContent('study_001.dcm', $dicomPayload);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => hash('sha256', $dicomPayload),
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('terminal_state', 'awaiting_ai')
            ->assertJsonPath('checksum', hash('sha256', $dicomPayload));

        $studyId = (string) $response->json('study_id');
        $this->assertNotEmpty($studyId);

        // Verify admission transitioned to awaiting_ai (server chose awaiting_ai, Grabber cannot choose completed)
        $admission = DB::table('operator_queue_admissions')->where('id', $s['admission']->id)->first();
        $this->assertSame('awaiting_ai', $admission->state);

        // Locator is marked completed
        $this->assertSame('completed', DB::table('radiography_session_locators')->where('id', $s['locator']->id)->value('status'));
    }

    // =========================================================================
    // 4. IDENTICAL RETRY YIELDS 200 AND REPLAYED:TRUE
    // =========================================================================

    public function test_identical_retry_produces_200_and_replayed_true(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createDicomPart10Payload('unique-dicom-bytes-retry-test');
        $checksum = hash('sha256', $dicomPayload);

        // Initial upload
        $firstResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload),
        ]);
        $firstResponse->assertStatus(201)->assertJsonPath('replayed', false);
        $studyId = $firstResponse->json('study_id');

        // Identical retry with same submission ID, checksum, and payload
        $retryResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload),
        ]);

        $retryResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('terminal_state', 'awaiting_ai')
            ->assertJsonPath('study_id', $studyId);
    }

    // =========================================================================
    // 5. IDEMPOTENCY CONFLICT WITH DIFFERENT PAYLOAD
    // =========================================================================

    public function test_idempotency_conflict_with_different_checksum_or_payload_yields_409(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();
        $payload1 = $this->createDicomPart10Payload('dicom-payload-version-1');
        $payload2 = $this->createDicomPart10Payload('dicom-payload-different-version-2');

        // Initial upload
        $firstResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => hash('sha256', $payload1),
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $payload1),
        ]);
        $firstResponse->assertStatus(201);

        // Conflicting upload with same submission ID but different checksum/payload
        $conflictResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => hash('sha256', $payload2),
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $payload2),
        ]);

        $conflictResponse->assertStatus(409)
            ->assertJsonPath('message', 'Idempotency conflict for submission ID.');
    }

    // =========================================================================
    // 6. NO DUPLICATE PRIVATE OBJECT, CAPTURE SET, STUDY, OR AUDIT EVENT
    // =========================================================================

    public function test_no_duplicate_objects_studies_or_events_on_replayed_upload(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createDicomPart10Payload('duplication-verification-bytes');
        $checksum = hash('sha256', $dicomPayload);

        // Count baselines
        $initialStudiesCount = DB::table('image_gateway_studies')->count();
        $initialCaptureSetsCount = DB::table('image_gateway_capture_sets')->count();
        $initialHistoryCount = DB::table('operator_queue_admission_history')->where('operator_queue_admission_id', $s['admission']->id)->count();

        // 1. Initial upload
        $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload),
        ])->assertStatus(201);

        $studiesAfterFirst = DB::table('image_gateway_studies')->count();
        $captureSetsAfterFirst = DB::table('image_gateway_capture_sets')->count();
        $auditEventsAfterFirst = DB::table('audit_events')->where('action', 'grabber.dicom.uploaded')->count();
        $historyAfterFirst = DB::table('operator_queue_admission_history')->where('operator_queue_admission_id', $s['admission']->id)->count();

        $this->assertSame($initialStudiesCount + 1, $studiesAfterFirst);
        $this->assertSame($initialCaptureSetsCount + 1, $captureSetsAfterFirst);
        $this->assertSame(1, $auditEventsAfterFirst);
        $this->assertSame($initialHistoryCount + 1, $historyAfterFirst);

        // 2. Replay upload
        $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
            'X-Checksum-SHA256' => $checksum,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload),
        ])->assertStatus(200);

        // Counts MUST remain exactly identical - zero duplicates!
        $this->assertSame($studiesAfterFirst, DB::table('image_gateway_studies')->count());
        $this->assertSame($captureSetsAfterFirst, DB::table('image_gateway_capture_sets')->count());
        $this->assertSame($auditEventsAfterFirst, DB::table('audit_events')->where('action', 'grabber.dicom.uploaded')->count());
        $this->assertSame($historyAfterFirst, DB::table('operator_queue_admission_history')->where('operator_queue_admission_id', $s['admission']->id)->count());
    }

    // =========================================================================
    // 7. PRIVATE OBJECT STORAGE INTEGRITY (NOT PUBLIC)
    // =========================================================================

    public function test_dicom_object_is_stored_in_private_object_store_not_public(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createDicomPart10Payload('private-storage-test-payload');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload),
        ]);
        $response->assertStatus(201);

        $studyId = (string) $response->json('study_id');
        $study = DB::table('image_gateway_studies')->where('id', $studyId)->first();
        $this->assertNotNull($study);

        // Object stored in private store, verified not present on public disk
        $this->assertTrue(Storage::disk('local')->exists((string) $study->object_key));
        $this->assertFalse(Storage::disk('public')->exists((string) $study->object_key));
    }

    // =========================================================================
    // 8. FAILURE DOES NOT FALSELY COMPLETE SESSION
    // =========================================================================

    public function test_corrupted_or_invalid_upload_does_not_falsely_complete_session(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();

        // Corrupt payload: preamble present but wrong magic bytes (not DICM)
        $corruptPayload = str_repeat("\0", 128).'NOPE'.'corrupt-content';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('corrupt.dcm', $corruptPayload),
        ]);

        $response->assertStatus(422);

        // Admission MUST remain in 'waiting' state, NOT 'completed' or 'awaiting_ai'
        $admission = DB::table('operator_queue_admissions')->where('id', $s['admission']->id)->first();
        $this->assertSame('waiting', $admission->state);

        // Locator MUST remain active
        $locator = DB::table('radiography_session_locators')->where('id', $s['locator']->id)->first();
        $this->assertSame('active', $locator->status);
    }

    // =========================================================================
    // 9. STUDY ACCESSIBLE VIA EXISTING DICOM VIEWER PATH
    // =========================================================================

    public function test_uploaded_study_is_accessible_via_existing_dicom_viewer_route(): void
    {
        $s = $this->setupRehearsalSession();
        $submissionId = (string) Str::uuid();
        $dicomPayload = $this->createDicomPart10Payload('viewer-accessibility-payload');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$s['token'],
            'X-Submission-ID' => $submissionId,
        ])->post("/api/v1/grabber/radiography-sessions/{$s['locator_code']}/dicom", [
            'file' => UploadedFile::fake()->createWithContent('study.dcm', $dicomPayload),
        ]);
        $response->assertStatus(201);

        $studyId = (string) $response->json('study_id');
        $displayRef = (string) $response->json('display_reference');

        // Access study through existing DICOM viewer route as logged-in Operator
        $viewerResponse = $this->get(route('operator.study.show', $studyId));
        $viewerResponse->assertOk()
            ->assertSee($displayRef)
            ->assertSee('VOI otomatis');
    }

    // =========================================================================
    // 10. NPZ UPLOAD PIPELINE AND OPERATOR FLOW PRESERVATION
    // =========================================================================

    public function test_legacy_npz_upload_pipeline_and_operator_flow_are_preserved(): void
    {
        Queue::fake([ProcessCaptureSet::class]);

        $fixture = $this->operatorFixture(administrator: true);
        $this->startOperatorSession($fixture);

        $now = now();
        $ticketId = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $fixture['bookingId'],
            'ticket_number' => 'NPZ-001',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $admissionId = (string) Str::uuid();
        DB::table('operator_queue_admissions')->insert([
            'id' => $admissionId,
            'operator_paper_ticket_id' => $ticketId,
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

        $npzSubmissionId = (string) Str::uuid();
        $npzResponse = $this->post(route('operator.xray-capture.store', $admissionId), [
            'submission_id' => $npzSubmissionId,
            'metadata' => [
                'examination' => [
                    'study_description' => 'CHEST RADIOGRAPH REHEARSAL',
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
        ]);

        $npzResponse->assertRedirect(route('operator.study.results'));
        Queue::assertPushed(ProcessCaptureSet::class);
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
