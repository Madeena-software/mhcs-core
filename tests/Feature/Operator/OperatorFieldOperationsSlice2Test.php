<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\ImageGateway\Application\Services\ImageGatewayCaptureService;
use App\Modules\Member\Application\Services\Mvp03ScheduleService;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Member\Filament\Resources\ShiftSchedules\Pages\EditShiftSchedule;
use App\Modules\Operator\Application\Services\GrabberClientService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Modules\Operator\Domain\Models\RadiographySessionLocator;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\PrivateObjectStore;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorFieldOperationsSlice2Test extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mhcs.security.asset_grants' => ['max_ttl_seconds' => 300, 'audiences' => ['operator-identity']]]);
        Storage::fake('local');
        RateLimiter::clear('grabber:manifest:total:*');
        RateLimiter::clear('grabber:manifest:failed:*');
    }

    /**
     * Helper to set up an active radiography session admission.
     *
     * @return array{
     *     fixture: array<string, mixed>,
     *     admissionId: string,
     *     ticketId: string,
     *     locator: RadiographySessionLocator
     * }
     */
    private function createActiveRadiographySession(string $nik = '900000000088'): array
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

        return [
            'fixture' => $fixture,
            'admissionId' => $admissionId,
            'ticketId' => $ticketId,
            'locator' => $locator,
        ];
    }

    /** @return array<string, mixed> */
    private function matchedFixture(string $nik = '900000000001'): array
    {
        $fixture = $this->operatorFixture(false, $nik);
        $this->makeMemberDigestUnique($fixture, $nik);
        $this->grantIdentityPermission($fixture);
        $this->insertIdentityAssets($fixture);
        $this->startOperatorSession($fixture);

        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $arrivalId = (string) DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->value('id');
        $case = app(OperatorIdentityVerificationService::class)->start($arrivalId, (string) Str::uuid());
        app(OperatorIdentityVerificationService::class)->decide($case['case_id'], 'matched', null, (string) Str::uuid());

        return [...$fixture, 'caseId' => $case['case_id']];
    }

    /** @param array<string, mixed> $fixture */
    private function startOperatorSession(array $fixture): void
    {
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
    }

    /** @param array<string, mixed> $fixture */
    private function grantIdentityPermission(array $fixture): void
    {
        $permissions = [
            'operator.identity.verify',
            'operator.ticket.issue',
            'operator.consent.manage',
            'operator.member.manage',
            'operator.attendance.manage',
            'operator.queue.claim',
            'operator.queue.call',
            'operator.queue.start',
            'operator.worklist.complete',
        ];
        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->updateOrInsert(
                ['user_id' => $fixture['operator']->id, 'permission' => $permission],
                ['id' => (string) Str::uuid(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /** @param array<string, mixed> $fixture */
    private function makeMemberDigestUnique(array $fixture, string $nik = '900000000001'): void
    {
        $protected = app(ProtectedIdentifierService::class)->protect($nik);
        DB::table('members')->where('id', $fixture['memberId'])->update([
            'encrypted_nik' => $protected['encrypted_display'],
            'nik_lookup_digest' => $protected['lookup_digest'],
        ]);
    }

    /** @param array<string, mixed> $fixture */
    private function insertIdentityAssets(array $fixture): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.portal.access', 'operator.identity.verify'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            purpose: 'operator.identity.asset',
        );
        foreach ([['ktp', 'synthetic-identity-document'], ['profile_photo', 'synthetic-latest-profile']] as [$type, $contents]) {
            $object = app(PrivateObjectStore::class)->put($contents, $context, 'operator.identity.asset');
            DB::table('member_verification_assets')->insert([
                'id' => (string) Str::uuid(),
                'member_id' => $fixture['memberId'],
                'type' => $type,
                'private_object_key' => (string) $object->key,
                'checksum' => $object->checksum,
                'bytes' => $object->bytes,
                'format' => 'image/jpeg',
                'review_status' => 'approved',
                'is_current' => true,
                'uploaded_by_user_id' => $fixture['operator']->id,
                'reviewed_by_user_id' => $fixture['operator']->id,
                'reviewed_at' => now(),
                'replaces_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // =========================================================================
    // A. FOUR-DIGIT LOCATOR LIFECYCLE
    // =========================================================================

    public function test_allocates_padded_four_digit_code_between_0000_and_9999(): void
    {
        $session = $this->createActiveRadiographySession();
        $code = $session['locator']->locator_code;

        $this->assertSame(4, strlen($code));
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $code);
        $intVal = (int) $code;
        $this->assertGreaterThanOrEqual(0, $intVal);
        $this->assertLessThanOrEqual(9999, $intVal);

        $this->assertSame('active', $session['locator']->status);
        $this->assertNotNull($session['locator']->active_key);

        // Admission record must also have locator_code set
        $admission = DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->first();
        $this->assertSame($code, $admission->locator_code);
    }

    public function test_locator_allocation_is_idempotent_for_same_admission(): void
    {
        $session = $this->createActiveRadiographySession();
        $locatorService = app(RadiographySessionLocatorService::class);

        $second = $locatorService->allocate(
            $session['admissionId'],
            $session['fixture']['siteLocalId'],
            $session['fixture']['scheduleId'],
        );

        $this->assertSame($session['locator']->id, $second->id);
        $this->assertSame($session['locator']->locator_code, $second->locator_code);
    }

    public function test_enforces_uniqueness_within_same_site_and_shift(): void
    {
        $session1 = $this->createActiveRadiographySession('900000000001');
        $siteId = $session1['fixture']['siteLocalId'];
        $scheduleId = $session1['fixture']['scheduleId'];

        // Create second admission for same site and shift
        $member2 = (string) Str::uuid();
        $booking2 = (string) Str::uuid();
        $ticket2 = (string) Str::uuid();
        $admission2 = (string) Str::uuid();
        $now = now();

        $user2 = User::factory()->create();
        $protected = app(ProtectedIdentifierService::class)->protect('900000000002');
        DB::table('members')->insert([
            'id' => $member2,
            'user_id' => $user2->id,
            'medical_record_number' => 'MRN-22222222',
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protected['encrypted_display'],
            'nik_lookup_digest' => $protected['lookup_digest'],
            'name' => 'Member Two',
            'birth_date' => '1992-02-02',
            'administrative_gender' => 'male',
            'registration_source' => 'administrator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('bookings')->insert([
            'id' => $booking2,
            'member_id' => $member2,
            'shift_schedule_id' => $scheduleId,
            'service_offering_id' => $session1['fixture']['serviceId'],
            'examination_site_id_snapshot' => $session1['fixture']['siteReferenceId'],
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => 'RAD-01',
            'point_cost_snapshot' => '2.5000',
            'point_exchange_rate_id' => (string) DB::table('point_exchange_rates')->value('id'),
            'includes_ai_snapshot' => true,
            'includes_doctor_snapshot' => false,
            'site_code_snapshot' => 'SITE-01',
            'site_name_snapshot' => 'Site 1',
            'site_timezone_snapshot' => 'Asia/Jakarta',
            'created_at' => $now,
            'confirmed_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticket2,
            'operator_site_id' => $siteId,
            'member_schedule_id' => $scheduleId,
            'operator_profile_id' => $session1['fixture']['profileId'],
            'booking_id' => $booking2,
            'ticket_number' => '102',
            'issued_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_queue_admissions')->insert([
            'id' => $admission2,
            'operator_paper_ticket_id' => $ticket2,
            'operator_site_id' => $siteId,
            'member_schedule_id' => $scheduleId,
            'queue_class' => 'standard',
            'stage' => 'xray',
            'state' => 'waiting',
            'ready_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $locatorService = app(RadiographySessionLocatorService::class);
        $locator2 = $locatorService->allocate($admission2, $siteId, $scheduleId);

        $this->assertNotSame($session1['locator']->locator_code, $locator2->locator_code);
    }

    public function test_permits_same_code_across_different_sites_or_shifts(): void
    {
        $session1 = $this->createActiveRadiographySession('900000000011');

        // Create second site & shift
        $session2 = $this->createActiveRadiographySession('900000000022');

        // Force locator2 to have the same code as locator1
        $code = $session1['locator']->locator_code;
        $activeKey2 = sprintf('%s:%s:%s', $session2['fixture']['siteLocalId'], $session2['fixture']['scheduleId'], $code);

        $updated = DB::table('radiography_session_locators')
            ->where('id', $session2['locator']->id)
            ->update([
                'locator_code' => $code,
                'active_key' => $activeKey2,
            ]);

        $this->assertSame(1, $updated);

        $loc1 = DB::table('radiography_session_locators')->where('id', $session1['locator']->id)->first();
        $loc2 = DB::table('radiography_session_locators')->where('id', $session2['locator']->id)->first();

        $this->assertSame($loc1->locator_code, $loc2->locator_code);
        $this->assertNotSame($loc1->operator_site_id, $loc2->operator_site_id);
    }

    public function test_handles_code_space_exhaustion_safely(): void
    {
        $locatorService = app(RadiographySessionLocatorService::class);

        $this->expectException(OperatorException::class);
        $this->expectExceptionMessage('Radiography locator codes are exhausted for the active shift.');

        // Invoke private generateUnusedCode with all 10,000 slots full
        $reflector = new \ReflectionClass($locatorService);
        $method = $reflector->getMethod('generateUnusedCode');
        $method->setAccessible(true);

        $allCodes = [];
        for ($i = 0; $i < 10000; $i++) {
            $allCodes[] = sprintf('%04d', $i);
        }

        $method->invoke($locatorService, $allCodes);
    }

    public function test_marking_session_completed_invalidates_locator_code_and_clears_active_key(): void
    {
        $session = $this->createActiveRadiographySession();
        $locatorService = app(RadiographySessionLocatorService::class);

        $locatorService->markCompleted($session['admissionId']);

        $locator = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertNotNull($locator);
        $this->assertSame('completed', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertNotNull($locator->invalidated_at);
        $this->assertSame('session_completed', $locator->invalidation_reason);

        // Code can now be reused by a new session in the same shift without collision
        $code = $session['locator']->locator_code;
        $activeKey = sprintf('%s:%s:%s', $session['fixture']['siteLocalId'], $session['fixture']['scheduleId'], $code);

        $booking2 = (string) Str::uuid();
        DB::table('bookings')->insert([
            'id' => $booking2,
            'member_id' => $session['fixture']['memberId'],
            'shift_schedule_id' => $session['fixture']['scheduleId'],
            'service_offering_id' => $session['fixture']['serviceId'],
            'examination_site_id_snapshot' => $session['fixture']['siteReferenceId'],
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => 'RAD-01',
            'point_cost_snapshot' => '2.5000',
            'point_exchange_rate_id' => (string) DB::table('point_exchange_rates')->value('id'),
            'includes_ai_snapshot' => true,
            'includes_doctor_snapshot' => false,
            'site_code_snapshot' => 'SITE-01',
            'site_name_snapshot' => 'Site 1',
            'site_timezone_snapshot' => 'Asia/Jakarta',
            'created_at' => now(),
            'confirmed_at' => now(),
            'updated_at' => now(),
        ]);

        $ticket2 = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert([
            'id' => $ticket2,
            'operator_site_id' => $session['fixture']['siteLocalId'],
            'member_schedule_id' => $session['fixture']['scheduleId'],
            'operator_profile_id' => $session['fixture']['profileId'],
            'booking_id' => $booking2,
            'ticket_number' => '103',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admission2 = (string) Str::uuid();
        DB::table('operator_queue_admissions')->insert([
            'id' => $admission2,
            'operator_paper_ticket_id' => $ticket2,
            'operator_site_id' => $session['fixture']['siteLocalId'],
            'member_schedule_id' => $session['fixture']['scheduleId'],
            'queue_class' => 'standard',
            'stage' => 'xray',
            'state' => 'waiting',
            'ready_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newLocator = RadiographySessionLocator::query()->create([
            'id' => (string) Str::uuid(),
            'operator_queue_admission_id' => $admission2,
            'operator_site_id' => $session['fixture']['siteLocalId'],
            'member_schedule_id' => $session['fixture']['scheduleId'],
            'locator_code' => $code,
            'status' => 'active',
            'active_key' => $activeKey,
            'allocated_at' => now(),
        ]);

        $this->assertNotNull($newLocator);
        $this->assertSame($code, $newLocator->locator_code);
    }

    public function test_marking_session_cancelled_invalidates_locator_code(): void
    {
        $session = $this->createActiveRadiographySession();
        $locatorService = app(RadiographySessionLocatorService::class);

        $locatorService->markCancelled($session['admissionId'], 'patient_walkout');

        $locator = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertNotNull($locator);
        $this->assertSame('cancelled', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertNotNull($locator->invalidated_at);
        $this->assertSame('patient_walkout', $locator->invalidation_reason);
    }

    public function test_shift_closure_expires_all_active_locators_for_that_shift(): void
    {
        $session1 = $this->createActiveRadiographySession('900000000031');
        $shiftId = $session1['fixture']['scheduleId'];

        $locatorService = app(RadiographySessionLocatorService::class);
        $invalidatedCount = $locatorService->closeShiftLocators($shiftId);

        $this->assertSame(1, $invalidatedCount);

        $locator = RadiographySessionLocator::query()->find($session1['locator']->id);
        $this->assertNotNull($locator);
        $this->assertSame('expired', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertSame('shift_closed', $locator->invalidation_reason);
    }

    private function scheduleAdminUser(): User
    {
        $admin = User::factory()->create(['email' => 'schedule-admin-'.Str::lower(Str::random(8)).'@example.test']);
        $this->grant($admin, true, ['member.admin.access', 'member.schedule.read', 'member.schedule.manage']);

        return $admin;
    }

    public function test_shift_closure_via_mvp03_schedule_service_invalidates_all_active_locators(): void
    {
        $session = $this->createActiveRadiographySession('900000000041');
        $admin = $this->scheduleAdminUser();
        $this->actingAs($admin);

        $schedule = ShiftSchedule::query()->findOrFail($session['fixture']['scheduleId']);
        app(Mvp03ScheduleService::class)->update($schedule, ['status' => 'closed']);

        $this->assertSame('closed', $schedule->refresh()->status);

        $locator = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertNotNull($locator);
        $this->assertSame('expired', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertNotNull($locator->invalidated_at);
        $this->assertSame('shift_closed', $locator->invalidation_reason);
    }

    public function test_shift_closure_via_filament_edit_shift_schedule_page_invalidates_all_active_locators(): void
    {
        $session = $this->createActiveRadiographySession('900000000042');
        $admin = $this->scheduleAdminUser();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(EditShiftSchedule::class, ['record' => $session['fixture']['scheduleId']])
            ->fillForm(['status' => 'closed'])
            ->call('save')
            ->assertHasNoErrors();

        $schedule = ShiftSchedule::query()->findOrFail($session['fixture']['scheduleId']);
        $this->assertSame('closed', $schedule->status);

        $locator = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertNotNull($locator);
        $this->assertSame('expired', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertNotNull($locator->invalidated_at);
        $this->assertSame('shift_closed', $locator->invalidation_reason);
    }

    public function test_xray_admission_cancellation_via_worklist_route_invalidates_locator(): void
    {
        $session = $this->createActiveRadiographySession('900000000043');
        $this->startOperatorSession($session['fixture']);
        $this->grantIdentityPermission($session['fixture']);

        $operationId = (string) Str::uuid();
        $response = $this->post(route('operator.xray-readiness-worklist.cancel', $session['admissionId']), [
            'operation_id' => $operationId,
            'reason' => 'patient_cancelled',
        ]);

        $response->assertRedirect(route('operator.xray-readiness-worklist'));

        $admission = DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->first();
        $this->assertNotNull($admission);
        $this->assertSame('cancelled', $admission->state);

        $locator = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertNotNull($locator);
        $this->assertSame('cancelled', $locator->status);
        $this->assertNull($locator->active_key);
        $this->assertNotNull($locator->invalidated_at);
        $this->assertSame('patient_cancelled', $locator->invalidation_reason);

        $this->assertDatabaseHas('operator_queue_admission_history', [
            'operator_queue_admission_id' => $session['admissionId'],
            'event_type' => 'cancelled',
            'from_state' => 'waiting',
            'to_state' => 'cancelled',
            'operation_id' => $operationId,
        ]);

        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.xray.cancelled')->where('target_id', $session['admissionId'])->count());
    }

    public function test_consent_withdrawal_invalidates_active_radiography_locator_and_cancels_admission(): void
    {
        $fixture = $this->matchedFixture('900000000044');
        $this->startOperatorSession($fixture);
        $this->grantIdentityPermission($fixture);

        // Store master consent
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'is_master_consent' => '1',
            'consent_scope' => 'general_screening_radiography',
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        // Check in with bypass basic examination directly into xray readiness
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
            'bypass_basic_examination' => '1',
        ])->assertRedirect();

        $xrayAdmission = DB::table('operator_queue_admissions')->where('stage', 'xray')->first();
        $this->assertNotNull($xrayAdmission);
        $this->assertSame('waiting', $xrayAdmission->state);

        $locatorBefore = DB::table('radiography_session_locators')
            ->where('operator_queue_admission_id', $xrayAdmission->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($locatorBefore);
        $this->assertNotNull($locatorBefore->active_key);

        $masterConsent = DB::table('member_master_consents')
            ->where('member_id', $fixture['memberId'])
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($masterConsent);

        // Now withdraw consent via route
        $withdrawResponse = $this->post(route('operator.paper-consent.withdraw', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
            'master_consent_id' => $masterConsent->id,
            'reason' => 'Patient revoked consent',
        ]);
        $withdrawResponse->assertRedirect();

        // Master consent must be withdrawn
        $this->assertSame('withdrawn', DB::table('member_master_consents')->where('id', $masterConsent->id)->value('status'));

        // Admission must be cancelled
        $admissionAfter = DB::table('operator_queue_admissions')->where('id', $xrayAdmission->id)->first();
        $this->assertSame('cancelled', $admissionAfter->state);

        // Locator must be cancelled with null active_key and invalidation_reason consent_withdrawn
        $locatorAfter = DB::table('radiography_session_locators')->where('id', $locatorBefore->id)->first();
        $this->assertSame('cancelled', $locatorAfter->status);
        $this->assertNull($locatorAfter->active_key);
        $this->assertNotNull($locatorAfter->invalidated_at);
        $this->assertSame('consent_withdrawn', $locatorAfter->invalidation_reason);
    }

    // =========================================================================
    // B. GRABBER AUTHENTICATION & AUTHORIZATION
    // =========================================================================

    public function test_grabber_endpoint_rejects_unauthenticated_requests(): void
    {
        $session = $this->createActiveRadiographySession();
        $code = $session['locator']->locator_code;

        $response = $this->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_grabber_endpoint_rejects_invalid_token(): void
    {
        $session = $this->createActiveRadiographySession();
        $code = $session['locator']->locator_code;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-1234567890',
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_grabber_endpoint_authenticates_with_x_grabber_token_header(): void
    {
        $session = $this->createActiveRadiographySession();
        $code = $session['locator']->locator_code;
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $created = $grabberService->create('GRABBER-DDR-01', 'Test DDR Grabber', $siteId);

        $response = $this->withHeaders([
            'X-Grabber-Token' => $created['raw_token'],
            'X-Grabber-ID' => 'GRABBER-DDR-01',
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'examination' => ['study_description'],
            'patient' => ['medical_record_number', 'name', 'sex', 'birth_date'],
            'capture' => ['detector_type', 'body_part_examined', 'laterality', 'projection'],
        ]);
    }

    public function test_grabber_endpoint_rejects_mismatched_grabber_id(): void
    {
        $session = $this->createActiveRadiographySession();
        $code = $session['locator']->locator_code;
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $created = $grabberService->create('GRABBER-DDR-01', 'Test DDR Grabber', $siteId);

        // Pass correct token but wrong X-Grabber-ID
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$created['raw_token'],
            'X-Grabber-ID' => 'GRABBER-WRONG-99',
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_grabber_endpoint_rejects_suspended_or_inactive_grabber_client(): void
    {
        $session = $this->createActiveRadiographySession();
        $code = $session['locator']->locator_code;
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $created = $grabberService->create('GRABBER-DDR-INACTIVE', 'Suspended Grabber', $siteId, status: 'suspended');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$created['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_grabber_endpoint_denies_cross_site_requests(): void
    {
        $session1 = $this->createActiveRadiographySession('900000000041');
        $session2 = $this->createActiveRadiographySession('900000000042');

        // Grabber client belongs to site 1
        $grabberService = app(GrabberClientService::class);
        $client1 = $grabberService->create('GRABBER-SITE1', 'Site 1 DDR', $session1['fixture']['siteLocalId']);

        // Explicitly request site 2
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client1['raw_token'],
            'X-Site-ID' => $session2['fixture']['siteLocalId'],
        ])->getJson("/api/v1/grabber/manifest/{$session2['locator']->locator_code}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Forbidden.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_cross_shift_lookup(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        // Another shift ID not belonging to this site
        $otherShiftId = (string) Str::uuid();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
            'X-Shift-ID' => $otherShiftId,
        ])->getJson("/api/v1/grabber/manifest/{$session['locator']->locator_code}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_closed_shift(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $shiftId = $session['fixture']['scheduleId'];

        // Close the shift schedule
        DB::table('shift_schedules')->where('id', $shiftId)->update(['status' => 'closed']);

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
            'X-Shift-ID' => $shiftId,
        ])->getJson("/api/v1/grabber/manifest/{$session['locator']->locator_code}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_non_existent_locator_code(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson('/api/v1/grabber/manifest/0000');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_malformed_code(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson('/api/v1/grabber/manifest/abc');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_completed_session(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        app(RadiographySessionLocatorService::class)->markCompleted($session['admissionId']);

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_cancelled_session(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        app(RadiographySessionLocatorService::class)->markCancelled($session['admissionId']);

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    public function test_grabber_endpoint_returns_generic_404_for_ineligible_queue_stage(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        // Change queue admission state to completed or stage to something else
        DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->update([
            'state' => 'completed',
        ]);

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Radiography session not found.']);
    }

    // =========================================================================
    // C. MINIMAL MANIFEST CONTRACT & STRICT PRIVACY / NIK ISOLATION
    // =========================================================================

    public function test_grabber_resolves_exact_minimal_dicom_manifest_per_spec(): void
    {
        $nik = '3201019001010077';
        $session = $this->createActiveRadiographySession($nik);
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(200);

        $data = $response->json();

        // 1. Root keys MUST be exactly examination, patient, capture
        $this->assertSame(['examination', 'patient', 'capture'], array_keys($data));

        // 2. Examination keys MUST be exactly study_description
        $this->assertSame(['study_description'], array_keys($data['examination']));
        $this->assertNotEmpty($data['examination']['study_description']);

        // 3. Patient keys MUST be exactly medical_record_number, name, sex, birth_date
        $this->assertSame(['medical_record_number', 'name', 'sex', 'birth_date'], array_keys($data['patient']));

        // 4. Capture keys MUST be exactly detector_type, body_part_examined, laterality, projection
        $this->assertSame(['detector_type', 'body_part_examined', 'laterality', 'projection'], array_keys($data['capture']));

        // 5. Patient ID / MRN must be internal MHCS MRN, NEVER NIK
        $this->assertStringStartsWith('MRN-', $data['patient']['medical_record_number']);
        $this->assertNotSame($nik, $data['patient']['medical_record_number']);
        $this->assertStringNotContainsString($nik, $data['patient']['medical_record_number']);

        // 6. Values match DB
        $member = DB::table('members')->where('id', $session['fixture']['memberId'])->first();
        $this->assertSame($member->medical_record_number, $data['patient']['medical_record_number']);
        $this->assertSame($member->name, $data['patient']['name']);
        $this->assertSame($member->birth_date, $data['patient']['birth_date']);
    }

    public function test_manifest_strictly_excludes_nik_and_prohibited_data(): void
    {
        $nik = '3201019001010088';
        $session = $this->createActiveRadiographySession($nik);
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        // Set rich member attributes that MUST NOT appear in manifest
        DB::table('members')->where('id', $session['fixture']['memberId'])->update([
            'phone' => '081299998888',
            'affiliation' => 'PT Confidential Corp',
            'office_location' => 'Building 4, Jakarta',
        ]);

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-01', 'DDR 1', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(200);

        $jsonContent = (string) $response->getContent();

        // Strictly verify forbidden strings are absent from the entire raw response
        $this->assertStringNotContainsString($nik, $jsonContent);
        $this->assertStringNotContainsString('081299998888', $jsonContent);
        $this->assertStringNotContainsString('PT Confidential Corp', $jsonContent);
        $this->assertStringNotContainsString('Building 4, Jakarta', $jsonContent);
        $this->assertStringNotContainsString('encrypted_nik', $jsonContent);
        $this->assertStringNotContainsString('nik_lookup_digest', $jsonContent);
        $this->assertStringNotContainsString('nik', $jsonContent);
        $this->assertStringNotContainsString('phone', $jsonContent);
        $this->assertStringNotContainsString('affiliation', $jsonContent);
        $this->assertStringNotContainsString('office_location', $jsonContent);
        $this->assertStringNotContainsString('consent', $jsonContent);
        $this->assertStringNotContainsString('questionnaire', $jsonContent);
        $this->assertStringNotContainsString('vital_signs', $jsonContent);
    }

    public function test_manifest_lookup_via_post_endpoint_works_identically(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-POST', 'DDR POST', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->postJson('/api/v1/grabber/manifest/lookup', [
            'locator_code' => $code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'examination' => ['study_description'],
            'patient' => ['medical_record_number', 'name', 'sex', 'birth_date'],
            'capture' => ['detector_type', 'body_part_examined', 'laterality', 'projection'],
        ]);
        $this->assertSame($session['locator']->locator_code, $code);
    }

    public function test_radiography_sessions_alias_endpoint_works(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-ALIAS', 'DDR Alias', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/radiography-sessions/{$code}/manifest");

        $response->assertStatus(200);
        $response->assertJsonPath('patient.medical_record_number', fn ($val) => str_starts_with((string) $val, 'MRN-'));
    }

    // =========================================================================
    // D. SECURITY, RATE-LIMITING & AUDITING
    // =========================================================================

    public function test_rate_limiter_throttles_excessive_failed_lookup_attempts(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-RATE', 'Rate Test Grabber', $siteId);

        // 10 failed attempts are allowed (MAX_FAILED_ATTEMPTS_PER_MINUTE = 10)
        for ($i = 0; $i < 10; $i++) {
            $resp = $this->withHeaders([
                'Authorization' => 'Bearer '.$client['raw_token'],
            ])->getJson('/api/v1/grabber/manifest/9999');

            $resp->assertStatus(404);
        }

        // The 11th attempt MUST be throttled with HTTP 429
        $throttled = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson('/api/v1/grabber/manifest/9999');

        $throttled->assertStatus(429);
        $throttled->assertJson(['message' => 'Too many attempts. Please try again later.']);
        $this->assertTrue($throttled->headers->has('Retry-After'));
    }

    public function test_audit_event_logged_on_successful_manifest_resolution_without_secrets(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];
        $code = $session['locator']->locator_code;

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-AUDIT', 'Audit Grabber', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson("/api/v1/grabber/manifest/{$code}");

        $response->assertStatus(200);

        $resolvedEvent = DB::table('audit_events')->where('action', 'grabber.session.resolved')->first();

        $this->assertNotNull($resolvedEvent);
        $this->assertSame('success', $resolvedEvent->outcome);
        $this->assertSame($session['admissionId'], $resolvedEvent->target_id);
        $metadata = json_decode((string) $resolvedEvent->metadata, true);
        $this->assertSame($code, $metadata['code'] ?? null);

        // Ensure no raw token or NIK in metadata or serialized event
        $serialized = json_encode($resolvedEvent);
        $this->assertStringNotContainsString($client['raw_token'], (string) $serialized);
        $this->assertStringNotContainsString('encrypted_nik', (string) $serialized);
    }

    public function test_audit_event_logged_on_failed_resolution_without_secrets(): void
    {
        $session = $this->createActiveRadiographySession();
        $siteId = $session['fixture']['siteLocalId'];

        $grabberService = app(GrabberClientService::class);
        $client = $grabberService->create('GRABBER-FAIL', 'Fail Audit Grabber', $siteId);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$client['raw_token'],
        ])->getJson('/api/v1/grabber/manifest/0000');

        $response->assertStatus(404);

        $failedEvent = DB::table('audit_events')->where('action', 'grabber.session.failed')->first();

        $this->assertNotNull($failedEvent);
        $this->assertSame('failure', $failedEvent->outcome);
        $this->assertSame('session_not_found', $failedEvent->reason);
    }

    // =========================================================================
    // E. OPERATOR WORKFLOW INTEGRATION
    // =========================================================================

    public function test_bypass_basic_examination_at_check_in_allocates_locator_and_displays_on_xray_worklist(): void
    {
        $fixture = $this->matchedFixture();

        // Confirm paper consent
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        // Issue ticket WITH bypass basic examination
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
            'bypass_basic_examination' => '1',
        ])->assertRedirect();

        $xrayAdmission = DB::table('operator_queue_admissions')->where('stage', 'xray')->first();
        $this->assertNotNull($xrayAdmission);
        $this->assertSame('waiting', $xrayAdmission->state);
        $this->assertNotNull($xrayAdmission->locator_code);
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $xrayAdmission->locator_code);

        // Verify active locator record exists
        $locator = DB::table('radiography_session_locators')
            ->where('operator_queue_admission_id', $xrayAdmission->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($locator);
        $this->assertSame($xrayAdmission->locator_code, $locator->locator_code);

        // Verify locator code is visible on X-ray readiness worklist UI
        $worklistResponse = $this->get(route('operator.xray-readiness-worklist'));
        $worklistResponse->assertOk();
        $worklistResponse->assertSee($locator->locator_code);
    }

    public function test_bypass_basic_examination_from_worklist_allocates_locator_and_displays_on_xray_worklist(): void
    {
        $fixture = $this->matchedFixture();

        // Confirm paper consent
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        // Issue ticket without bypass (sequential)
        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
            'bypass_basic_examination' => '0',
        ])->assertRedirect();

        $basicAdmission = DB::table('operator_queue_admissions')->where('stage', 'basic_examination')->first();
        $this->assertNotNull($basicAdmission);

        // Bypass basic examination from worklist
        $this->post(route('operator.basic-examination-worklist.bypass', $basicAdmission->id), [
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect(route('operator.xray-readiness-worklist'));

        // X-ray admission must now be 'waiting' and have locator_code allocated
        $xrayAdmission = DB::table('operator_queue_admissions')->where('stage', 'xray')->first();
        $this->assertNotNull($xrayAdmission);
        $this->assertSame('waiting', $xrayAdmission->state);
        $this->assertNotNull($xrayAdmission->locator_code);
        $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $xrayAdmission->locator_code);

        $locator = DB::table('radiography_session_locators')
            ->where('operator_queue_admission_id', $xrayAdmission->id)
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($locator);

        // Verify xray worklist contains locator code
        $worklistResponse = $this->get(route('operator.xray-readiness-worklist'));
        $worklistResponse->assertOk();
        $worklistResponse->assertSee($locator->locator_code);
    }

    public function test_image_gateway_capture_acceptance_marks_locator_completed(): void
    {
        $session = $this->createActiveRadiographySession();

        // Insert capture set row for this admission
        $captureId = (string) Str::uuid();
        DB::table('image_gateway_capture_sets')->insert([
            'id' => $captureId,
            'submission_id' => (string) Str::uuid(),
            'admission_id' => $session['admissionId'],
            'booking_id' => $session['fixture']['bookingId'],
            'member_schedule_id' => $session['fixture']['scheduleId'],
            'operator_site_id' => $session['fixture']['siteLocalId'],
            'operator_profile_id' => $session['fixture']['profileId'],
            'radiograph_count' => 1,
            'status' => 'capturing',
            'processing_status' => 'pending',
            'attempts' => 0,
            'radiograph_status' => 'success',
            'gain_status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert mock capture objects
        foreach (['radiograph', 'gain', 'manifest', 'manifest_signature'] as $index => $type) {
            DB::table('image_gateway_capture_objects')->insert([
                'id' => (string) Str::uuid(),
                'capture_set_id' => $captureId,
                'object_type' => $type,
                'object_index' => $index,
                'object_key' => 'mock-key-'.$type,
                'checksum' => hash('sha256', $type),
                'bytes' => 100,
                'format' => 'application/octet-stream',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Set admission to called/in_service with operator profile
        DB::table('operator_queue_admissions')->where('id', $session['admissionId'])->update([
            'state' => 'in_service',
            'operator_profile_id' => $session['fixture']['profileId'],
        ]);

        $locatorBefore = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertSame('active', $locatorBefore->status);

        $captureService = app(ImageGatewayCaptureService::class);
        $reflector = new \ReflectionClass($captureService);
        $acceptMethod = $reflector->getMethod('acceptSources');
        $acceptMethod->setAccessible(true);

        $captureRow = DB::table('image_gateway_capture_sets')->where('id', $captureId)->first();
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $session['fixture']['operator']->id),
            operationId: CorrelationId::random(),
            roles: ['operator'],
            permissions: ['operator.portal.access', 'image-gateway.capture.manage'],
            siteId: LocalId::fromString($session['fixture']['siteLocalId']),
            purpose: ImageGatewayCaptureService::CAPTURE_PURPOSE,
        );

        $acceptMethod->invoke(
            $captureService,
            $captureRow,
            $session['fixture']['profileId'],
            $session['fixture']['siteStableId'],
            (string) Str::uuid(),
            $context,
        );

        // Locator status must now be 'completed'
        $locatorAfter = RadiographySessionLocator::query()->find($session['locator']->id);
        $this->assertSame('completed', $locatorAfter->status);
        $this->assertNull($locatorAfter->active_key);
        $this->assertSame('capture_accepted', $locatorAfter->invalidation_reason);
    }
}
