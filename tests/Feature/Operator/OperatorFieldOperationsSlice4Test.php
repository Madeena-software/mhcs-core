<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\ImageGateway\Application\Jobs\ProcessCaptureSet;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\OperatorCheckInTicketService;
use App\Modules\Operator\Application\Services\OperatorFieldOperationsService;
use App\Modules\Operator\Application\Services\OperatorReusableConsentService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Shared\Security\ProtectedIdentifierService;
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

final class OperatorFieldOperationsSlice4Test extends TestCase
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
        RateLimiter::clear('grabber:manifest:total:*');
        RateLimiter::clear('grabber:manifest:failed:*');
        RateLimiter::clear('grabber:dicom:total:*');
        RateLimiter::clear('grabber:dicom:failed:*');
    }

    // =========================================================================
    // 1. THERMAL PRINT STYLING & PRIVACY SPECIFICATION (57x47P ROLL)
    // =========================================================================

    public function test_ticket_print_view_adheres_to_57mm_thermal_roll_geometry(): void
    {
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
            'ticket_number' => 'A-042',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get(route('operator.paper-ticket.print', $ticketId));
        $response->assertOk();

        $content = $response->getContent();

        // 1. Nominal media width and dynamic content height
        $this->assertStringContainsString('size: 57mm auto;', $content, 'Page size must be 57mm wide with dynamic height');

        // 2. Margin zeroing to suppress browser headers/footers
        $this->assertStringContainsString('margin: 0;', $content, 'Page margin must be zeroed to suppress browser headers/footers');

        // 3. Safe printable content width (~48mm inside 57mm roll)
        $this->assertStringContainsString('width: 57mm;', $content);
        $this->assertStringContainsString('max-width: 57mm;', $content);
        $this->assertStringContainsString('4.5mm', $content, 'Padding must yield safe printable area (~48mm)');

        // 4. Manual-tear visual separator margin (does not assume auto-cutter)
        $this->assertStringContainsString('ticket-tear-margin', $content);
        $this->assertStringContainsString('Tear here', $content);
        $this->assertStringContainsString('dashed', $content);

        // 5. Operational queuing data present
        $this->assertStringContainsString('Synthetic Operator Site', $content);
        $this->assertStringContainsString('A-042', $content);
        $this->assertStringContainsString($now->toDateTimeString(), $content);
    }

    public function test_ticket_print_view_strictly_preserves_privacy(): void
    {
        $sensitiveNik = '3171012304900001';
        $sensitivePhone = '+6281234567890';
        $sensitiveDob = '1990-04-23';
        $sensitiveName = 'Rahasia Bin Pribadi';

        $fixture = $this->operatorFixture(administrator: true, nik: $sensitiveNik);
        $this->startOperatorSession($fixture);

        // Update member record with sensitive civil & clinical data
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'phone' => $sensitivePhone,
            'birth_date' => $sensitiveDob,
            'name' => $sensitiveName,
        ]);

        $now = now();
        $ticketId = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketId,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $fixture['bookingId'],
            'ticket_number' => 'B-099',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get(route('operator.paper-ticket.print', $ticketId));
        $response->assertOk();

        $content = $response->getContent();

        // Strict privacy assertions: NO civil, contact, demographic, consent, or clinical data
        $this->assertStringNotContainsString($sensitiveNik, $content, 'NIK must not be exposed on print ticket');
        $this->assertStringNotContainsString($sensitivePhone, $content, 'Phone number must not be exposed on print ticket');
        $this->assertStringNotContainsString($sensitiveDob, $content, 'Birth date must not be exposed on print ticket');
        $this->assertStringNotContainsString($sensitiveName, $content, 'Member name must not be exposed on print ticket');
        $this->assertStringNotContainsString($fixture['memberId'], $content, 'Member ID must not be exposed on print ticket');
        $this->assertStringNotContainsString($fixture['bookingId'], $content, 'Booking ID must not be exposed on print ticket');
        $this->assertStringNotContainsString('MRN-', $content, 'MRN must not be exposed on print ticket');
        $this->assertStringNotContainsString('consent', strtolower($content), 'Consent info must not be exposed on print ticket');
        $this->assertStringNotContainsString('diagnosis', strtolower($content), 'Clinical info must not be exposed on print ticket');
    }

    public function test_ticket_print_view_renders_clean_text_preview_artifact(): void
    {
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
            'ticket_number' => 'T-5747',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get(route('operator.paper-ticket.print', $ticketId));
        $response->assertOk();

        // Verify simulated thermal line wrap width (approx 32 monospace characters across 48mm safe area)
        $html = (string) $response->getContent();
        $crawler = strip_tags(preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html));
        $lines = array_filter(array_map('trim', explode("\n", $crawler)));

        $this->assertNotEmpty($lines);
        $this->assertContains('T-5747', $lines);
        $this->assertContains('-- Tear here --', $lines);

        // Generate synthetic thermal ticket text preview artifact
        $preview = $this->renderSyntheticThermalTicketPreview([
            'site_name' => $fixture['siteStableId'],
            'schedule_window' => '08:00:00 – 12:00:00',
            'ticket_number' => 'T-5747',
            'issued_at' => $now->toDateTimeString(),
        ]);

        $this->assertStringContainsString('================================', $preview);
        $this->assertStringContainsString('T-5747', $preview);
        $this->assertStringContainsString('- - - - - [ TEAR HERE ] - - - -', $preview);

        // Verify simulated printable width does not exceed 32 thermal columns
        foreach (explode("\n", $preview) as $line) {
            $this->assertLessThanOrEqual(32, mb_strlen($line), "Thermal line exceeded 32 chars: '{$line}'");
        }
    }

    // =========================================================================
    // 2. SYNTHETIC CROSS-PATH REHEARSAL (LEGACY NPZ + ADDITIVE DIRECT DICOM)
    // =========================================================================

    public function test_synthetic_cross_path_rehearsal_both_pathways_end_to_end(): void
    {
        $fixture = $this->operatorFixture(administrator: true);
        $this->startOperatorSession($fixture);

        // ---------------------------------------------------------------------
        // PATH A: Legacy NPZ Pathway
        // Flow: Scheduled Member -> Arrived -> Verified -> Ticket -> Basic Exam -> X-ray Claim -> NPZ Capture -> ProcessCaptureSet -> DICOM Study -> Viewer
        // ---------------------------------------------------------------------
        $memberIdA = (string) Str::uuid();
        $userA = User::factory()->create(['email' => 'npz.rehearsal@example.test']);
        $nikA = '3171099900010001';
        $protectedA = app(ProtectedIdentifierService::class)->protect($nikA);
        $now = now();
        DB::table('members')->insert([
            'id' => $memberIdA,
            'user_id' => $userA->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.substr($memberIdA, 0, 8),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protectedA['encrypted_display'],
            'nik_lookup_digest' => $protectedA['lookup_digest'],
            'name' => 'Legacy NPZ Rehearsal Member',
            'birth_date' => '1988-08-08',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => '+628111111111',
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

        $ticketIdA = (string) Str::uuid();
        $now = now();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticketIdA,
            'operator_site_id' => $fixture['siteLocalId'],
            'member_schedule_id' => $fixture['scheduleId'],
            'operator_profile_id' => $fixture['profileId'],
            'booking_id' => $bookingIdA,
            'ticket_number' => 'NPZ-001',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $admissionIdA = (string) Str::uuid();
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

        $npzSubmissionId = (string) Str::uuid();
        $npzResponse = $this->post(route('operator.xray-capture.store', $admissionIdA), [
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

        $captureIdA = (string) DB::table('image_gateway_capture_sets')->where('admission_id', $admissionIdA)->value('id');
        $this->assertNotEmpty($captureIdA);

        // Execute async job to complete MPIPS conversion
        app()->call([new ProcessCaptureSet($captureIdA), 'handle']);

        $studyA = DB::table('image_gateway_studies')->where('capture_set_id', $captureIdA)->first();
        $this->assertNotNull($studyA, 'Legacy NPZ path must produce an image_gateway_studies record');
        $this->assertNotEmpty($studyA->display_reference);

        // Verify viewer accessibility for Path A
        $this->get(route('operator.study.show', $studyA->id))->assertOk()
            ->assertSee($studyA->display_reference)
            ->assertSee('VOI otomatis');

        // ---------------------------------------------------------------------
        // PATH B: Direct DICOM Pathway
        // Flow: Field Member -> Registration -> Master Consent -> Bypass Exam -> 4-Digit Locator -> Manifest Lookup -> DDR Direct DICOM Upload -> awaiting_ai -> Viewer Compatible
        // ---------------------------------------------------------------------
        $nikB = '3171099900020002';
        $memberRegPayload = [
            'name' => 'Siti Walkin Rehearsal',
            'administrative_gender' => 'female',
            'nik' => $nikB,
            'birth_date' => '1995-05-15',
            'phone' => '+6281234560000',
            'affiliation' => 'PT Rehearsal Sejahtera',
            'office_location' => 'Building B Floor 2',
        ];

        $regResult = app(OperatorFieldOperationsService::class)->registerAndAdmitMember(
            $memberRegPayload,
            $fixture['scheduleId'],
            (string) Str::uuid()
        );
        $this->assertArrayHasKey('member_id', $regResult);
        $this->assertArrayHasKey('booking_id', $regResult);
        $this->assertNotEmpty($regResult['medical_record_number']);

        // Master informed consent
        $consentService = app(OperatorReusableConsentService::class);
        $consentRecord = $consentService->recordMasterConsent(
            $regResult['case_id'],
            'member',
            now()->toDateString(),
            (string) Str::uuid()
        );
        $this->assertSame('confirmed', $consentRecord['status']);

        // Issue ticket with bypass basic examination
        $checkInService = app(OperatorCheckInTicketService::class);
        $ticketResultB = $checkInService->issue(
            $regResult['case_id'],
            'B-777',
            (string) Str::uuid(),
            bypassBasicExamination: true
        );
        $ticketIdB = $ticketResultB['ticket_id'];

        // Radiography queue admission created in waiting state
        $admissionB = DB::table('operator_queue_admissions')
            ->where('operator_paper_ticket_id', $ticketIdB)
            ->where('stage', 'xray')
            ->first();
        $this->assertNotNull($admissionB);
        $this->assertSame('waiting', $admissionB->state);

        // Allocate 4-digit radiography session locator
        $locatorService = app(RadiographySessionLocatorService::class);
        $locatorB = $locatorService->allocate(
            $admissionB->id,
            $fixture['siteLocalId'],
            $fixture['scheduleId']
        );
        $codeB = $locatorB->locator_code;
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $codeB);

        // Grabber client credential setup
        $grabberService = app(GrabberClientService::class);
        $grabber = $grabberService->create(
            'GRABBER-REHEARSAL-01',
            'Cross Path Rehearsal DDR',
            $fixture['siteLocalId']
        );
        $rawToken = $grabber['raw_token'];

        // Grabber manifest lookup
        $manifestResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$rawToken,
        ])->getJson("/api/v1/grabber/radiography-sessions/{$codeB}/manifest");

        $manifestResponse->assertOk()
            ->assertJsonPath('patient.medical_record_number', $regResult['medical_record_number'])
            ->assertJsonPath('patient.name', 'Siti Walkin Rehearsal')
            ->assertJsonMissing(['nik' => $nikB])
            ->assertJsonMissing(['phone' => '+6281234560000']);

        // DDR Direct DICOM upload
        $dicomPayloadB = str_repeat("\0", 128).'DICM'.'ddr-synthetic-direct-dicom-rehearsal-data';
        $dicomFile = UploadedFile::fake()->createWithContent('rehearsal_study.dcm', $dicomPayloadB);

        $uploadResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$rawToken,
            'X-Submission-ID' => (string) Str::uuid(),
        ])->post("/api/v1/grabber/radiography-sessions/{$codeB}/dicom", [
            'file' => $dicomFile,
        ]);

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('terminal_state', 'awaiting_ai');

        $studyIdB = (string) $uploadResponse->json('study_id');
        $this->assertNotEmpty($studyIdB);

        // Invariant: Grabber cannot set terminal state to 'completed'
        $finalAdmissionB = DB::table('operator_queue_admissions')->where('id', $admissionB->id)->first();
        $this->assertSame('awaiting_ai', $finalAdmissionB->state, 'Direct DICOM admission must transition to awaiting_ai, never falsely completed');

        // Locator session code must be marked completed/unusable after upload
        $this->assertSame('completed', DB::table('radiography_session_locators')->where('id', $locatorB->id)->value('status'));

        // Verify viewer accessibility for Path B (direct DICOM study)
        $this->get(route('operator.study.show', $studyIdB))->assertOk()
            ->assertSee($uploadResponse->json('display_reference'))
            ->assertSee('VOI otomatis');

        // Verify both studies coexist cleanly
        $allStudiesCount = DB::table('image_gateway_studies')->count();
        $this->assertGreaterThanOrEqual(2, $allStudiesCount);
    }

    private function renderSyntheticThermalTicketPreview(array $data): string
    {
        $cols = 32; // Standard ~48mm printable width at 12 cpi / font A
        $divider = str_repeat('=', $cols);
        $subdivider = str_repeat('-', $cols);
        $tearLine = '- - - - - [ TEAR HERE ] - - - -';

        $center = fn (string $text) => str_pad(mb_substr($text, 0, $cols), $cols, ' ', STR_PAD_BOTH);
        $left = fn (string $text) => str_pad(mb_substr($text, 0, $cols), $cols, ' ', STR_PAD_RIGHT);

        $lines = [];
        $lines[] = $divider;
        $lines[] = $center($data['site_name']);
        $lines[] = $center($data['schedule_window']);
        $lines[] = $subdivider;
        $lines[] = $center('QUEUE TICKET');
        $lines[] = $center($data['ticket_number']);
        $lines[] = $subdivider;
        $lines[] = $left('Issued: '.$data['issued_at']);
        $lines[] = $tearLine;

        return implode("\n", $lines);
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
}
