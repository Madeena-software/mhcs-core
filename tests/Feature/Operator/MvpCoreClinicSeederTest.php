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
        $this->assertDatabaseHas('users', ['email' => 'mvp-admin@example.test']);
        $this->assertDatabaseHas('users', ['email' => 'mvp-operator-two@example.test']);
        $this->assertDatabaseHas('authorization_permission_assignments', [
            'user_id' => DB::table('users')->where('email', 'mvp-admin@example.test')->value('id'),
            'permission' => 'operator.identity.verify',
            'active' => true,
        ]);
        $this->assertSame(1, DB::table('bookings')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('users.email', 'mvp-member-one@example.test')
            ->where('bookings.status', 'confirmed')
            ->count());
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
    }
}
