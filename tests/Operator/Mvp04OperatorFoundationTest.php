<?php

declare(strict_types=1);

namespace Tests\Operator;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp04OperatorSiteReferenceService;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Operator\Application\Services\EligibleShiftIntakeService;
use App\Modules\Operator\Application\Services\OperatorActiveSiteService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorSiteService;
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

    public function test_arrival_uses_trusted_site_and_operation_identity_and_moves_member_booking_once(): void
    {
        $fixture = $this->operatorFixture();
        $this->startSession();
        $this->actingAs($fixture['operator']);
        app(OperatorActiveSiteService::class)->select($fixture['siteLocalId']);
        $service = app(OperatorArrivalService::class);

        $first = $service->record($fixture['bookingId'], '2040-01-10T10:15:00+07:00', 'arrival-operation-1');
        $replay = $service->record($fixture['bookingId'], '2040-01-10T10:15:00+07:00', 'arrival-operation-1');

        $this->assertSame($first, $replay);
        $this->assertDatabaseHas('bookings', ['id' => $fixture['bookingId'], 'status' => 'arrived']);
        $this->assertDatabaseCount('operator_arrivals', 1);
        $arrival = DB::table('operator_arrivals')->where('booking_id', $fixture['bookingId'])->first();
        $this->assertSame('2040-01-10 03:15:00', $arrival->occurrence_at);
        $this->assertNotSame($arrival->occurrence_at, $arrival->recorded_at);

        $this->expectException(IdempotencyConflict::class);
        $service->record($fixture['bookingId'], '2040-01-10T10:16:00+07:00', 'arrival-operation-1');
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
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'examination_site_id' => $memberSite->id, 'service_offering_id' => $serviceId, 'starts_at' => '2040-02-01 03:00:00', 'ends_at' => '2040-02-01 04:00:00', 'quota' => 5, 'status' => 'open', 'eligible_at' => null, 'created_at' => now(), 'updated_at' => now()]);

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
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'examination_site_id' => $siteReferenceId, 'service_offering_id' => $serviceId, 'starts_at' => '2040-03-01 03:00:00', 'ends_at' => '2040-03-01 04:00:00', 'quota' => 5, 'status' => 'open', 'eligible_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

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
}
