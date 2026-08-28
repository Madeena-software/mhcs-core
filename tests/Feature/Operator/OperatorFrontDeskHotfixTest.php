<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class OperatorFrontDeskHotfixTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_non_admin_operator_with_front_desk_permission_can_manage_schedules(): void
    {
        $fixture = $this->operatorFixture(false);

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->get(route('operator.schedules.index'))
            ->assertOk()
            ->assertSee('Kelola Jadwal');
    }

    public function test_front_desk_schedule_management_requires_shift_manage_permission(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.shift.manage')
            ->update(['active' => false]);

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->get(route('operator.schedules.index'))
            ->assertForbidden();
    }

    public function test_front_desk_can_create_a_running_schedule_without_changing_member_schedule_rules(): void
    {
        $fixture = $this->operatorFixture(false);

        $response = $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.store'), [
                'service_offering_id' => $fixture['serviceId'],
                'starts_at' => '2041-01-10T10:00:00+07:00',
                'ends_at' => '2041-01-10T11:00:00+07:00',
                'quota' => 1,
                'examination_site_id' => (string) Str::uuid(),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', [
            'examination_site_id' => $fixture['siteReferenceId'],
            'service_offering_id' => $fixture['serviceId'],
            'quota' => 1,
            'status' => 'open',
        ]);
        $this->assertMatchesRegularExpression('/^JAD-[A-Z0-9]{8}$/', (string) DB::table('shift_schedules')->latest('created_at')->value('display_reference'));
    }

    public function test_front_desk_walk_in_registration_keeps_identity_pending_and_login_disabled(): void
    {
        $fixture = $this->operatorFixture(false);

        $response = $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.members.store'), [
                'name' => 'Walk-in Member',
                'email' => 'walk-in@example.test',
                'phone' => '081234567890',
                'operation_id' => (string) Str::uuid(),
            ]);

        $response->assertRedirect();
        $member = DB::table('members')->where('name', 'Walk-in Member')->first();
        $this->assertNotNull($member);
        $this->assertSame('walk_in', $member->registration_source);
        $this->assertSame('pending_verification', $member->identity_status);
        $this->assertNull($member->birth_date);
        $this->assertNull($member->administrative_gender);
        $this->assertNull($member->encrypted_nik);
        $this->assertDatabaseHas('users', [
            'id' => $member->user_id,
            'email' => 'walk-in@example.test',
            'email_verified_at' => null,
            'login_enabled' => false,
        ]);
    }

    public function test_front_desk_schedule_rules_allow_running_quota_500_and_reject_invalid_or_overlapping_sessions(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $runningStart = now()->utc()->subMinute()->format(DATE_ATOM);
        $runningEnd = now()->utc()->addHour()->format(DATE_ATOM);
        $runningResponse = $this->post(route('operator.schedules.store'), [
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => $runningStart,
            'ends_at' => $runningEnd,
            'quota' => 500,
        ]);
        $runningResponse->assertRedirect();
        $this->assertDatabaseHas('shift_schedules', ['quota' => 500, 'status' => 'open']);

        $this->post(route('operator.schedules.store'), [
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2041-01-10T12:00:00+07:00',
            'ends_at' => '2041-01-10T13:00:00+07:00',
            'quota' => 0,
        ])->assertSessionHasErrors('quota');

        $this->post(route('operator.schedules.store'), [
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2041-01-10T12:00:00+07:00',
            'ends_at' => '2041-01-10T13:00:00+07:00',
            'quota' => 501,
        ])->assertSessionHasErrors('quota');

        $this->post(route('operator.schedules.store'), [
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2040-01-10T10:00:00+07:00',
            'ends_at' => '2040-01-10T11:00:00+07:00',
            'quota' => 1,
        ])->assertSessionHasErrors('schedule');

        $this->createSchedule($fixture, '2041-01-10T14:00:00+07:00', '2041-01-10T15:00:00+07:00', 2);
        $this->post(route('operator.schedules.store'), [
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2041-01-10T14:30:00+07:00',
            'ends_at' => '2041-01-10T15:30:00+07:00',
            'quota' => 2,
        ])->assertSessionHasErrors('schedule');
    }

    public function test_front_desk_walk_ins_support_email_phone_or_both_and_store_missing_identity_as_null(): void
    {
        $fixture = $this->operatorFixture(false);

        $emailOnly = $this->registerWalkIn($fixture, 'Email-only Walk-in', 'email-only@example.test', null);
        $phoneOnly = $this->registerWalkIn($fixture, 'Phone-only Walk-in', null, '081111111111');
        $phoneOnlySecond = $this->registerWalkIn($fixture, 'Phone-only Second Walk-in', null, '081111111112');
        $both = $this->registerWalkIn($fixture, 'Both-contact Walk-in', 'both@example.test', '082222222222');

        foreach ([$emailOnly, $phoneOnly, $phoneOnlySecond, $both] as $member) {
            $this->assertSame('walk_in', $member->registration_source);
            $this->assertSame('pending_verification', $member->identity_status);
            $this->assertNull($member->identity_document_type);
            $this->assertNull($member->encrypted_nik);
            $this->assertNull($member->nik_lookup_digest);
            $this->assertNull($member->birth_date);
            $this->assertNull($member->administrative_gender);
        }
        $this->assertNull(DB::table('users')->where('id', $phoneOnly->user_id)->value('email'));
        $this->assertNotSame('', (string) $emailOnly->medical_record_number);
        $this->assertNotSame('', (string) $phoneOnly->medical_record_number);
        $this->assertNotSame('', (string) $both->medical_record_number);

        $this->post(route('operator.members.store'), [
            'name' => 'Name-only Walk-in',
            'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('members', ['name' => 'Name-only Walk-in']);

        $this->post(route('operator.members.store'), [
            'email' => 'missing-name@example.test',
            'phone' => '083333333333',
            'operation_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('users', ['email' => 'missing-name@example.test']);

        $this->assertSame(2, DB::table('users')->whereNull('email')->count());
    }

    public function test_duplicate_walk_in_email_is_rejected_without_creating_another_member(): void
    {
        $fixture = $this->operatorFixture(false);
        $first = $this->registerWalkIn($fixture, 'First Duplicate Candidate', 'duplicate@example.test', null);
        $memberCount = DB::table('members')->count();
        $userCount = DB::table('users')->count();

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.members.store'), [
                'name' => 'Second Duplicate Candidate',
                'email' => 'duplicate@example.test',
                'operation_id' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('member');

        $this->assertSame($memberCount, DB::table('members')->count());
        $this->assertSame($userCount, DB::table('users')->count());
        $this->assertDatabaseHas('members', ['id' => $first->id]);
        $this->assertDatabaseMissing('members', ['name' => 'Second Duplicate Candidate']);
    }

    public function test_assisted_booking_is_business_supported_idempotent_and_activates_real_eligibility(): void
    {
        $fixture = $this->operatorFixture(false);
        $secondOperator = $this->secondOperatorFixture($fixture);
        $scheduleId = $this->createSchedule($fixture, '2041-01-11T10:00:00+07:00', '2041-01-11T11:00:00+07:00', 2);
        $member = $this->registerWalkIn($fixture, 'Assisted Booking Walk-in', null, '084444444444');
        $operationId = (string) Str::uuid();

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.participants.store', $scheduleId), [
                'member_id' => $member->id,
                'operation_id' => $operationId,
            ])
            ->assertRedirect(route('operator.schedules.show', $scheduleId));

        $booking = DB::table('bookings')->where('shift_schedule_id', $scheduleId)->where('member_id', $member->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('b2b', $booking->booking_type);
        $this->assertSame('business_reserved', $booking->funding_source);
        $this->assertTrue((bool) $booking->operator_assisted_hotfix);
        $this->assertSame($fixture['siteReferenceId'], $booking->examination_site_id_snapshot);
        $this->assertDatabaseHas('local_imaging_orders', ['booking_id' => $booking->id, 'member_id' => $member->id, 'shift_schedule_id' => $scheduleId]);
        $this->assertDatabaseCount('point_ledger_entries', 1);
        $this->assertDatabaseMissing('point_ledger_entries', ['member_id' => $member->id]);

        $this->assertSame(1, DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->count());
        $eligible = DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->first();
        $this->assertNotNull($eligible);
        $this->assertSame(1, (int) $eligible->confirmed_count_at_eligibility);
        $this->assertSame($fixture['siteStableId'], $eligible->operator_site_id);
        $this->assertSame(2, DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligible->id)->where('status', 'active')->count());
        $this->assertDatabaseHas('operator_shift_assignments', ['operator_eligible_shift_id' => $eligible->id, 'operator_profile_id' => $secondOperator['profileId'], 'status' => 'active']);

        $secondMember = $this->registerWalkIn($fixture, 'Second Assisted Walk-in', null, '084444444445');
        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.participants.store', $scheduleId), [
                'member_id' => $secondMember->id,
                'operation_id' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('operator.schedules.show', $scheduleId));
        $this->assertDatabaseHas('bookings', ['shift_schedule_id' => $scheduleId, 'member_id' => $secondMember->id, 'status' => 'confirmed']);
        $this->assertSame(1, DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->count());
        $this->assertSame(2, DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligible->id)->where('status', 'active')->count());

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.participants.store', $scheduleId), [
                'member_id' => $member->id,
                'operation_id' => $operationId,
            ])
            ->assertRedirect(route('operator.schedules.show', $scheduleId));
        $this->assertSame(1, DB::table('bookings')->where('shift_schedule_id', $scheduleId)->where('member_id', $member->id)->count());
        $this->assertSame(1, DB::table('local_imaging_orders')->where('booking_id', $booking->id)->count());
        $this->assertSame(1, DB::table('operator_eligible_shifts')->where('member_schedule_id', $scheduleId)->count());
        $this->assertSame(2, DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligible->id)->where('status', 'active')->count());

        $otherMember = $this->registerWalkIn($fixture, 'Reused Operation Walk-in', null, '085555555555');
        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.participants.store', $scheduleId), [
                'member_id' => $otherMember->id,
                'operation_id' => $operationId,
            ])
            ->assertSessionHasErrors('participant');
        $this->assertDatabaseMissing('bookings', ['shift_schedule_id' => $scheduleId, 'member_id' => $otherMember->id]);
    }

    public function test_assisted_walk_in_flows_through_attendance_arrival_and_visit_level_identity_match(): void
    {
        $fixture = $this->operatorFixture(false);
        $scheduleId = $this->createSchedule($fixture, '2041-01-12T10:00:00+07:00', '2041-01-12T11:00:00+07:00', 2);
        $member = $this->registerWalkIn($fixture, 'Downstream Walk-in', null, '086666666666');
        $this->actingAs($fixture['operator'])->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $this->post(route('operator.schedules.participants.store', $scheduleId), [
            'member_id' => $member->id,
            'operation_id' => (string) Str::uuid(),
        ])->assertRedirect();
        $bookingId = (string) DB::table('bookings')->where('shift_schedule_id', $scheduleId)->where('member_id', $member->id)->value('id');

        $this->get(route('operator.attendance', ['schedule' => $scheduleId, 'at' => '2041-01-12T10:15:00+07:00']))
            ->assertOk()
            ->assertSee('Downstream Walk-in')
            ->assertSee('NIK: Identitas tidak ditampilkan');

        $arrival = app(OperatorArrivalService::class)->confirm($bookingId, '2041-01-12T10:15:00+07:00');
        $recordedArrival = app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => 'arrived']);

        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $fixture['operator']->id,
            'permission' => 'operator.identity.verify',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $case = app(OperatorIdentityVerificationService::class)->start($recordedArrival['arrival_id'], (string) Str::uuid());
        $view = app(OperatorIdentityVerificationService::class)->view($case['case_id']);
        $this->assertSame('walk_in_assisted', $view['evidenceStatus']);
        $this->assertNull($view['view']['nik']);
        $this->assertNull($view['view']['identity_document']);

        try {
            app(OperatorIdentityVerificationService::class)->decide($case['case_id'], OperatorIdentityVerificationService::MATCHED, null, (string) Str::uuid());
            $this->fail('Walk-in identity matching was accepted without explicit confirmation.');
        } catch (OperatorException $exception) {
            $this->assertSame('identity_evidence_unavailable', $exception->category);
        }

        app(OperatorIdentityVerificationService::class)->decide($case['case_id'], OperatorIdentityVerificationService::MATCHED, null, (string) Str::uuid(), true);
        $this->assertDatabaseHas('operator_identity_verifications', ['id' => $case['case_id'], 'state' => 'matched']);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'identity_status' => 'pending_verification']);
        $audit = DB::table('audit_events')->where('action', 'operator.identity-verification.matched')->latest('occurred_at')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('operator_assisted_walk_in_manual_match', (string) $audit->metadata);
    }

    public function test_foreign_site_schedule_cannot_be_booked_from_the_active_operator_site(): void
    {
        $fixture = $this->operatorFixture(false);
        $foreignScheduleId = $this->foreignSchedule($fixture);
        $member = $this->registerWalkIn($fixture, 'Foreign Site Walk-in', null, '087777777777');

        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.participants.store', $foreignScheduleId), [
                'member_id' => $member->id,
                'operation_id' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('participant');

        $this->assertDatabaseMissing('bookings', ['shift_schedule_id' => $foreignScheduleId, 'member_id' => $member->id]);
    }

    /** @param array<string, mixed> $fixture */
    private function createSchedule(array $fixture, string $startsAt, string $endsAt, int $quota): string
    {
        $response = $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.schedules.store'), [
                'service_offering_id' => $fixture['serviceId'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'quota' => $quota,
            ]);
        $response->assertRedirect();
        $path = parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
        $segments = explode('/', trim((string) $path, '/'));

        return (string) end($segments);
    }

    /** @param array<string, mixed> $fixture */
    private function registerWalkIn(array $fixture, string $name, ?string $email, ?string $phone): object
    {
        $this->actingAs($fixture['operator'])
            ->withSession(['operator.active_site_id' => $fixture['siteLocalId']])
            ->post(route('operator.members.store'), array_filter([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'operation_id' => (string) Str::uuid(),
            ], static fn (mixed $value): bool => $value !== null))
            ->assertRedirect();

        $member = DB::table('members')->where('name', $name)->first();
        $this->assertNotNull($member);

        return $member;
    }

    /** @param array<string, mixed> $fixture */
    private function foreignSchedule(array $fixture): string
    {
        $now = now();
        $organizationId = (string) Str::uuid();
        $siteId = (string) Str::uuid();
        $siteReferenceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $operatorSiteId = 'foreign-operator-site-'.Str::lower(Str::random(8));

        DB::table('operator_organization_refs')->insert([
            'id' => $organizationId,
            'operator_organization_id' => 'foreign-operator-org-'.Str::lower(Str::random(8)),
            'name' => 'Foreign organization',
            'source_version' => '1',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_sites')->insert([
            'id' => $siteId,
            'operator_site_id' => $operatorSiteId,
            'organization_id' => 'foreign-organization',
            'organization_name' => 'Foreign organization',
            'code' => 'FOREIGN-SITE',
            'display_name' => 'Foreign site',
            'address_line' => null,
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'source_version' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('examination_site_refs')->insert([
            'id' => $siteReferenceId,
            'operator_site_id' => $operatorSiteId,
            'operator_organization_ref_id' => $organizationId,
            'code' => 'FOREIGN-REF',
            'display_name' => 'Foreign site',
            'timezone' => 'Asia/Jakarta',
            'source_version' => '1',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('shift_schedules')->insert([
            'id' => $scheduleId,
            'display_reference' => 'JAD-'.Str::upper(Str::random(8)),
            'examination_site_id' => $siteReferenceId,
            'service_offering_id' => $fixture['serviceId'],
            'starts_at' => '2041-01-13 03:00:00',
            'ends_at' => '2041-01-13 04:00:00',
            'quota' => 2,
            'status' => 'open',
            'eligible_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $scheduleId;
    }
}
