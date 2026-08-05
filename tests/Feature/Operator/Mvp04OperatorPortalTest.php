<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04OperatorPortalTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

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

    public function test_arrival_route_requires_explicit_offset_and_places_successful_arrivals_in_worklist(): void
    {
        $fixture = $this->operatorFixture(false);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $this->withSession(['operator.active_site_id' => $fixture['siteLocalId']]);

        $this->post('/operator/arrivals', [
            'booking_id' => $fixture['bookingId'],
            'schedule_id' => $fixture['scheduleId'],
            'occurrence_at' => '2040-01-10T10:15:00',
            'idempotency_key' => 'portal-arrival-invalid-time',
        ])->assertSessionHasErrors('arrival');
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);

        $this->post('/operator/arrivals', [
            'booking_id' => $fixture['bookingId'],
            'schedule_id' => $fixture['scheduleId'],
            'occurrence_at' => '2040-01-10T10:15:00+07:00',
            'idempotency_key' => 'portal-arrival-success',
        ])->assertRedirect(route('operator.attendance', $fixture['scheduleId']));

        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'arrived']);
        $this->get('/operator/verification-worklist')->assertOk()->assertSee('Synthetic Arrival Member')->assertSee('pending_verification');
        $this->assertDatabaseMissing('operator_arrivals', ['booking_id' => $fixture['bookingId'], 'status' => 'pending']);
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
