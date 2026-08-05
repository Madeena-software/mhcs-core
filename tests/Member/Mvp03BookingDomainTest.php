<?php

declare(strict_types=1);

namespace Tests\Member;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Application\Services\Mvp03ScheduleService;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Member\Domain\Mvp03BookingFailure;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Events\DomainEvent;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use Database\Seeders\MvpBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp03BookingDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_comparison_handles_signs_negative_zero_and_large_magnitudes_without_numeric_coercion(): void
    {
        $this->assertGreaterThan(0, PointAmount::fromString('-1.0000')->compare(PointAmount::fromString('-2.0000')));
        $this->assertLessThan(0, PointAmount::fromString('-2.0000')->compare(PointAmount::fromString('-1.0000')));
        $this->assertLessThan(0, PointAmount::fromString('-1.0001')->compare(PointAmount::fromString('-1.0000')));
        $this->assertSame(0, PointAmount::fromString('0.0000')->compare(PointAmount::fromString('-0.0000')));
        $this->assertGreaterThan(0, PointAmount::fromString('9999999999999999.9999')->compare(PointAmount::fromString('9999999999999999.9998')));
        $this->assertLessThan(0, PointAmount::fromString('9999999999999999.9998')->compare(PointAmount::fromString('9999999999999999.9999')));
    }

    public function test_point_arithmetic_and_booking_charge_are_four_decimal_and_float_free(): void
    {
        $this->assertSame('12.5000', (string) PointAmount::fromString('10.2500')->add(PointAmount::fromString('2.25')));
        $this->assertSame('7.5000', (string) PointAmount::fromString('10.2500')->subtract(PointAmount::fromString('2.75')));

        $fixture = $this->fixture('decimal@example.test', '12.5000', '20.1234');
        $this->actingAs($fixture['user']);
        $result = app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-decimal-request', '12.5000');

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
            app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-insufficient-request');
            $this->fail('Business-funded points must not fund a B2C booking.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('tidak mencukupi', $exception->getMessage());
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('local_imaging_orders', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertDatabaseMissing('point_ledger_entries', ['entry_type' => 'charge']);
        $this->assertDatabaseHas('idempotent_consumptions', ['message_id' => 'mvp03-insufficient-request', 'status' => 'failed']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.booking.failed',
            'target_id' => $fixture['member_id'],
            'reason' => 'insufficient_personal_points',
        ]);
    }

    public function test_booking_is_atomic_idempotent_and_preserves_snapshots_and_one_active_booking(): void
    {
        $fixture = $this->fixture('booking@example.test', '12.5000', '50.0000');
        $this->actingAs($fixture['user']);
        $service = app(Mvp03BookingService::class);
        $first = $service->createForCurrentMember($fixture['schedule_id'], 'mvp03-same-request', '12.5000');
        $replay = $service->createForCurrentMember($fixture['schedule_id'], 'mvp03-same-request', '12.5000');

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
            $service->createForCurrentMember($fixture['schedule_id'], 'mvp03-same-request', '99.0000');
            $this->fail('Changed idempotency input must conflict.');
        } catch (IdempotencyConflict) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.booking.failed',
            'target_id' => $fixture['member_id'],
            'reason' => 'idempotency_conflict',
        ]);
    }

    public function test_booking_uses_the_authenticated_member_even_when_request_ids_name_another_member(): void
    {
        $fixture = $this->fixture('owner@example.test', '2.5000', '10.0000');
        $other = $this->memberFixture('other-owner@example.test');
        $this->actingAs($fixture['user']);

        $this->post('/member/bookings', [
            'schedule_id' => $fixture['schedule_id'],
            'point_cost' => '2.5000',
            'confirmation' => '1',
            'idempotency_key' => 'mvp03-owner-bound',
            'member_id' => $other['member_id'],
            'user_id' => $other['user']->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'member_id' => $fixture['member_id'],
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseMissing('bookings', ['member_id' => $other['member_id']]);
    }

    public function test_booking_service_denies_each_required_actor_state_without_partial_success(): void
    {
        foreach (['anonymous', 'administrator-only', 'missing-member', 'suspended', 'login-disabled', 'mandatory-change', 'child', 'identity-incomplete', 'profile-incomplete'] as $state) {
            $fixture = $this->fixture('actor-'.$state.'@example.test', '2.5000', '10.0000', code: 'STATE-'.strtoupper(str_replace('-', '_', $state)));
            $memberId = null;

            if ($state === 'anonymous') {
                Auth::logout();
            } elseif ($state === 'administrator-only') {
                $actor = User::factory()->create(['email' => 'admin-only-actor@example.test']);
                $this->grant($actor, ['administrator'], []);
                $this->actingAs($actor);
            } elseif ($state === 'missing-member') {
                $this->actingAs(User::factory()->create(['email' => 'missing-member-actor@example.test']));
            } else {
                $memberId = $fixture['member_id'];
                $this->actingAs($fixture['user']);
                match ($state) {
                    'suspended' => DB::table('users')->where('id', $fixture['user']->id)->update(['account_status' => 'suspended']),
                    'login-disabled' => DB::table('users')->where('id', $fixture['user']->id)->update(['login_enabled' => false]),
                    'mandatory-change' => DB::table('users')->where('id', $fixture['user']->id)->update(['must_change_password' => true]),
                    'child' => DB::table('members')->where('id', $fixture['member_id'])->update(['birth_date' => '2010-01-01']),
                    'identity-incomplete' => DB::table('members')->where('id', $fixture['member_id'])->update(['identity_status' => 'pending']),
                    'profile-incomplete' => DB::table('members')->where('id', $fixture['member_id'])->update(['current_address' => null]),
                    default => null,
                };
            }

            try {
                app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'actor-state-'.$state, '2.5000');
                $this->fail("The {$state} actor must be denied.");
            } catch (\Throwable) {
                $this->addToAssertionCount(1);
            }

            $this->assertDatabaseCount('bookings', 0);
            $this->assertDatabaseCount('local_imaging_orders', 0);
            $this->assertDatabaseCount('outbox_messages', 0);
            $this->assertDatabaseMissing('point_ledger_entries', ['entry_type' => 'charge']);
            $this->assertDatabaseMissing('audit_events', ['action' => 'member.booking.confirmed']);
            $this->assertDatabaseMissing('audit_events', ['action' => 'member.point-charge']);
            $this->assertDatabaseMissing('audit_events', ['action' => 'member.imaging-order.create']);
            $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => 'actor-state-'.$state, 'status' => 'handled']);

            if ($memberId === null) {
                $this->assertDatabaseMissing('audit_events', ['action' => 'member.booking.failed', 'reason' => 'member_unavailable']);
            } else {
                $failure = DB::table('audit_events')->where('action', 'member.booking.failed')->where('target_id', $memberId)->first();
                $this->assertNotNull($failure);
                $this->assertContains($failure->reason, Mvp03BookingFailure::CATEGORIES);
                $this->assertSame(Member::class, $failure->target_type);
                $this->assertSame('[]', $failure->metadata);
            }
        }
    }

    public function test_an_eligible_dual_role_member_books_for_the_trusted_member_only(): void
    {
        $fixture = $this->fixture('dual-role@example.test', '2.5000', '10.0000');
        $other = $this->memberFixture('dual-role-other@example.test');
        $this->grant($fixture['user'], ['administrator'], []);
        $this->actingAs($fixture['user']);

        $result = app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'dual-role-booking', '2.5000');

        $this->assertDatabaseHas('bookings', ['id' => $result['booking_id'], 'member_id' => $fixture['member_id']]);
        $this->assertDatabaseMissing('bookings', ['member_id' => $other['member_id']]);
    }

    public function test_booking_failure_categories_are_sanitized_and_unexpected_failures_roll_back(): void
    {
        $fixture = $this->fixture('unexpected@example.test', '2.5000', '10.0000');
        $this->actingAs($fixture['user']);
        $this->app->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new \RuntimeException('raw exception marker must not enter audit');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });

        try {
            app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-unexpected-failure', '2.5000');
            $this->fail('Unexpected booking failures must be reported.');
        } catch (Mvp03Exception|Mvp03BookingFailure $exception) {
            $this->assertStringNotContainsString('raw exception marker', $exception->getMessage());
        }
        $this->app->forgetInstance(OutboxStore::class);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('local_imaging_orders', 0);
        $this->assertDatabaseMissing('audit_events', ['reason' => 'raw exception marker must not enter audit']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.booking.failed',
            'target_id' => $fixture['member_id'],
            'reason' => 'unexpected_failure',
        ]);
    }

    public function test_stale_price_and_active_booking_failures_are_audited_by_controlled_category(): void
    {
        $fixture = $this->fixture('controlled-failure@example.test', '2.5000', '10.0000');
        $this->actingAs($fixture['user']);

        try {
            app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-stale-price', '3.0000');
            $this->fail('A stale displayed price must be rejected.');
        } catch (Mvp03BookingFailure $exception) {
            $this->assertSame('price_changed', $exception->category);
        }

        app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-active-booking', '2.5000');
        try {
            app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-active-booking-retry', '2.5000');
            $this->fail('An active booking must block another booking.');
        } catch (Mvp03BookingFailure $exception) {
            $this->assertSame('active_booking_exists', $exception->category);
        }

        $this->assertDatabaseHas('audit_events', ['action' => 'member.booking.failed', 'target_id' => $fixture['member_id'], 'reason' => 'price_changed']);
        $this->assertDatabaseHas('audit_events', ['action' => 'member.booking.failed', 'target_id' => $fixture['member_id'], 'reason' => 'active_booking_exists']);
    }

    public function test_booked_schedule_freezes_appointment_fields_but_allows_noop_and_close(): void
    {
        $fixture = $this->fixture('schedule-integrity@example.test', '2.5000', '10.0000', '2040-01-10 03:00:00', '2040-01-10 04:00:00');
        $this->actingAs($fixture['user']);
        app(Mvp03BookingService::class)->createForCurrentMember($fixture['schedule_id'], 'mvp03-schedule-integrity', '2.5000');

        $otherSiteId = (string) Str::uuid();
        $otherServiceId = (string) Str::uuid();
        $now = now();
        DB::table('examination_site_refs')->insert([
            'id' => $otherSiteId,
            'operator_site_id' => 'site-'.$otherSiteId,
            'operator_organization_ref_id' => DB::table('examination_site_refs')->where('id', $fixture['site_id'])->value('operator_organization_ref_id'),
            'code' => 'OTHER-'.substr($otherSiteId, 0, 8),
            'display_name' => 'Other Site',
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('service_offerings')->insert([
            'id' => $otherServiceId,
            'code' => 'OTHER-'.substr($otherServiceId, 0, 8),
            'name' => 'Other Service',
            'includes_ai' => false,
            'includes_doctor' => false,
            'point_price' => '3.0000',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $admin = User::factory()->create(['email' => 'schedule-integrity-admin@example.test']);
        $this->grant($admin, ['administrator'], ['member.schedule.read', 'member.schedule.manage']);
        $this->actingAs($admin);
        $service = app(Mvp03ScheduleService::class);
        $record = ShiftSchedule::query()->findOrFail($fixture['schedule_id']);
        $before = DB::table('audit_events')->where('action', 'member.schedule.update')->count();
        $service->update($record, []);

        foreach ([
            ['examination_site_id' => $otherSiteId],
            ['service_offering_id' => $otherServiceId],
            ['starts_at' => '2040-01-10T11:00:00+07:00'],
            ['ends_at' => '2040-01-10T12:00:00+07:00'],
            ['quota' => 6],
        ] as $change) {
            try {
                $service->update($record, $change);
                $this->fail('Booked schedule appointment data must be immutable.');
            } catch (Mvp03Exception) {
                $this->addToAssertionCount(1);
            }
            $this->assertSame($before + 1, DB::table('audit_events')->where('action', 'member.schedule.update')->count());
        }

        $service->update($record, ['status' => 'closed']);
        $stored = DB::table('shift_schedules')->where('id', $fixture['schedule_id'])->first();
        $this->assertSame('closed', $stored->status);
        $this->assertSame('2040-01-10 03:00:00', $stored->starts_at);
        $this->assertSame('2040-01-10 04:00:00', $stored->ends_at);
        $this->assertSame(5, (int) $stored->quota);
    }

    public function test_unbooked_schedule_can_be_updated_through_the_application_service(): void
    {
        $fixture = $this->fixture('unbooked-schedule@example.test', '2.5000', '10.0000', '2040-01-11 03:00:00', '2040-01-11 04:00:00', 20);
        $admin = User::factory()->create(['email' => 'unbooked-schedule-admin@example.test']);
        $this->grant($admin, ['administrator'], ['member.schedule.read', 'member.schedule.manage']);
        $this->actingAs($admin);

        $updated = app(Mvp03ScheduleService::class)->update(ShiftSchedule::query()->findOrFail($fixture['schedule_id']), ['quota' => 5]);
        $this->assertSame(5, $updated->quota);
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
            $service->createForCurrentMember($fixture['schedule_id'], 'threshold-request-'.$index, '1.0000');
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
            $service->createForCurrentMember($fixture['schedule_id'], 'threshold-request-sixth', '1.0000');
            $this->fail('A sixth active booking must exceed the configured quota.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('full', $exception->getMessage());
        }
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.booking.failed',
            'target_id' => $sixth['member_id'],
            'reason' => 'capacity_full',
        ]);
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
    private function fixture(string $email, string $price, string $credit, string $start = '2030-01-10 03:00:00', string $end = '2030-01-10 04:00:00', int $quota = 5, string $code = 'SYNTHETIC'): array
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
        DB::table('service_offerings')->insert(['id' => $serviceId, 'code' => $code, 'name' => 'Synthetic Service', 'includes_ai' => true, 'includes_doctor' => true, 'point_price' => $price, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
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
