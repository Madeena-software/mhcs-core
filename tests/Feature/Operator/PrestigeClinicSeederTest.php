<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Database\Seeders\PrestigeClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PrestigeClinicSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestige_clinic_seeder_creates_expected_operators_schedules_and_members(): void
    {
        $this->seed(PrestigeClinicSeeder::class);
        $this->seed(PrestigeClinicSeeder::class); // Verify idempotency

        // Verify operators
        $this->assertSame(5, DB::table('users')->where('email', 'like', 'operatorprestige%@madeena-xray.com')->count());
        $this->assertSame(5, DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->count());

        // Verify site
        $this->assertDatabaseHas('operator_sites', [
            'operator_site_id' => 'site-prestige',
            'display_name' => 'Rumah Skrining CV Prestige',
            'code' => 'PRES-01',
        ]);

        // Verify schedules (14, 26, 27 Aug 2026)
        $this->assertSame(3, DB::table('shift_schedules')->count());
        $this->assertDatabaseHas('shift_schedules', ['starts_at' => '2026-08-14 01:00:00']);
        $this->assertDatabaseHas('shift_schedules', ['starts_at' => '2026-08-26 01:00:00']);
        $this->assertDatabaseHas('shift_schedules', ['starts_at' => '2026-08-27 01:00:00']);

        // Verify members (37 employees)
        $this->assertSame(37, DB::table('members')->count());
        $this->assertSame(37, DB::table('bookings')->where('status', 'confirmed')->count());
        $this->assertSame(37, DB::table('point_ledger_entries')->where('entry_type', 'charge')->count());

        // Verify first and last member from CSV
        $this->assertDatabaseHas('members', ['name' => 'Sarjiman / Prawoto Utomo']);
        $this->assertDatabaseHas('members', ['name' => 'Dwi Janti']);
    }
}
