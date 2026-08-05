<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
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
            ->assertDontSee('900000000001')
            ->assertDontSee('authorization_permission_assignments')
            ->assertDontSee('point balance');
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
        ])->assertRedirect(route('operator.attendance', $fixture['scheduleId']));

        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'arrived']);
        $this->get('/operator/verification-worklist')->assertOk()->assertSee('Synthetic Arrival Member')->assertSee('pending_verification');
        $this->assertDatabaseMissing('operator_arrivals', ['booking_id' => $fixture['bookingId'], 'status' => 'pending']);

        $this->post('/operator/arrivals', ['confirmation_token' => $token])->assertRedirect(route('operator.attendance', $fixture['scheduleId']));
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
}
