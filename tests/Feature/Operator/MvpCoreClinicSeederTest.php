<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Database\Seeders\MvpCoreClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MvpCoreClinicSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_core_seeder_creates_a_repeatable_front_desk_booking_and_authorized_operator(): void
    {
        $this->seed(MvpCoreClinicSeeder::class);
        $this->seed(MvpCoreClinicSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'mvp-member-one@example.test']);
        $this->assertSame(5, DB::table('users')->whereIn('email', [
            'mvp-member-one@example.test',
            'mvp-member-two@example.test',
            'mvp-member-three@example.test',
            'mvp-member-four@example.test',
            'mvp-member-five@example.test',
        ])->count());
        $this->assertDatabaseHas('users', ['email' => 'mvp-admin@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'mvp-operator-two@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'mvp-operator-three@example.test']);
        $this->assertSame(3, DB::table('operator_profiles')->count());
        $this->assertDatabaseHas('authorization_permission_assignments', [
            'user_id' => DB::table('users')->where('email', 'mvp-admin@example.test')->value('id'),
            'permission' => 'operator.identity.verify',
            'active' => true,
        ]);
        $bookingId = DB::table('bookings')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('users.email', 'mvp-member-one@example.test')
            ->where('bookings.status', 'confirmed')
            ->value('bookings.id');
        $this->assertIsString($bookingId);
        $scheduleId = DB::table('bookings')->where('id', $bookingId)->value('shift_schedule_id');
        $memberIds = DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->whereIn('users.email', [
                'mvp-member-one@example.test',
                'mvp-member-two@example.test',
                'mvp-member-three@example.test',
                'mvp-member-four@example.test',
                'mvp-member-five@example.test',
            ])
            ->pluck('members.id');
        $this->assertCount(5, $memberIds);
        $this->assertSame(5, DB::table('bookings')->where('shift_schedule_id', $scheduleId)->count());
        $this->assertMatchesRegularExpression('/^MRN-[A-Z0-9]{8}$/', (string) DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('users.email', 'mvp-member-one@example.test')
            ->value('members.medical_record_number'));
        $this->assertDatabaseHas('point_ledger_entries', [
            'booking_id' => $bookingId,
            'funding_source' => 'personal',
            'entry_type' => 'charge',
            'point_delta' => '-12.5000',
        ]);
        foreach ($memberIds as $memberId) {
            $memberBookingId = DB::table('bookings')
                ->where('member_id', $memberId)
                ->where('shift_schedule_id', $scheduleId)
                ->value('id');
            $this->assertIsString($memberBookingId);
            $this->assertDatabaseHas('point_ledger_entries', [
                'booking_id' => $memberBookingId,
                'funding_source' => 'personal',
                'entry_type' => 'charge',
                'point_delta' => '-12.5000',
            ]);
        }
        $eligibleId = DB::table('operator_eligible_shifts')->value('id');
        $secondProfileId = DB::table('operator_profiles')
            ->join('users', 'users.id', '=', 'operator_profiles.user_id')
            ->where('users.email', 'mvp-operator-two@example.test')
            ->value('operator_profiles.id');
        $this->assertNotFalse($eligibleId);
        $this->assertNotFalse($secondProfileId);
        $this->assertDatabaseHas('operator_shift_assignments', [
            'operator_eligible_shift_id' => $eligibleId,
            'operator_profile_id' => $secondProfileId,
            'status' => 'active',
        ]);
        $thirdProfileId = DB::table('operator_profiles')
            ->join('users', 'users.id', '=', 'operator_profiles.user_id')
            ->where('users.email', 'mvp-operator-three@example.test')
            ->value('operator_profiles.id');
        $this->assertNotFalse($thirdProfileId);
        $this->assertDatabaseHas('operator_shift_assignments', [
            'operator_eligible_shift_id' => $eligibleId,
            'operator_profile_id' => $thirdProfileId,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('shift_schedules', [
            'starts_at' => '2026-08-13 03:00:00',
            'ends_at' => '2026-08-22 16:59:59',
        ]);
    }
}
