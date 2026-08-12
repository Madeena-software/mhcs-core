<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Shared\Security\ProtectedIdentifierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04OperatorPortalTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_operator_only_user_can_complete_shared_interactive_login(): void
    {
        $fixture = $this->operatorFixture(false);

        $response = $this->post('/login', [
            'identifier' => $fixture['operator']->email,
            'password' => 'password',
        ]);

        $this->assertSame(route('operator.dashboard'), $response->headers->get('Location'));
    }

    public function test_operator_has_a_distinct_login_entry_and_reaches_the_workstation(): void
    {
        $fixture = $this->operatorFixture(false);

        $this->get(route('operator.login'))
            ->assertOk()
            ->assertSee('Operator workstation')
            ->assertDontSee('Masuk ke MHCS Core');

        $this->post(route('operator.login.store'), [
            'identifier' => $fixture['operator']->email,
            'password' => 'password',
        ])->assertRedirect(route('operator.dashboard'));
    }

    public function test_member_and_administrator_accounts_receive_the_same_generic_operator_login_failure(): void
    {
        $member = User::factory()->create(['email' => 'member-only@example.test']);
        $administrator = User::factory()->create(['email' => 'administrator-only@example.test']);
        DB::table('authorization_role_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $administrator->id,
            'role' => 'administrator',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$member, $administrator] as $user) {
            $this->post(route('operator.login.store'), [
                'identifier' => $user->email,
                'password' => 'password',
            ])->assertSessionHasErrors(['identifier']);

            $this->assertGuest('web');
        }
    }

    public function test_dedicated_operator_login_rechecks_profile_role_permission_and_account_state(): void
    {
        $fixture = $this->operatorFixture(false);
        $deny = function () use ($fixture): void {
            $this->post(route('operator.login.store'), [
                'identifier' => $fixture['operator']->email,
                'password' => 'password',
            ])->assertSessionHasErrors(['identifier']);
            $this->assertGuest('web');
        };

        DB::table('operator_profiles')->where('id', $fixture['profileId'])->update(['active' => false]);
        $deny();

        DB::table('operator_profiles')->where('id', $fixture['profileId'])->update(['active' => true]);
        DB::table('authorization_role_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('role', 'operator')
            ->update(['active' => false]);
        $deny();

        DB::table('authorization_role_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('role', 'operator')
            ->update(['active' => true]);
        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => false]);
        $deny();

        DB::table('authorization_permission_assignments')
            ->where('user_id', $fixture['operator']->id)
            ->where('permission', 'operator.portal.access')
            ->update(['active' => true]);
        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
        $deny();
    }

    public function test_operator_password_replacement_keeps_the_dedicated_entry_destination(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('users')->where('id', $fixture['operator']->id)->update([
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->post(route('operator.login.store'), [
            'identifier' => $fixture['operator']->email,
            'password' => 'temporary-password',
        ])->assertRedirect(route('password.change-required'));

        $this->post(route('password.change-required.update'), [
            'current_password' => 'temporary-password',
            'password' => 'operator-password-1',
            'password_confirmation' => 'operator-password-1',
        ])->assertRedirect(route('operator.dashboard'));
    }

    public function test_operator_only_mandatory_password_replacement_does_not_require_a_member(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('users')->where('id', $fixture['operator']->id)->update([
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->post('/login', [
            'identifier' => $fixture['operator']->email,
            'password' => 'temporary-password',
        ])->assertRedirect(route('password.change-required'));

        $this->get('/password/change-required')->assertOk();
        $response = $this->post('/password/change-required', [
            'current_password' => 'temporary-password',
            'password' => 'operator-password-1',
            'password_confirmation' => 'operator-password-1',
        ]);

        $this->assertSame(route('operator.dashboard'), $response->headers->get('Location'));
        $this->assertDatabaseHas('users', ['id' => $fixture['operator']->id, 'must_change_password' => false]);
        $this->assertDatabaseHas('audit_events', ['action' => 'operator.password-replacement', 'source' => 'operator', 'outcome' => 'success']);
    }

    public function test_dual_role_login_honors_only_local_authorized_intended_surfaces(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('members')->where('id', $fixture['memberId'])->update(['user_id' => $fixture['operator']->id]);

        $this->withSession(['url.intended' => route('operator.dashboard')])->post('/login', [
            'identifier' => $fixture['operator']->email,
            'password' => 'password',
            'role' => 'member',
        ])->assertRedirect(route('operator.dashboard'));

        $this->post('/logout');
        $response = $this->withSession(['url.intended' => 'https://external.example/unsafe'])->post('/login', [
            'identifier' => $fixture['operator']->email,
            'password' => 'password',
            'role' => 'operator',
        ]);

        $this->assertSame(route('member.dashboard'), $response->headers->get('Location'));
    }

    public function test_inactive_operator_profile_and_revoked_operator_claims_fail_closed_at_login(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('operator_profiles')->where('id', $fixture['profileId'])->update(['active' => false]);
        $this->post('/login', ['identifier' => $fixture['operator']->email, 'password' => 'password'])
            ->assertSessionHasErrors(['identifier']);

        DB::table('operator_profiles')->where('id', $fixture['profileId'])->update(['active' => true]);
        DB::table('authorization_role_assignments')->where('user_id', $fixture['operator']->id)->where('role', 'operator')->update(['active' => false]);
        $this->post('/login', ['identifier' => $fixture['operator']->email, 'password' => 'password'])
            ->assertSessionHasErrors(['identifier']);

        DB::table('authorization_role_assignments')->where('user_id', $fixture['operator']->id)->where('role', 'operator')->update(['active' => true]);
        DB::table('authorization_permission_assignments')->where('user_id', $fixture['operator']->id)->where('permission', 'operator.portal.access')->update(['active' => false]);
        $this->post('/login', ['identifier' => $fixture['operator']->email, 'password' => 'password'])
            ->assertSessionHasErrors(['identifier']);

        DB::table('authorization_permission_assignments')->where('user_id', $fixture['operator']->id)->where('permission', 'operator.portal.access')->update(['active' => true]);
        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'suspended']);
        $this->post('/login', ['identifier' => $fixture['operator']->email, 'password' => 'password'])
            ->assertSessionHasErrors(['identifier']);

        DB::table('users')->where('id', $fixture['operator']->id)->update(['account_status' => 'active', 'login_enabled' => false]);
        $this->post('/login', ['identifier' => $fixture['operator']->email, 'password' => 'password'])
            ->assertSessionHasErrors(['identifier']);
    }

    public function test_operator_routes_use_shared_auth_and_render_only_bounded_attendance_data(): void
    {
        $this->get('/operator')->assertRedirect(route('login'));

        $member = User::factory()->create(['email' => 'member-only@example.test']);
        $this->actingAs($member)->get('/operator')->assertForbidden();

        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->get('/operator')->assertOk()->assertSee('No active site is selected.');
        $this->post('/operator/site', ['site_id' => $fixture['siteLocalId']])->assertRedirect(route('operator.dashboard'));

        $this->get('/operator/attendance/'.$fixture['scheduleId'].'?at=2040-01-10T10:15:00%2B07:00')
            ->assertOk()
            ->assertSee('Synthetic Arrival Member')
            ->assertSee('MRN-'.substr($fixture['memberId'], 0, 8))
            ->assertSee('900000000001')
            ->assertDontSee('Refresh attendance')
            ->assertDontSee('authorization_permission_assignments')
            ->assertDontSee('point balance');
    }

    public function test_assigned_shift_attendance_link_uses_the_schedule_start_time(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->get(route('operator.eligible-shifts'))
            ->assertOk()
            ->assertSee(route('operator.attendance', [
                'schedule' => $fixture['scheduleId'],
                'at' => '2040-01-10T03:00:00+00:00',
            ]), false);
    }

    public function test_selected_shift_roster_keeps_all_members_and_shows_state_and_next_action(): void
    {
        $fixture = $this->operatorFixture(false);
        for ($index = 1; $index <= 36; $index++) {
            $this->insertRosterBooking($fixture, $index);
        }
        DB::table('bookings')->where('id', $fixture['bookingId'])->update(['status' => 'arrived']);

        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        $response = $this->get('/operator/attendance/'.$fixture['scheduleId'].'?at=2040-01-10T10:15:00%2B07:00')
            ->assertOk()
            ->assertSee('Synthetic Arrival Member')
            ->assertSee('Arrived')
            ->assertSee('Continue identity verification');

        for ($index = 1; $index <= 36; $index++) {
            $response->assertSee('Roster Member '.$index);
        }
    }

    public function test_workstation_shows_the_ordered_clinic_flow_and_server_derived_queue_counts(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->actingAs($fixture['operator']);

        $this->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Select an assigned site');

        $this->post(route('operator.site.select'), ['site_id' => $fixture['siteLocalId']])
            ->assertRedirect(route('operator.dashboard'));

        $this->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('1. Attendance')
            ->assertSee(route('lcd.show', $fixture['siteLocalId']), false)
            ->assertSee('Open LCD queue')
            ->assertSee('2. Arrival and verification')
            ->assertSee('3. Consent and ticket')
            ->assertSee('4. Basic examination')
            ->assertSee('5. X-ray readiness')
            ->assertSee('data-testid="verification-queue-count"', false)
            ->assertSee('data-testid="basic-examination-queue-count"', false)
            ->assertSee('data-testid="xray-queue-count"', false);
    }

    public function test_arrival_requires_explicit_confirmation_and_uses_authoritative_schedule_redirect(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->post('/operator/arrivals/confirm', [
            'booking_id' => $fixture['bookingId'],
            'occurrence_at' => '2040-01-10T10:15:00',
        ])->assertSessionHasErrors('arrival');
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);
        $this->assertDatabaseCount('operator_arrivals', 0);

        $this->post('/operator/arrivals/confirm', [
            'booking_id' => $fixture['bookingId'],
            'occurrence_at' => '2040-01-10T10:15:00+07:00',
        ])->assertOk()->assertSee('Confirm physical arrival');

        $token = (string) data_get(session('operator.arrival_confirmation'), 'token');
        $this->assertNotSame('', $token);
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);
        $this->assertDatabaseCount('operator_arrivals', 0);

        $this->post('/operator/arrivals', [
            'confirmation_token' => $token,
            'schedule_id' => (string) Str::uuid(),
        ])->assertRedirect(route('operator.attendance', [
            'schedule' => $fixture['scheduleId'],
            'at' => '2040-01-10T03:15:00+00:00',
        ]));

        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'arrived']);
        $this->get('/operator/verification-worklist')->assertOk()->assertSee('Synthetic Arrival Member')->assertSee('pending_verification');
        $this->assertDatabaseMissing('operator_arrivals', ['booking_id' => $fixture['bookingId'], 'status' => 'pending']);

        $this->post('/operator/arrivals', ['confirmation_token' => $token])->assertRedirect(route('operator.attendance', [
            'schedule' => $fixture['scheduleId'],
            'at' => '2040-01-10T03:15:00+00:00',
        ]));
        $this->assertDatabaseCount('operator_arrivals', 1);
    }

    public function test_cancelled_or_expired_confirmation_does_not_mutate(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->post('/operator/arrivals/confirm', [
            'booking_id' => $fixture['bookingId'],
            'occurrence_at' => '2040-01-10T10:15:00+07:00',
        ])->assertOk();
        $cancelledToken = (string) data_get(session('operator.arrival_confirmation'), 'token');
        $this->post('/operator/arrivals/cancel', ['confirmation_token' => $cancelledToken])->assertRedirect(route('operator.dashboard'));
        $cancelledResponse = $this->post('/operator/arrivals', ['confirmation_token' => $cancelledToken]);
        $this->assertSame(302, $cancelledResponse->getStatusCode());

        $this->post('/operator/arrivals/confirm', [
            'booking_id' => $fixture['bookingId'],
            'occurrence_at' => '2040-01-10T10:15:00+07:00',
        ])->assertOk();
        $expired = session('operator.arrival_confirmation');
        $this->assertIsArray($expired);
        $expired['expires_at'] = '2020-01-10T03:00:00+00:00';
        session()->put('operator.arrival_confirmation', $expired);

        $expiredResponse = $this->post('/operator/arrivals', ['confirmation_token' => $expired['token']]);
        $this->assertSame(302, $expiredResponse->getStatusCode());
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);
        $this->assertDatabaseCount('operator_arrivals', 0);
    }

    public function test_revoked_site_assignment_cannot_be_used_by_a_stale_session(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false, 'revoked_at' => now()]);

        $this->get('/operator/attendance/'.$fixture['scheduleId'].'?at=2040-01-10T10:15:00%2B07:00')->assertRedirect(route('operator.dashboard'));
    }

    /** @param array<string, mixed> $fixture */
    private function insertRosterBooking(array $fixture, int $index): void
    {
        $now = now();
        $memberUser = User::factory()->create(['email' => 'roster-member-'.$index.'-'.Str::lower(Str::random(6)).'@example.test']);
        $memberId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        $protected = app(ProtectedIdentifierService::class)->protect((string) (900000000100 + $index));
        $service = DB::table('service_offerings')->where('id', $fixture['serviceId'])->first();
        $site = DB::table('examination_site_refs')->where('id', $fixture['siteReferenceId'])->first();

        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $memberUser->id,
            'family_id' => null,
            'medical_record_number' => 'ROSTER-MRN-'.$index,
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protected['encrypted_display'],
            'nik_lookup_digest' => $protected['lookup_digest'],
            'name' => 'Roster Member '.$index,
            'birth_date' => '1988-01-10',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Synthetic address',
            'emergency_contact_name' => 'Synthetic contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '0800000000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('bookings')->insert([
            'id' => $bookingId,
            'member_id' => $memberId,
            'shift_schedule_id' => $fixture['scheduleId'],
            'service_offering_id' => $fixture['serviceId'],
            'examination_site_id_snapshot' => $fixture['siteReferenceId'],
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => $service->code,
            'point_cost_snapshot' => $service->point_price,
            'point_exchange_rate_id' => DB::table('point_exchange_rates')->value('id'),
            'includes_ai_snapshot' => (bool) $service->includes_ai,
            'includes_doctor_snapshot' => (bool) $service->includes_doctor,
            'site_code_snapshot' => $site->code,
            'site_name_snapshot' => $site->display_name,
            'site_timezone_snapshot' => $site->timezone,
            'created_at' => $now,
            'confirmed_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $memberId,
            'booking_id' => $bookingId,
            'funding_source' => 'personal',
            'entry_type' => 'charge',
            'point_delta' => '-2.5000',
            'source_reference' => 'test:roster:'.$bookingId,
            'reverses_id' => null,
            'created_at' => $now,
        ]);
    }
}
