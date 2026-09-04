<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Modules\Operator\Application\Services\OperatorReusableConsentService;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorFieldOperationsSlice1Test extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mhcs.security.asset_grants' => ['max_ttl_seconds' => 300, 'audiences' => ['operator-identity']]]);
        Storage::fake('local');
    }

    public function test_authorized_operator_can_create_operational_shift_within_assigned_site_scope(): void
    {
        $fixture = $this->operatorFixture();
        $this->startOperatorSession($fixture);

        $startsAt = '2040-05-01 08:00:00';
        $endsAt = '2040-05-01 12:00:00';
        $quota = 10;

        $response = $this->post(route('operator.shifts.store'), [
            'operator_site_id' => $fixture['siteLocalId'],
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'quota' => $quota,
        ]);

        $response->assertRedirect();

        $schedule = DB::table('shift_schedules')
            ->where('starts_at', $startsAt)
            ->where('ends_at', $endsAt)
            ->first();

        $this->assertNotNull($schedule);
        $this->assertSame($quota, (int) $schedule->quota);
        $this->assertSame('open', $schedule->status);

        // Operator eligible shift must be created
        $eligible = DB::table('operator_eligible_shifts')
            ->where('member_schedule_id', $schedule->id)
            ->first();
        $this->assertNotNull($eligible);
        $this->assertSame($fixture['siteStableId'], $eligible->operator_site_id);

        // Operator shift assignment must be created for current operator
        $assignment = DB::table('operator_shift_assignments')
            ->where('operator_eligible_shift_id', $eligible->id)
            ->where('operator_profile_id', $fixture['profileId'])
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($assignment);
    }

    public function test_operator_cannot_create_shift_for_unassigned_site(): void
    {
        $fixture = $this->operatorFixture();
        $this->startOperatorSession($fixture);

        // Create an unassigned site
        $otherSiteLocalId = (string) Str::uuid();
        $otherSiteStableId = 'unassigned-site-'.Str::lower(Str::random(8));
        DB::table('operator_sites')->insert([
            'id' => $otherSiteLocalId,
            'operator_site_id' => $otherSiteStableId,
            'organization_id' => $fixture['organizationStableId'],
            'organization_name' => 'Synthetic Operator Organization',
            'code' => 'OTHER-SITE',
            'display_name' => 'Other Operator Site',
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'source_version' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post(route('operator.shifts.store'), [
            'operator_site_id' => $otherSiteLocalId,
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2040-05-02 08:00:00',
            'ends_at' => '2040-05-02 12:00:00',
            'quota' => 5,
        ]);

        // Should redirect with error or be forbidden
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('shift_schedules', ['starts_at' => '2040-05-02 08:00:00']);
    }

    public function test_operator_can_search_and_add_existing_member_to_active_shift(): void
    {
        $fixture = $this->operatorFixture();
        $this->startOperatorSession($fixture);

        // Search for existing member
        $response = $this->get(route('operator.shifts.members.search', [
            'schedule' => $fixture['scheduleId'],
            'query' => 'Synthetic Arrival',
        ]));
        $response->assertOk();
        $response->assertSee('Synthetic Arrival Member');

        // Add to active shift (create a new schedule to admit the existing member into)
        $newScheduleId = (string) Str::uuid();
        $newDisplayRef = 'JAD-NEW-'.Str::upper(Str::random(6));
        DB::table('shift_schedules')->insert([
            'id' => $newScheduleId,
            'display_reference' => $newDisplayRef,
            'examination_site_id' => $fixture['siteReferenceId'],
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2040-06-01 08:00:00',
            'ends_at' => '2040-06-01 12:00:00',
            'quota' => 10,
            'status' => 'open',
            'eligible_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $newEligibleId = (string) Str::uuid();
        DB::table('operator_eligible_shifts')->insert([
            'id' => $newEligibleId,
            'member_schedule_id' => $newScheduleId,
            'operator_site_id' => $fixture['siteStableId'],
            'schedule_starts_at' => '2040-06-01 08:00:00',
            'schedule_ends_at' => '2040-06-01 12:00:00',
            'confirmed_count_at_eligibility' => 0,
            'quota' => 10,
            'event_version' => 1,
            'source_event_id' => 'test:shift:'.$newScheduleId,
            'eligible_at' => now(),
            'sync_status' => 'eligible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('operator_shift_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_eligible_shift_id' => $newEligibleId,
            'operator_profile_id' => $fixture['profileId'],
            'assigned_by_user_id' => $fixture['operator']->id,
            'status' => 'active',
            'assigned_at' => now(),
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $addResponse = $this->post(route('operator.shifts.members.add-existing', $newScheduleId), [
            'member_id' => $fixture['memberId'],
        ]);
        $addResponse->assertRedirect();

        // Check that booking, arrival, and verification case are created
        $booking = DB::table('bookings')
            ->where('member_id', $fixture['memberId'])
            ->where('shift_schedule_id', $newScheduleId)
            ->first();
        $this->assertNotNull($booking);
        $this->assertSame('arrived', $booking->status);

        $arrival = DB::table('operator_arrivals')
            ->where('booking_id', $booking->id)
            ->first();
        $this->assertNotNull($arrival);
        $this->assertSame('recorded', $arrival->status);

        $case = DB::table('operator_identity_verifications')
            ->where('arrival_id', $arrival->id)
            ->first();
        $this->assertNotNull($case);
        $this->assertSame('matched', $case->state);
    }

    public function test_on_the_spot_member_registration_captures_all_seven_fields_and_generates_internal_mrn(): void
    {
        $fixture = $this->operatorFixture();
        $this->startOperatorSession($fixture);

        $nik = '3201019001010099';
        $registrationData = [
            'name' => 'Field Registered Member',
            'administrative_gender' => 'male',
            'nik' => $nik,
            'birth_date' => '1990-01-01',
            'phone' => '081234567890',
            'affiliation' => 'PT Health Services Indonesia',
            'office_location' => 'Gedung Wisma 46 Lt 12, Jakarta',
        ];

        $response = $this->post(route('operator.shifts.members.register.store', $fixture['scheduleId']), $registrationData);
        $response->assertRedirect();

        $protected = app(ProtectedIdentifierService::class)->protect($nik);
        $member = DB::table('members')->where('nik_lookup_digest', $protected['lookup_digest'])->first();

        $this->assertNotNull($member);
        $this->assertSame('Field Registered Member', $member->name);
        $this->assertSame('male', $member->administrative_gender);
        $this->assertSame('1990-01-01', $member->birth_date);
        $this->assertSame('081234567890', $member->phone);
        $this->assertSame('PT Health Services Indonesia', $member->affiliation);
        $this->assertSame('Gedung Wisma 46 Lt 12, Jakarta', $member->office_location);

        // Internal MRN check: must NOT be NIK
        $this->assertStringStartsWith('MRN-', $member->medical_record_number);
        $this->assertNotSame($nik, $member->medical_record_number);

        // Member must be admitted to the shift
        $booking = DB::table('bookings')
            ->where('member_id', $member->id)
            ->where('shift_schedule_id', $fixture['scheduleId'])
            ->first();
        $this->assertNotNull($booking);
        $this->assertSame('arrived', $booking->status);
    }

    public function test_on_the_spot_registration_deduplicates_by_nik_and_prevents_duplicate_member(): void
    {
        $fixture = $this->operatorFixture();
        $this->startOperatorSession($fixture);

        $existingMemberBeforeCount = DB::table('members')->count();

        // Attempt to register with the existing fixture member's NIK ('900000000001')
        $response = $this->post(route('operator.shifts.members.register.store', $fixture['scheduleId']), [
            'name' => 'Updated Name If Any',
            'administrative_gender' => 'female',
            'nik' => '900000000001',
            'birth_date' => '1988-01-10',
            'phone' => '089999999999',
            'affiliation' => 'Org X',
            'office_location' => 'Office Y',
        ]);

        $response->assertRedirect();

        // No new member row should be inserted
        $this->assertSame($existingMemberBeforeCount, DB::table('members')->count());

        // Affiliation and office location should be updated on the existing member
        $existing = DB::table('members')->where('id', $fixture['memberId'])->first();
        $this->assertSame('Org X', $existing->affiliation);
        $this->assertSame('Office Y', $existing->office_location);
    }

    public function test_reusable_master_informed_consent_and_lightweight_visit_confirmation(): void
    {
        $fixture = $this->matchedFixture();
        $this->startOperatorSession($fixture);

        // 1. Initial Visit: Upload Master Consent
        $plainPdf = "%PDF-1.7\nsynthetic master consent\n%%EOF";
        $operationId1 = (string) Str::uuid();

        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Master Screening Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => $operationId1,
            'is_master_consent' => '1',
            'consent_scope' => 'general_screening_radiography',
            'scan' => UploadedFile::fake()->createWithContent('master-consent.pdf', $plainPdf),
        ])->assertRedirect(route('operator.paper-consent.show', $fixture['caseId']));

        // Master consent record must exist
        $master = DB::table('member_master_consents')
            ->where('member_id', $fixture['memberId'])
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($master);
        $this->assertSame(1, (int) $master->consent_version);
        $this->assertSame('general_screening_radiography', $master->screening_scope);

        // Projected examination_consents record must also exist for backward compatibility
        $projected1 = DB::table('examination_consents')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertNotNull($projected1);

        // 2. Second Visit for the SAME Member (different shift & verification case)
        $shift2 = app(\App\Modules\Operator\Application\Services\OperatorFieldOperationsService::class)->createShift(
            operatorSiteId: (string) $fixture['siteLocalId'],
            startsAt: '2040-06-02 08:00:00',
            endsAt: '2040-06-02 12:00:00',
            quota: 10,
        );
        $scheduleId2 = $shift2['schedule_id'];

        $admitResult = app(\App\Modules\Operator\Application\Services\OperatorFieldOperationsService::class)->addExistingMemberToShift(
            $fixture['memberId'],
            $scheduleId2,
            (string) Str::uuid(),
        );
        $newBookingId = $admitResult['booking_id'];
        $case2Id = $admitResult['case_id'];

        // Perform lightweight visit confirmation (NO re-upload needed)
        $visitOperationId = (string) Str::uuid();
        $confirmResponse = $this->post(route('operator.paper-consent.visit-confirm', $case2Id), [
            'operation_id' => $visitOperationId,
            'master_consent_id' => $master->id,
            'confirmed' => '1',
            'notes' => 'Patient reconfirmed consent verbally during second visit',
        ]);
        $confirmResponse->assertRedirect(route('operator.paper-consent.show', $case2Id));

        // Check visit confirmation was recorded
        $visitConfirm = DB::table('consent_visit_confirmations')
            ->where('booking_id', $newBookingId)
            ->where('member_master_consent_id', $master->id)
            ->first();
        $this->assertNotNull($visitConfirm);

        // Check projected examination_consents for second visit
        $projected2 = DB::table('examination_consents')->where('booking_id', $newBookingId)->first();
        $this->assertNotNull($projected2);

        // Invariant: Historical master consent version is NEVER overwritten
        $allMasters = DB::table('member_master_consents')->where('member_id', $fixture['memberId'])->get();
        $this->assertCount(1, $allMasters);
        $this->assertSame($master->id, $allMasters->first()->id);
        $this->assertSame(1, (int) $allMasters->first()->consent_version);
    }

    public function test_withdrawn_master_consent_blocks_check_in_and_worklist_progression(): void
    {
        $fixture = $this->matchedFixture();
        $this->startOperatorSession($fixture);

        // Create master consent
        $plainPdf = "%PDF-1.7\nsynthetic master consent\n%%EOF";
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Master Screening Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'is_master_consent' => '1',
            'consent_scope' => 'general_screening_radiography',
            'scan' => UploadedFile::fake()->createWithContent('master-consent.pdf', $plainPdf),
        ])->assertRedirect();

        $master = DB::table('member_master_consents')
            ->where('member_id', $fixture['memberId'])
            ->where('status', 'active')
            ->first();
        $this->assertNotNull($master);

        // Now withdraw consent
        $this->post(route('operator.paper-consent.withdraw', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
            'master_consent_id' => $master->id,
            'reason' => 'Member withdrew consent for radiography',
        ])->assertRedirect();

        $this->assertSame('withdrawn', DB::table('member_master_consents')->where('id', $master->id)->value('status'));
        $this->assertSame('withdrawn', DB::table('examination_consents')->where('booking_id', $fixture['bookingId'])->value('status'));

        // Attempting to issue check-in ticket must fail
        $ticketResponse = $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
        ]);
        $ticketResponse->assertSessionHasErrors(['ticket']);
        $this->assertDatabaseCount('operator_paper_tickets', 0);
    }

    public function test_basic_examination_bypass_at_check_in_transitions_to_radiography_readiness_with_zero_clinical_data(): void
    {
        $fixture = $this->matchedFixture();
        $this->startOperatorSession($fixture);

        // Confirm consent
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        // Issue ticket WITH bypassBasicExamination = true
        $operationId = (string) Str::uuid();
        $response = $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => $operationId,
            'bypass_basic_examination' => '1',
        ]);
        $response->assertRedirect();

        // Assert basic_examination stage is 'skipped'
        $basicAdmission = DB::table('operator_queue_admissions')
            ->where('stage', 'basic_examination')
            ->first();
        $this->assertNotNull($basicAdmission);
        $this->assertSame('skipped', $basicAdmission->state);

        // Assert xray stage is 'waiting'
        $xrayAdmission = DB::table('operator_queue_admissions')
            ->where('stage', 'xray')
            ->first();
        $this->assertNotNull($xrayAdmission);
        $this->assertSame('waiting', $xrayAdmission->state);

        // History shows 'skipped' event
        $skippedHistory = DB::table('operator_queue_admission_history')
            ->where('operator_queue_admission_id', $basicAdmission->id)
            ->where('event_type', 'skipped')
            ->first();
        $this->assertNotNull($skippedHistory);
        $this->assertSame('skipped', $skippedHistory->to_state);

        // Zero clinical data fabricated:
        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertDatabaseCount('member_paper_questionnaires', 0);

        // Audit log records skipped event
        $auditEvent = DB::table('audit_events')
            ->where('action', 'operator.basic-examination.skipped')
            ->first();
        $this->assertNotNull($auditEvent);
        $this->assertSame('success', $auditEvent->outcome);
    }

    public function test_basic_examination_bypass_from_worklist(): void
    {
        $fixture = $this->matchedFixture();
        $this->startOperatorSession($fixture);

        // Standard consent & check-in without bypass
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();

        $basicAdmission = DB::table('operator_queue_admissions')
            ->where('stage', 'basic_examination')
            ->first();
        $this->assertSame('waiting', $basicAdmission->state);

        // Operator triggers bypass from the worklist
        $bypassOperationId = (string) Str::uuid();
        $bypassResponse = $this->post(route('operator.basic-examination-worklist.bypass', $basicAdmission->id), [
            'operation_id' => $bypassOperationId,
        ]);
        $bypassResponse->assertRedirect(route('operator.xray-readiness-worklist'));

        // Basic admission is now 'skipped'
        $updatedBasic = DB::table('operator_queue_admissions')->where('id', $basicAdmission->id)->first();
        $this->assertSame('skipped', $updatedBasic->state);

        // X-ray admission is now 'waiting'
        $xrayAdmission = DB::table('operator_queue_admissions')->where('stage', 'xray')->first();
        $this->assertNotNull($xrayAdmission);
        $this->assertSame('waiting', $xrayAdmission->state);

        // Zero clinical data
        $this->assertDatabaseCount('member_vital_signs_assessments', 0);
        $this->assertDatabaseCount('operator_vital_signs_executions', 0);
        $this->assertDatabaseCount('member_paper_questionnaires', 0);
    }

    public function test_dual_path_normal_and_bypass_coexist_in_same_shift(): void
    {
        $fixture = $this->matchedFixture();
        $this->startOperatorSession($fixture);

        // Member 1 (Bypass path)
        $this->post(route('operator.paper-consent.store', $fixture['caseId']), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent1.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        $this->post(route('operator.check-in.store', $fixture['caseId']), [
            'operation_id' => (string) Str::uuid(),
            'bypass_basic_examination' => '1',
        ])->assertRedirect();

        // Member 2 (Normal path in the same shift)
        $reg2 = $this->post(route('operator.shifts.members.register.store', $fixture['scheduleId']), [
            'name' => 'Synthetic Member Two',
            'administrative_gender' => 'female',
            'nik' => '900000000002',
            'birth_date' => '1992-05-15',
            'phone' => '081234567891',
            'affiliation' => 'Org Two',
            'office_location' => 'Office Two',
        ]);
        $reg2->assertRedirect();

        $caseId2 = DB::table('operator_identity_verifications')
            ->join('bookings', 'bookings.id', '=', 'operator_identity_verifications.booking_id')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->where('members.name', 'Synthetic Member Two')
            ->value('operator_identity_verifications.id');

        $this->post(route('operator.paper-consent.store', $caseId2), [
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => 'member',
            'signature_confirmed' => '1',
            'signed_at' => '2040-01-10',
            'operation_id' => (string) Str::uuid(),
            'scan' => UploadedFile::fake()->createWithContent('signed-consent2.pdf', "%PDF-1.7\nsynthetic\n%%EOF"),
        ])->assertRedirect();

        $this->post(route('operator.check-in.store', $caseId2), [
            'operation_id' => (string) Str::uuid(),
            'bypass_basic_examination' => '0',
        ])->assertRedirect();

        // Check co-existence
        // Member 1 has basic_examination skipped and xray waiting
        // Member 2 has basic_examination waiting
        $basicAdmissions = DB::table('operator_queue_admissions')->where('stage', 'basic_examination')->get();
        $this->assertCount(2, $basicAdmissions);

        $states = $basicAdmissions->pluck('state')->all();
        $this->assertContains('skipped', $states);
        $this->assertContains('waiting', $states);

        $xrayAdmissions = DB::table('operator_queue_admissions')->where('stage', 'xray')->get();
        $this->assertCount(1, $xrayAdmissions);
        $this->assertSame('waiting', $xrayAdmissions->first()->state);
    }

    /** @return array<string, mixed> */
    private function matchedFixture(): array
    {
        $fixture = $this->operatorFixture(false);
        $this->makeMemberDigestUnique($fixture);
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
    private function makeMemberDigestUnique(array $fixture): void
    {
        $protected = app(ProtectedIdentifierService::class)->protect('900000000001');
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
}
