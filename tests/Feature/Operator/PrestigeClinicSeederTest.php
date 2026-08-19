<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Database\Seeders\PrestigeClinicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class PrestigeClinicSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_employee_csv_fails_before_creating_prestige_schedules(): void
    {
        putenv('PRESTIGE_EMPLOYEE_CSV='.sys_get_temp_dir().'/prestige-missing.csv');

        try {
            $this->seed(PrestigeClinicSeeder::class);
            $this->fail('A missing Prestige employee CSV must fail clearly.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('employee CSV', $exception->getMessage());
        } finally {
            putenv('PRESTIGE_EMPLOYEE_CSV');
        }

        $this->assertSame(0, DB::table('shift_schedules')->count());
    }

    public function test_invalid_employee_csv_fails_before_creating_prestige_schedules(): void
    {
        $this->withSyntheticCsv(function (string $path): void {
            file_put_contents($path, "no,name,place,birth_date,address,nik\n1,Only One,Yogyakarta,01-Jan-80,Address,900000000001\n1,Duplicate,Yogyakarta,02-Jan-80,Address,900000000001\n");

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('An invalid Prestige employee CSV must fail clearly.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('employee CSV', $exception->getMessage());
            }
        });

        $this->assertSame(0, DB::table('shift_schedules')->count());
    }

    public function test_prestige_clinic_seeder_creates_the_two_day_fixture_and_is_idempotent(): void
    {
        $this->withSyntheticCsv(function (string $path): void {
            $this->seed(PrestigeClinicSeeder::class);
            $this->insertObsoleteSchedule();
            $this->seed(PrestigeClinicSeeder::class);
        });

        $siteId = DB::table('examination_site_refs')->where('operator_site_id', PrestigeClinicSeeder::OPERATOR_SITE_ID)->value('id');
        $schedules = DB::table('shift_schedules')->where('examination_site_id', $siteId)->orderBy('starts_at')->get();

        $this->assertSame(5, DB::table('users')->where('email', 'like', 'operatorprestige%@madeena-xray.com')->count());
        $this->assertSame(5, DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->count());
        $this->assertCount(2, $schedules);
        $this->assertSame(['2026-08-27 01:00:00', '2026-08-28 01:00:00'], $schedules->pluck('starts_at')->all());
        $this->assertSame([37, 37], $schedules->pluck('quota')->map(fn ($quota): int => (int) $quota)->all());
        $this->assertSame(37, DB::table('members')->count());
        $this->assertSame(37, DB::table('bookings')->where('shift_schedule_id', $schedules[0]->id)->where('status', 'confirmed')->count());
        $this->assertSame(37, DB::table('bookings')->where('shift_schedule_id', $schedules[1]->id)->where('status', 'confirmed')->count());
        $firstDayMembers = DB::table('bookings')->where('shift_schedule_id', $schedules[0]->id)->orderBy('member_id')->pluck('member_id')->all();
        $secondDayMembers = DB::table('bookings')->where('shift_schedule_id', $schedules[1]->id)->orderBy('member_id')->pluck('member_id')->all();
        $this->assertSame($firstDayMembers, $secondDayMembers);
        $this->assertSame(74, DB::table('bookings')->count());
        $this->assertSame(37, DB::table('bookings')->distinct('member_id')->count('member_id'));
        $this->assertSame(0, DB::table('shift_schedules')->whereIn('starts_at', ['2026-08-14 01:00:00', '2026-08-26 01:00:00'])->count());
    }

    public function test_progressed_obsolete_schedule_stops_without_deleting_it(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $staleScheduleId = $this->insertObsoleteSchedule(true);

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('A progressed obsolete schedule must require separate cleanup.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('downstream records', $exception->getMessage());
            }

            $this->assertDatabaseHas('shift_schedules', ['id' => $staleScheduleId]);
        });
    }

    /** @param callable(string): void $callback */
    private function withSyntheticCsv(callable $callback): void
    {
        $path = tempnam(sys_get_temp_dir(), 'prestige-');
        $this->assertNotFalse($path);
        $handle = fopen($path, 'wb');
        $this->assertNotFalse($handle);
        fputcsv($handle, ['no', 'name', 'place', 'birth_date', 'address', 'nik']);
        for ($index = 1; $index <= 37; $index++) {
            fputcsv($handle, [
                (string) $index,
                'Synthetic Prestige Member '.$index,
                'Yogyakarta',
                '01-Jan-80',
                'Synthetic address '.$index,
                sprintf('900000%06d', $index),
            ]);
        }
        fclose($handle);
        putenv('PRESTIGE_EMPLOYEE_CSV='.$path);

        try {
            $callback($path);
        } finally {
            putenv('PRESTIGE_EMPLOYEE_CSV');
            unlink($path);
        }
    }

    private function insertObsoleteSchedule(bool $withBooking = false): string
    {
        $siteId = DB::table('examination_site_refs')->where('operator_site_id', PrestigeClinicSeeder::OPERATOR_SITE_ID)->value('id');
        $offeringId = DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->value('id');
        $scheduleId = (string) Str::uuid();
        $now = now();
        DB::table('shift_schedules')->insert([
            'id' => $scheduleId,
            'display_reference' => 'JAD-STALE',
            'examination_site_id' => $siteId,
            'service_offering_id' => $offeringId,
            'starts_at' => '2026-08-26 01:00:00',
            'ends_at' => '2026-08-26 10:00:00',
            'quota' => 37,
            'status' => 'open',
            'eligible_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_eligible_shifts')->insert([
            'id' => (string) Str::uuid(),
            'member_schedule_id' => $scheduleId,
            'operator_site_id' => PrestigeClinicSeeder::OPERATOR_SITE_ID,
            'schedule_starts_at' => '2026-08-26 01:00:00',
            'schedule_ends_at' => '2026-08-26 10:00:00',
            'confirmed_count_at_eligibility' => 0,
            'quota' => 37,
            'event_version' => 1,
            'source_event_id' => 'prestige:stale:'.$scheduleId,
            'eligible_at' => $now,
            'sync_status' => 'eligible',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($withBooking) {
            $booking = (array) DB::table('bookings')->first();
            $booking['id'] = (string) Str::uuid();
            $booking['shift_schedule_id'] = $scheduleId;
            DB::table('bookings')->insert($booking);
        }

        return $scheduleId;
    }
}
