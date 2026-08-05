<?php

declare(strict_types=1);

namespace Tests\Member;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Application\Services\Mvp03ScheduleService;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use Database\Seeders\MvpBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp03BookingDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_arithmetic_and_booking_charge_are_four_decimal_and_float_free(): void
    {
        $this->assertSame('12.5000', (string) PointAmount::fromString('10.2500')->add(PointAmount::fromString('2.25')));
        $this->assertSame('7.5000', (string) PointAmount::fromString('10.2500')->subtract(PointAmount::fromString('2.75')));

        $fixture = $this->fixture('decimal@example.test', '12.5000', '20.1234');
        $this->actingAs($fixture['user']);
        $result = app(Mvp03BookingService::class)->create($fixture['member_id'], $fixture['schedule_id'], 'mvp03-decimal-request', '12.5000');

        $this->assertSame('12.5000', $result['point_cost']);
        $this->assertSame('7.6234', $result['remaining_personal_points']);
        $this->assertDatabaseHas('point_ledger_entries', ['booking_id' => $result['booking_id'], 'point_delta' => '-12.5000', 'funding_source' => 'personal']);
    }

    public function test_b2c_uses_personal_points_only_and_insufficient_balance_rolls_back_every_booking_side_effect(): void
    {
        $fixture = $this->fixture('insufficient@example.test', '12.5000', '10.0000');
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $fixture['member_id'],
            'booking_id' => null,
            'funding_source' => 'business_reserved',
            'entry_type' => 'credit',
            'point_delta' => '100.0000',
            'source_reference' => 'test:business-credit',
            'reverses_id' => null,
            'created_at' => now(),
        ]);
        $this->actingAs($fixture['user']);

        try {
            app(Mvp03BookingService::class)->create($fixture['member_id'], $fixture['schedule_id'], 'mvp03-insufficient-request');
            $this->fail('Business-funded points must not fund a B2C booking.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('tidak mencukupi', $exception->getMessage());
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('local_imaging_orders', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertDatabaseMissing('point_ledger_entries', ['entry_type' => 'charge']);
        $this->assertDatabaseHas('idempotent_consumptions', ['message_id' => 'mvp03-insufficient-request', 'status' => 'failed']);
    }

    public function test_booking_is_atomic_idempotent_and_preserves_snapshots_and_one_active_booking(): void
    {
        $fixture = $this->fixture('booking@example.test', '12.5000', '50.0000');
        $this->actingAs($fixture['user']);
        $service = app(Mvp03BookingService::class);
        $first = $service->create($fixture['member_id'], $fixture['schedule_id'], 'mvp03-same-request', '12.5000');
        $replay = $service->create($fixture['member_id'], $fixture['schedule_id'], 'mvp03-same-request', '12.5000');

        $this->assertSame($first, $replay);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('local_imaging_orders', 1);
        $this->assertDatabaseCount('outbox_messages', 1);

        DB::table('service_offerings')->where('id', $fixture['service_id'])->update(['code' => 'CHANGED', 'point_price' => '99.0000', 'includes_ai' => false]);
        DB::table('examination_site_refs')->where('id', $fixture['site_id'])->update(['display_name' => 'Changed site']);
        $snapshot = Booking::query()->findOrFail($first['booking_id']);
        $this->assertSame('SYNTHETIC', $snapshot->service_code_snapshot);
        $this->assertSame('Synthetic Site', $snapshot->site_name_snapshot);
        $this->assertSame('12.5000', (string) $snapshot->point_cost_snapshot);

        try {
            $service->create($fixture['member_id'], $fixture['schedule_id'], 'mvp03-same-request', '99.0000');
            $this->fail('Changed idempotency input must conflict.');
        } catch (IdempotencyConflict) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_schedule_overlap_boundary_and_quota_are_enforced_by_member_application_service(): void
    {
        $fixture = $this->fixture('schedule@example.test', '12.5000', '20.0000', '2040-01-10 03:00:00', '2040-01-10 04:00:00');
        $admin = User::factory()->create(['email' => 'schedule-admin@example.test']);
        $this->grant($admin, ['administrator'], ['member.schedule.read', 'member.schedule.manage']);
        $this->actingAs($admin);
        $service = app(Mvp03ScheduleService::class);

        try {
            $service->create(['examination_site_id' => $fixture['site_id'], 'service_offering_id' => $fixture['service_id'], 'starts_at' => '2040-01-10T09:30:00+07:00', 'ends_at' => '2040-01-10T10:30:00+07:00', 'quota' => 5]);
            $this->fail('Overlapping schedules must be rejected.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }

        $boundary = $service->create(['examination_site_id' => $fixture['site_id'], 'service_offering_id' => $fixture['service_id'], 'starts_at' => '2040-01-10T11:00:00+07:00', 'ends_at' => '2040-01-10T12:00:00+07:00', 'quota' => 20]);
        $this->assertSame('2040-01-10 04:00:00', $boundary->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('open', $boundary->status);
    }

    public function test_fifth_confirmed_booking_marks_schedule_eligible_and_emits_one_sanitized_event(): void
    {
        $fixture = $this->fixture('threshold-0@example.test', '1.0000', '10.0000', '2050-01-10 03:00:00', '2050-01-10 04:00:00', 5);
        $service = app(Mvp03BookingService::class);
        for ($index = 0; $index < 5; $index++) {
            $member = $index === 0 ? $fixture : $this->memberFixture('threshold-'.$index.'@example.test');
            DB::table('point_ledger_entries')->insert([
                'id' => (string) Str::uuid(),
                'member_id' => $member['member_id'],
                'booking_id' => null,
                'funding_source' => 'personal',
                'entry_type' => 'credit',
                'point_delta' => '10.0000',
                'source_reference' => 'test:threshold-credit:'.$index,
                'reverses_id' => null,
                'created_at' => now(),
            ]);
            $this->actingAs($member['user']);
            $service->create($member['member_id'], $fixture['schedule_id'], 'threshold-request-'.$index, '1.0000');
        }

        $this->assertDatabaseHas('shift_schedules', ['id' => $fixture['schedule_id']]);
        $this->assertNotNull(DB::table('shift_schedules')->where('id', $fixture['schedule_id'])->value('eligible_at'));
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'shift_eligible')->count());
        $event = DB::table('outbox_messages')->where('event_name', 'shift_eligible')->first();
        $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($fixture['schedule_id'], $payload['schedule_id']);
        $this->assertArrayNotHasKey('member_id', $payload);
        $this->assertArrayNotHasKey('point_balance', $payload);

        $sixth = $this->memberFixture('threshold-sixth@example.test');
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $sixth['member_id'],
            'booking_id' => null,
            'funding_source' => 'personal',
            'entry_type' => 'credit',
            'point_delta' => '10.0000',
            'source_reference' => 'test:threshold-credit:sixth',
            'reverses_id' => null,
            'created_at' => now(),
        ]);
        $this->actingAs($sixth['user']);
        try {
            $service->create($sixth['member_id'], $fixture['schedule_id'], 'threshold-request-sixth', '1.0000');
            $this->fail('A sixth active booking must exceed the configured quota.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('full', $exception->getMessage());
        }
        $this->assertSame(5, DB::table('bookings')->where('shift_schedule_id', $fixture['schedule_id'])->count());
    }

    public function test_local_booking_seeder_is_idempotent_and_does_not_create_accounts(): void
    {
        $member = $this->memberFixture('mvp-member-one@example.test');
        $this->seed(MvpBookingSeeder::class);
        $counts = [
            DB::table('operator_organization_refs')->count(),
            DB::table('examination_site_refs')->count(),
            DB::table('service_offerings')->count(),
            DB::table('shift_schedules')->count(),
            DB::table('point_exchange_rates')->count(),
            DB::table('point_ledger_entries')->count(),
        ];
        $this->seed(MvpBookingSeeder::class);
        $this->assertSame($counts, [
            DB::table('operator_organization_refs')->count(),
            DB::table('examination_site_refs')->count(),
            DB::table('service_offerings')->count(),
            DB::table('shift_schedules')->count(),
            DB::table('point_exchange_rates')->count(),
            DB::table('point_ledger_entries')->count(),
        ]);
        $this->assertSame(1, DB::table('point_ledger_entries')->where('source_reference', 'mvp03:synthetic-personal-credit:mvp-member-one')->count());
        $this->assertSame(1, DB::table('users')->where('email', 'mvp-member-one@example.test')->count());
        $this->assertNotNull($member['member_id']);
    }

    /** @return array<string, mixed> */
    private function fixture(string $email, string $price, string $credit, string $start = '2030-01-10 03:00:00', string $end = '2030-01-10 04:00:00', int $quota = 5): array
    {
        $member = $this->memberFixture($email);
        $organizationId = (string) Str::uuid();
        $siteId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $rateId = (string) Str::uuid();
        $now = now();
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'operator-'.$siteId, 'name' => 'Synthetic Organization', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('examination_site_refs')->insert(['id' => $siteId, 'operator_site_id' => 'site-'.$siteId, 'operator_organization_ref_id' => $organizationId, 'code' => 'SITE-'.substr($siteId, 0, 8), 'display_name' => 'Synthetic Site', 'timezone' => 'Asia/Jakarta', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('service_offerings')->insert(['id' => $serviceId, 'code' => 'SYNTHETIC', 'name' => 'Synthetic Service', 'includes_ai' => true, 'includes_doctor' => true, 'point_price' => $price, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('point_exchange_rates')->insert(['id' => $rateId, 'rupiah_per_point' => 10000, 'status' => 'active', 'effective_at' => $now, 'configured_by_admin_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'examination_site_id' => $siteId, 'service_offering_id' => $serviceId, 'starts_at' => $start, 'ends_at' => $end, 'quota' => $quota, 'status' => 'open', 'eligible_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('point_ledger_entries')->insert(['id' => (string) Str::uuid(), 'member_id' => $member['member_id'], 'booking_id' => null, 'funding_source' => 'personal', 'entry_type' => 'credit', 'point_delta' => $credit, 'source_reference' => 'test:credit:'.$email, 'reverses_id' => null, 'created_at' => $now]);

        return $member + ['site_id' => $siteId, 'service_id' => $serviceId, 'schedule_id' => $scheduleId, 'rate_id' => $rateId];
    }

    /** @return array{user: User, member_id: string} */
    private function memberFixture(string $email): array
    {
        $user = User::factory()->create(['email' => $email]);
        $memberId = (string) Str::uuid();
        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.Str::upper(Str::random(10)),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'protected',
            'nik_lookup_digest' => hash('sha256', $memberId),
            'name' => 'Synthetic Member',
            'birth_date' => '1988-01-01',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Synthetic address',
            'emergency_contact_name' => 'Synthetic contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '0800000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['user' => $user->fresh(), 'member_id' => $memberId];
    }

    /** @param list<string> $roles @param list<string> $permissions */
    private function grant(User $user, array $roles, array $permissions): void
    {
        foreach ($roles as $role) {
            DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => $role, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission' => $permission, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
