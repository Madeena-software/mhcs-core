<?php

declare(strict_types=1);

namespace Tests\Operator;

use App\Models\User;
use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Services\Mvp04OperatorSiteReferenceService;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Operator\Application\Services\EligibleShiftIntakeService;
use App\Modules\Operator\Application\Services\OperatorActiveSiteService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Modules\Operator\Application\Services\OperatorSiteAssignmentService;
use App\Modules\Operator\Application\Services\OperatorSiteService;
use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use App\Modules\Operator\Domain\Models\OperatorShiftAssignment;
use App\Modules\Operator\Domain\OperatorException;
use App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\OperatorShiftAssignmentResource;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use Database\Seeders\MvpOperatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp04OperatorFoundationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_eligible_shift_intake_is_idempotent_and_rejects_changed_or_unknown_payloads(): void
    {
        $fixture = $this->operatorFixture();
        $service = app(EligibleShiftIntakeService::class);
        $scheduleId = (string) Str::uuid();
        $payload = [
            'schedule_id' => $scheduleId,
            'operator_site_id' => $fixture['siteStableId'],
            'starts_at' => '2040-01-11T03:00:00+00:00',
            'ends_at' => '2040-01-11T04:00:00+00:00',
            'confirmed_count' => 5,
            'quota' => 5,
            'event_version' => 1,
        ];

        $created = $service->consume('event-intake-1', $payload);
        $replay = $service->consume('event-intake-1', $payload);
        $this->assertSame($created, $replay);
        $this->assertDatabaseCount('operator_eligible_shifts', 2);

        $this->expectException(IdempotencyConflict::class);
        $service->consume('event-intake-1', [...$payload, 'quota' => 6]);
    }

    public function test_eligible_shift_intake_normalizes_post_2038_explicit_offset_projection_instants(): void
    {
        $fixture = $this->operatorFixture(false);
        $created = app(EligibleShiftIntakeService::class)->consume('event-post-2038', [
            'schedule_id' => (string) Str::uuid(),
            'operator_site_id' => $fixture['siteStableId'],
            'starts_at' => '2040-01-11T10:00:00+07:00',
            'ends_at' => '2040-01-11T11:00:00+07:00',
            'confirmed_count' => 5,
            'quota' => 5,
            'event_version' => 1,
        ]);

        $stored = OperatorEligibleShift::query()->findOrFail($created['eligible_shift_id']);
        $this->assertSame('2040-01-11 03:00:00', $stored->schedule_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2040-01-11 04:00:00', $stored->schedule_ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('operator_eligible_shifts', [
            'id' => $created['eligible_shift_id'],
            'schedule_starts_at' => '2040-01-11 03:00:00',
            'schedule_ends_at' => '2040-01-11 04:00:00',
        ]);
    }

    public function test_arrival_uses_trusted_site_and_operation_identity_and_moves_member_booking_once(): void
    {
        $fixture = $this->operatorFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        app(OperatorActiveSiteService::class)->select($fixture['siteLocalId']);
        $service = app(OperatorArrivalService::class);

        $confirmation = $service->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        $first = $service->recordConfirmed($confirmation['confirmation_token']);
        $replay = $service->recordConfirmed($confirmation['confirmation_token']);

        $this->assertSame($first, $replay);
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'arrived']);
        $this->assertDatabaseCount('operator_arrivals', 1);
        $arrival = DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertSame('2040-01-10 03:15:00', $arrival->occurrence_at);
        $this->assertNotSame($arrival->occurrence_at, $arrival->recorded_at);

        $this->assertSame($first['arrival_id'], $replay['arrival_id']);
    }

    public function test_shift_assignment_uses_shift_manage_and_stays_separate_from_site_assignment_manage(): void
    {
        $fixture = $this->operatorFixture();
        $this->actingAs($fixture['operator']);
        $shiftAssignments = app(OperatorShiftAssignmentService::class);
        $assignment = OperatorShiftAssignment::query()->where('operator_eligible_shift_id', $fixture['eligibleId'])->where('operator_profile_id', $fixture['profileId'])->firstOrFail();

        DB::table('authorization_permission_assignments')->where('user_id', $fixture['operator']->id)->where('permission', 'operator.assignment.manage')->update(['active' => false]);
        $this->assertTrue(OperatorShiftAssignmentResource::canCreate());
        $shiftAssignments->revoke($assignment, 'permission separation test');
        $shiftAssignments->assign($fixture['eligibleId'], $fixture['profileId']);
        $this->assertDatabaseHas('operator_shift_assignments', ['operator_eligible_shift_id' => $fixture['eligibleId'], 'operator_profile_id' => $fixture['profileId'], 'status' => 'active']);

        try {
            app(OperatorSiteAssignmentService::class)->assign($fixture['profileId'], $fixture['siteLocalId']);
            $this->fail('Site assignment unexpectedly used shift permission.');
        } catch (OperatorException) {
            $this->assertTrue(true);
        }

        DB::table('authorization_permission_assignments')->where('user_id', $fixture['operator']->id)->where('permission', 'operator.shift.manage')->update(['active' => false]);
        $this->assertFalse(OperatorShiftAssignmentResource::canCreate());
        $auditCount = DB::table('audit_events')->where('action', 'operator.shift-assignment.create')->where('outcome', 'success')->count();

        try {
            $shiftAssignments->assign($fixture['eligibleId'], $fixture['profileId']);
            $this->fail('Shift assignment unexpectedly used assignment permission.');
        } catch (OperatorException) {
            $this->assertSame($auditCount, DB::table('audit_events')->where('action', 'operator.shift-assignment.create')->where('outcome', 'success')->count());
        }
    }

    public function test_recorded_arrival_does_not_block_site_switching(): void
    {
        $fixture = $this->operatorFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $sites = app(OperatorActiveSiteService::class);
        $sites->select($fixture['siteLocalId']);
        $arrival = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        app(OperatorArrivalService::class)->recordConfirmed($arrival['confirmation_token']);

        $secondSiteId = $this->addSecondSite($fixture);

        $sites->select($secondSiteId);
        $this->assertSame($secondSiteId, session('operator.active_site_id'));
        $this->assertDatabaseHas('audit_events', ['action' => 'operator.active-site.switch', 'outcome' => 'success']);
        $this->assertDatabaseMissing('operator_arrivals', ['status' => 'resolved']);
    }

    public function test_active_unconsumed_confirmation_blocks_switch_and_preserves_site(): void
    {
        $fixture = $this->operatorFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $sites = app(OperatorActiveSiteService::class);
        $sites->select($fixture['siteLocalId']);
        $confirmation = app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
        $secondSiteId = $this->addSecondSite($fixture);

        try {
            $sites->select($secondSiteId);
            $this->fail('An unconsumed arrival confirmation did not block switching.');
        } catch (OperatorException $exception) {
            $this->assertSame('active_site_blocked', $exception->category);
        }

        $this->assertSame($fixture['siteLocalId'], session('operator.active_site_id'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'operator.active-site.switch',
            'outcome' => 'failure',
            'reason' => 'active_site_blocked',
        ]);
        $this->assertStringNotContainsString($confirmation['confirmation_token'], (string) DB::table('audit_events')->where('action', 'operator.active-site.switch')->latest('created_at')->value('metadata'));
    }

    public function test_cancelled_expired_and_malformed_confirmation_permit_switch_and_clear_state(): void
    {
        $fixture = $this->operatorFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        $sites = app(OperatorActiveSiteService::class);
        $service = app(OperatorArrivalService::class);
        $secondSiteId = $this->addSecondSite($fixture);

        foreach (['cancelled', 'expired', 'malformed'] as $case) {
            $sites->select($fixture['siteLocalId']);
            $confirmation = $service->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');

            if ($case === 'cancelled') {
                $service->cancelConfirmation($confirmation['confirmation_token']);
            } elseif ($case === 'expired') {
                session()->put('operator.arrival_confirmation', [...session('operator.arrival_confirmation'), 'expires_at' => '2020-01-10T03:00:00+00:00']);
            } else {
                session()->put('operator.arrival_confirmation', ['token' => 'malformed']);
            }

            $sites->select($secondSiteId);
            $this->assertSame([], session('operator.arrival_confirmation', []));
        }
    }

    public function test_operator_arrival_service_has_no_public_unconfirmed_record_command(): void
    {
        $reflection = new \ReflectionClass(OperatorArrivalService::class);
        $method = $reflection->getMethod('recordUnconfirmed');

        $this->assertFalse($reflection->hasMethod('record'));
        $this->assertFalse($method->isPublic());
    }

    public function test_member_attendance_requires_local_and_stable_operator_site_correspondence(): void
    {
        $fixture = $this->operatorFixture(false);
        $context = $this->operatorContext($fixture);
        $attendance = app(OperatorAttendanceContract::class);

        $this->assertCount(1, $attendance->query($context, $fixture['siteStableId'], $fixture['scheduleId'], '2040-01-10T10:15:00+07:00'));
        $auditCount = DB::table('audit_events')->count();

        try {
            $attendance->query($context, 'operator-site-other', $fixture['scheduleId'], '2040-01-10T10:15:00+07:00');
            $this->fail('Mismatched stable site was accepted by attendance.');
        } catch (Mvp03Exception) {
            $this->assertSame($auditCount, DB::table('audit_events')->count());
        }

        try {
            $attendance->resolveBookingForArrival($context, 'operator-site-other', $fixture['bookingId'], '2040-01-10T10:15:00+07:00');
            $this->fail('Mismatched stable site was accepted by arrival resolution.');
        } catch (Mvp03Exception) {
            $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);
        }
    }

    public function test_local_poc_attendance_allows_a_time_before_the_displayed_schedule_start(): void
    {
        $fixture = $this->operatorFixture(false);
        $context = $this->operatorContext($fixture);
        $attendance = app(OperatorAttendanceContract::class);

        $this->assertCount(1, $attendance->query($context, $fixture['siteStableId'], $fixture['scheduleId'], '2040-01-09T10:15:00+07:00'));
    }

    public function test_member_transition_rejects_mismatched_site_without_side_effects(): void
    {
        $fixture = $this->operatorFixture(false);
        $context = $this->operatorContext($fixture);
        $attendance = app(OperatorAttendanceContract::class);
        $eventCount = DB::table('booking_status_events')->count();
        $outboxCount = DB::table('outbox_messages')->count();

        try {
            $attendance->transitionConfirmedToArrived(
                $context,
                'operator-site-other',
                $fixture['bookingId'],
                '2040-01-10T10:15:00+07:00',
                '2040-01-10T03:15:00+00:00',
                'mismatch-operation',
            );
            $this->fail('Mismatched stable site was accepted by Member transition.');
        } catch (Mvp03Exception) {
            $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);
            $this->assertDatabaseCount('operator_arrivals', 0);
            $this->assertSame($eventCount, DB::table('booking_status_events')->count());
            $this->assertSame($outboxCount, DB::table('outbox_messages')->count());
        }
    }

    public function test_inactive_operator_assignment_fails_trusted_site_resolution(): void
    {
        $fixture = $this->operatorFixture(false);
        DB::table('operator_site_assignments')->where('operator_profile_id', $fixture['profileId'])->update(['active' => false]);

        try {
            app(OperatorAttendanceContract::class)->query($this->operatorContext($fixture), $fixture['siteStableId'], $fixture['scheduleId'], '2040-01-10T10:15:00+07:00');
            $this->fail('An inactive Operator assignment granted site authority.');
        } catch (Mvp03Exception $exception) {
            $this->assertSame('A trusted Operator attendance context is required.', $exception->getMessage());
        }
    }

    public function test_uncharged_bookings_cannot_create_arrival_side_effects(): void
    {
        $this->assertIneligibleArrival(static function (array $fixture): void {
            DB::table('point_ledger_entries')->where('booking_id', $fixture['bookingId'])->delete();
        }, 'uncharged');
    }

    public function test_unsupported_funding_cannot_create_arrival_side_effects(): void
    {
        $this->assertIneligibleArrival(static function (array $fixture): void {
            DB::table('bookings')->where('id', $fixture['bookingId'])->update(['funding_source' => 'business']);
        }, 'unsupported');
    }

    /** @param callable(array<string, mixed>): void $mutate */
    private function assertIneligibleArrival(callable $mutate, string $case): void
    {
        $fixture = $this->operatorFixture();
        $mutate($fixture);
        $this->startSession();
        $this->actingAs($fixture['operator']);
        app(OperatorActiveSiteService::class)->select($fixture['siteLocalId']);
        $eventCount = DB::table('booking_status_events')->count();
        $outboxCount = DB::table('outbox_messages')->count();

        try {
            app(OperatorArrivalService::class)->confirm($fixture['bookingId'], '2040-01-10T10:15:00+07:00');
            $this->fail("{$case} booking unexpectedly arrived.");
        } catch (\Throwable) {
            $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'confirmed']);
            $this->assertDatabaseCount('operator_arrivals', 0);
            $this->assertSame($eventCount, DB::table('booking_status_events')->count());
            $this->assertSame($outboxCount, DB::table('outbox_messages')->count());
            $this->assertSame(0, DB::table('audit_events')->where('action', 'operator.arrival.record')->where('outcome', 'success')->count());
        }
    }

    public function test_site_mutation_syncs_member_reference_and_deactivation_preserves_existing_schedule(): void
    {
        $admin = $this->operatorFixture()['operator'];
        $this->actingAs($admin);
        DB::table('authorization_role_assignments')->where('user_id', $admin->id)->where('role', 'operator')->delete();
        DB::table('authorization_permission_assignments')->where('user_id', $admin->id)->where('permission', 'operator.portal.access')->delete();
        $site = app(OperatorSiteService::class)->create([
            'operator_site_id' => 'operator-site-admin-created',
            'organization_id' => 'operator-org-admin-created',
            'organization_name' => 'Admin Organization',
            'code' => 'ADMIN-SITE',
            'display_name' => 'Admin Site',
            'timezone' => 'Asia/Jakarta',
            'source_version' => '2',
            'active' => true,
        ]);
        $memberSite = DB::table('examination_site_refs')->where('operator_site_id', $site->operator_site_id)->first();
        $scheduleId = (string) Str::uuid();
        DB::table('service_offerings')->insert(['id' => (string) Str::uuid(), 'code' => 'ADMIN-RAD', 'name' => 'Admin Radiography', 'includes_ai' => false, 'includes_doctor' => false, 'point_price' => '1.0000', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $serviceId = (string) DB::table('service_offerings')->where('code', 'ADMIN-RAD')->value('id');
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'display_reference' => 'JAD-'.Str::upper(Str::random(8)), 'examination_site_id' => $memberSite->id, 'service_offering_id' => $serviceId, 'starts_at' => '2040-02-01 03:00:00', 'ends_at' => '2040-02-01 04:00:00', 'quota' => 5, 'status' => 'open', 'eligible_at' => null, 'created_at' => now(), 'updated_at' => now()]);

        app(OperatorSiteService::class)->setActive($site, false);

        $this->assertDatabaseHas('examination_site_refs', ['id' => $memberSite->id, 'active' => false]);
        $this->assertDatabaseHas('shift_schedules', ['id' => $scheduleId]);
        $this->assertDatabaseHas('audit_events', ['action' => 'operator.site.update', 'outcome' => 'success']);
        $this->assertDatabaseHas('audit_events', ['action' => 'member.site-reference.synchronized', 'outcome' => 'success']);
    }

    public function test_site_reference_command_rejects_changed_same_version_replay(): void
    {
        $fixture = $this->operatorFixture();
        $service = app(Mvp04OperatorSiteReferenceService::class);

        $this->expectException(Mvp03Exception::class);
        $service->synchronize($fixture['organizationStableId'], 'Changed organization', $fixture['siteStableId'], 'CHANGED', 'Changed site', 'Asia/Jakarta', true, '1');
    }

    public function test_local_operator_seeder_is_idempotent_and_does_not_change_shared_credentials(): void
    {
        $user = User::factory()->create(['email' => 'mvp-admin@example.test']);
        $passwordHash = $user->password;
        $organizationId = (string) Str::uuid();
        $siteReferenceId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'synthetic-operator-org-mvp03', 'name' => 'Synthetic Operator Organization', 'source_version' => 'mvp04-v1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('examination_site_refs')->insert(['id' => $siteReferenceId, 'operator_site_id' => 'synthetic-operator-site-mvp03', 'operator_organization_ref_id' => $organizationId, 'code' => 'SYN-MVP03', 'display_name' => 'Synthetic MVP-03 site', 'timezone' => 'Asia/Jakarta', 'source_version' => 'mvp04-v1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('service_offerings')->insert(['id' => $serviceId, 'code' => 'SEED-RAD', 'name' => 'Seed Radiography', 'includes_ai' => false, 'includes_doctor' => false, 'point_price' => '1.0000', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'display_reference' => 'JAD-'.Str::upper(Str::random(8)), 'examination_site_id' => $siteReferenceId, 'service_offering_id' => $serviceId, 'starts_at' => '2040-03-01 03:00:00', 'ends_at' => '2040-03-01 04:00:00', 'quota' => 5, 'status' => 'open', 'eligible_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->seed(MvpOperatorSeeder::class);
        $counts = [
            'operator_sites' => DB::table('operator_sites')->count(),
            'operator_profiles' => DB::table('operator_profiles')->count(),
            'operator_site_assignments' => DB::table('operator_site_assignments')->count(),
            'operator_eligible_shifts' => DB::table('operator_eligible_shifts')->count(),
            'operator_shift_assignments' => DB::table('operator_shift_assignments')->count(),
        ];
        $this->seed(MvpOperatorSeeder::class);

        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
        $this->assertSame($passwordHash, $user->refresh()->password);
        $this->assertDatabaseMissing('operator_arrivals', []);
    }

    /** @param array<string, mixed> $fixture */
    private function addSecondSite(array $fixture): string
    {
        $secondSiteId = (string) Str::uuid();
        DB::table('operator_sites')->insert([
            'id' => $secondSiteId,
            'operator_site_id' => 'operator-site-second',
            'organization_id' => 'operator-org-second',
            'organization_name' => 'Second organization',
            'code' => 'SECOND-SITE',
            'display_name' => 'Second site',
            'address_line' => null,
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'source_version' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $fixture['profileId'],
            'operator_site_id' => $secondSiteId,
            'active' => true,
            'assigned_by_user_id' => $fixture['operator']->id,
            'assigned_at' => now(),
            'revoked_at' => null,
            'reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $secondSiteId;
    }

    /** @param array<string, mixed> $fixture */
    private function operatorContext(array $fixture): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: LocalId::fromString((string) $fixture['operator']->id),
            operationId: new CorrelationId('test-operation-'.Str::uuid()),
            roles: ['operator'],
            permissions: ['operator.attendance.read', 'operator.arrival.record'],
            siteId: LocalId::fromString($fixture['siteLocalId']),
            purpose: 'operator.attendance.read',
        );
    }
}
