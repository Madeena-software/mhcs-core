<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use Database\Seeders\PrestigeClinicSeeder;
use DateTimeImmutable;
use DateTimeZone;
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

    public function test_authorized_production_seed_rejects_empty_prestige_schedule_state(): void
    {
        $this->withSyntheticCsv(function (): void {
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');
            $_ENV['MHCS_ALLOW_PRODUCTION_MVP_SEED'] = 'true';

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('Authorized production seeding must reject an empty Prestige state.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Prestige production state is not an exact diagnosed legacy or clean final state.', $exception->getMessage());
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
                unset($_ENV['MHCS_ALLOW_PRODUCTION_MVP_SEED']);
            }
        });

        $this->assertSame(0, DB::table('shift_schedules')->count());
    }

    public function test_reset_source_deletes_queue_admissions_before_paper_tickets(): void
    {
        $source = file_get_contents(base_path('database/seeders/PrestigeClinicSeeder.php'));

        $this->assertIsString($source);
        $queueDelete = strpos($source, "['operator_queue_admissions', 'id', ".'$queueIds]');
        $ticketDelete = strpos($source, "['operator_paper_tickets', 'member_schedule_id', ".'$scheduleIds]');
        $this->assertNotFalse($queueDelete);
        $this->assertNotFalse($ticketDelete);
        $this->assertLessThan($ticketDelete, $queueDelete);
    }

    public function test_prestige_clinic_seeder_creates_the_three_schedule_fixture_and_is_idempotent(): void
    {
        $memberPasswordHashes = [];

        $this->withSyntheticCsv(function (string $path) use (&$memberPasswordHashes): void {
            $this->seed(PrestigeClinicSeeder::class);
            $memberPasswordHashes = DB::table('users')
                ->where('email', 'like', '%@prestige.madeena-xray.com')
                ->orderBy('id')
                ->pluck('password', 'id')
                ->all();
            $this->seed(PrestigeClinicSeeder::class);
        });

        $siteId = DB::table('examination_site_refs')->where('operator_site_id', PrestigeClinicSeeder::OPERATOR_SITE_ID)->value('id');
        $schedules = DB::table('shift_schedules')->where('examination_site_id', $siteId)->orderBy('starts_at')->get();

        $this->assertSame(5, DB::table('users')->where('email', 'like', 'operatorprestige%@madeena-xray.com')->count());
        $this->assertSame(5, DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->count());
        $this->assertCount(3, $schedules);
        $this->assertSame(
            ['2026-08-19 17:00:00', '2026-08-26 17:00:00', '2026-08-27 17:00:00'],
            $schedules->pluck('starts_at')->all(),
        );
        $this->assertSame(
            ['2026-08-26 17:00:00', '2026-08-27 17:00:00', '2026-08-28 17:00:00'],
            $schedules->pluck('ends_at')->all(),
        );
        $utc = new DateTimeZone('UTC');
        $local = new DateTimeZone(PrestigeClinicSeeder::SITE_TIMEZONE);
        $this->assertSame([
            ['2026-08-20T00:00:00+07:00', '2026-08-27T00:00:00+07:00'],
            ['2026-08-27T00:00:00+07:00', '2026-08-28T00:00:00+07:00'],
            ['2026-08-28T00:00:00+07:00', '2026-08-29T00:00:00+07:00'],
        ], $schedules->map(fn (object $schedule): array => [
            (new DateTimeImmutable((string) $schedule->starts_at, $utc))->setTimezone($local)->format(DATE_ATOM),
            (new DateTimeImmutable((string) $schedule->ends_at, $utc))->setTimezone($local)->format(DATE_ATOM),
        ])->all());
        $this->assertSame([37, 37, 37], $schedules->pluck('quota')->map(fn ($quota): int => (int) $quota)->all());
        $this->assertSame(37, DB::table('members')->count());
        $memberSets = $schedules->map(fn (object $schedule): array => DB::table('bookings')
            ->where('shift_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->orderBy('member_id')
            ->pluck('member_id')
            ->all()
        )->all();
        $this->assertSame([37, 37, 37], array_map('count', $memberSets));
        $this->assertSame([37, 37, 37], array_map(static fn (array $members): int => count(array_unique($members)), $memberSets));
        $this->assertSame($memberSets[0], $memberSets[1]);
        $this->assertSame($memberSets[1], $memberSets[2]);
        $this->assertSame(111, DB::table('bookings')->where('status', 'confirmed')->count());
        $this->assertSame(111, DB::table('bookings')->count());
        $this->assertSame(37, DB::table('bookings')->distinct('member_id')->count('member_id'));
        $this->assertSame(111, DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereNotNull('booking_id')->count());
        $this->assertSame(0, DB::table('shift_schedules')->whereIn('starts_at', ['2026-08-14 01:00:00', '2026-08-26 01:00:00', '2026-08-27 01:00:00', '2026-08-28 01:00:00'])->count());
        $this->assertSame(15, DB::table('operator_shift_assignments')->count());
        $this->assertSame(
            $memberPasswordHashes,
            DB::table('users')
                ->where('email', 'like', '%@prestige.madeena-xray.com')
                ->orderBy('id')
                ->pluck('password', 'id')
                ->all(),
        );
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
                $this->assertSame('Prestige reconciliation preconditions are not satisfied.', $exception->getMessage());
            }

            $this->assertDatabaseHas('shift_schedules', ['id' => $staleScheduleId]);
        });
    }

    public function test_mixed_prestige_schedule_state_fails_closed_without_deleting_legacy_rows(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $staleScheduleId = $this->insertObsoleteSchedule();

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('A mixed Prestige schedule state must fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Prestige reconciliation preconditions are not satisfied.', $exception->getMessage());
            }

            $this->assertDatabaseHas('shift_schedules', ['id' => $staleScheduleId, 'starts_at' => '2026-08-26 01:00:00']);
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
