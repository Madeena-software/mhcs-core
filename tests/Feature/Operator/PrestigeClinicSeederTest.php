<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
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

    public function test_authorized_legacy_reset_removes_real_fk_graph_and_reseeds_idempotently(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $this->makeLegacyFixture();
            $beforeUsers = DB::table('users')->where('email', 'like', '%@prestige.madeena-xray.com')->orderBy('id')->pluck('password', 'id')->all();
            $beforeMemberNames = DB::table('members')->whereIn('user_id', array_keys($beforeUsers))->orderBy('id')->pluck('name', 'id')->all();
            $beforeNonBookingLedger = DB::table('point_ledger_entries')->whereNull('booking_id')->count();
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');

            try {
                $this->seed(PrestigeClinicSeeder::class);
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
            }

            $this->assertSame(0, DB::table('shift_schedules')->whereIn('starts_at', ['2026-08-14 01:00:00', '2026-08-26 01:00:00', '2026-08-27 01:00:00'])->count());
            $this->assertSame(111, DB::table('bookings')->where('status', 'confirmed')->count());
            $this->assertSame(111, DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereNotNull('booking_id')->count());
            foreach (['operator_paper_tickets', 'operator_queue_admissions', 'operator_queue_admission_history', 'operator_arrivals', 'operator_identity_verifications', 'operator_identity_verification_events', 'member_paper_questionnaires', 'member_vital_signs_assessments', 'operator_vital_signs_executions', 'examination_consents', 'booking_status_events'] as $table) {
                $this->assertSame(0, DB::table($table)->count(), $table);
            }
            $this->assertSame(37, DB::table('users')->where('email', 'like', '%@prestige.madeena-xray.com')->count());
            $this->assertSame($beforeUsers, DB::table('users')->whereIn('id', array_keys($beforeUsers))->orderBy('id')->pluck('password', 'id')->all());
            $this->assertSame($beforeMemberNames, DB::table('members')->whereIn('id', array_keys($beforeMemberNames))->orderBy('id')->pluck('name', 'id')->all());
            $this->assertSame($beforeNonBookingLedger, DB::table('point_ledger_entries')->whereNull('booking_id')->count());

            $before = [
                'schedules' => DB::table('shift_schedules')->count(),
                'bookings' => DB::table('bookings')->count(),
                'charges' => DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereNotNull('booking_id')->count(),
                'assignments' => DB::table('operator_shift_assignments')->count(),
            ];
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');
            try {
                $this->seed(PrestigeClinicSeeder::class);
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
            }
            $this->assertSame($before['schedules'], DB::table('shift_schedules')->count());
            $this->assertSame($before['bookings'], DB::table('bookings')->count());
            $this->assertSame($before['charges'], DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereNotNull('booking_id')->count());
            $this->assertSame($before['assignments'], DB::table('operator_shift_assignments')->count());
        });
    }

    public function test_legacy_reset_rolls_back_after_a_descendant_delete(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $this->makeLegacyFixture();
            $before = $this->legacyStateSnapshot();
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');
            putenv('PRESTIGE_TEST_RESET_FAILURE_AFTER_DELETE=1');
            $caught = null;
            try {
                $this->seed(PrestigeClinicSeeder::class);
            } catch (RuntimeException $exception) {
                $caught = $exception;
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
                putenv('PRESTIGE_TEST_RESET_FAILURE_AFTER_DELETE');
            }
            $this->assertInstanceOf(RuntimeException::class, $caught);
            $this->assertSame('Prestige reset test failure.', $caught?->getMessage());
            $after = $this->legacyStateSnapshot();
            foreach (array_keys($before) as $category) {
                $this->assertSame($before[$category], $after[$category], $category.' must be restored by rollback.');
            }
        });
    }

    public function test_authorized_legacy_reset_preserves_unrelated_data(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $this->makeLegacyFixture();
            $unrelated = $this->makeUnrelatedFixture();
            $before = $this->unrelatedStateSnapshot($unrelated);
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');

            try {
                $this->seed(PrestigeClinicSeeder::class);
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
            }

            $this->assertSame($before, $this->unrelatedStateSnapshot($unrelated));
        });
    }

    public function test_authorized_production_seed_rejects_empty_prestige_schedule_state(): void
    {
        $this->withSyntheticCsv(function (): void {
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');
            $_ENV['MHCS_ALLOW_PRODUCTION_MVP_SEED'] = 'true';

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('An authorized production seed must reject an empty Prestige state.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Prestige production state is not an exact diagnosed legacy or clean final state.', $exception->getMessage());
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
                unset($_ENV['MHCS_ALLOW_PRODUCTION_MVP_SEED']);
            }
        });

        $this->assertSame(0, DB::table('shift_schedules')->count());
    }

    public function test_authorized_legacy_reset_rejects_an_unexpected_extra_schedule_without_deletion(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $this->makeLegacyFixture();
            $this->insertUnexpectedPrestigeSchedule();
            $before = [
                'schedules' => DB::table('shift_schedules')->count(),
                'bookings' => DB::table('bookings')->count(),
                'charges' => DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereNotNull('booking_id')->count(),
            ];
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('An unexpected Prestige schedule must fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Prestige reset preconditions are not satisfied.', $exception->getMessage());
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
            }

            $this->assertSame($before['schedules'], DB::table('shift_schedules')->count());
            $this->assertSame($before['bookings'], DB::table('bookings')->count());
            $this->assertSame($before['charges'], DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereNotNull('booking_id')->count());
        });
    }

    public function test_authorized_final_seed_rejects_a_wrong_member_cohort_without_mutation(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $wrongMemberId = $this->insertNonPrestigeMember();
            $targetScheduleIds = DB::table('shift_schedules')->orderBy('starts_at')->pluck('id');
            $wrongMemberSource = DB::table('bookings')->where('shift_schedule_id', $targetScheduleIds[0])->where('status', 'confirmed')->first();
            foreach ($targetScheduleIds as $scheduleId) {
                DB::table('bookings')->where('shift_schedule_id', $scheduleId)->where('member_id', $wrongMemberSource->member_id)->update(['member_id' => $wrongMemberId]);
            }
            $before = DB::table('bookings')->orderBy('id')->pluck('member_id', 'id')->all();
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('A wrong final Prestige cohort must fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Prestige reconciliation preconditions are not satisfied.', $exception->getMessage());
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
            }

            $this->assertSame($before, DB::table('bookings')->orderBy('id')->pluck('member_id', 'id')->all());
        });
    }

    public function test_authorized_final_seed_rejects_duplicate_missing_charge_distribution_without_mutation(): void
    {
        $this->withSyntheticCsv(function (): void {
            $this->seed(PrestigeClinicSeeder::class);
            $bookings = DB::table('bookings')->orderBy('id')->limit(2)->get(['id']);
            $firstCharge = DB::table('point_ledger_entries')->where('booking_id', $bookings[0]->id)->where('entry_type', 'charge')->first();
            $secondCharge = DB::table('point_ledger_entries')->where('booking_id', $bookings[1]->id)->where('entry_type', 'charge')->first();
            DB::table('point_ledger_entries')->where('id', $firstCharge->id)->delete();
            $duplicate = (array) $secondCharge;
            $duplicate['id'] = (string) Str::uuid();
            $duplicate['source_reference'] = 'duplicate-charge-test';
            DB::table('point_ledger_entries')->insert($duplicate);
            $before = DB::table('point_ledger_entries')->orderBy('id')->pluck('booking_id', 'id')->all();
            putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED=true');

            try {
                $this->seed(PrestigeClinicSeeder::class);
                $this->fail('A duplicate/missing final charge distribution must fail closed.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Prestige reconciliation preconditions are not satisfied.', $exception->getMessage());
            } finally {
                putenv('MHCS_ALLOW_PRODUCTION_MVP_SEED');
            }

            $this->assertSame($before, DB::table('point_ledger_entries')->orderBy('id')->pluck('booking_id', 'id')->all());
        });
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

    private function makeLegacyFixture(): void
    {
        $site = DB::table('examination_site_refs')->where('operator_site_id', PrestigeClinicSeeder::OPERATOR_SITE_ID)->first();
        $offering = DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->first();
        $schedules = DB::table('shift_schedules')->where('examination_site_id', $site->id)->where('service_offering_id', $offering->id)->orderBy('starts_at')->get();
        $legacy = [
            ['2026-08-14 01:00:00', '2026-08-14 10:00:00', 13],
            ['2026-08-26 01:00:00', '2026-08-26 10:00:00', 12],
            ['2026-08-27 01:00:00', '2026-08-27 10:00:00', 12],
        ];
        $cohortIds = DB::table('members')->orderBy('id')->pluck('id');
        foreach ($schedules as $index => $schedule) {
            $bookings = DB::table('bookings')->where('shift_schedule_id', $schedule->id)->orderBy('id')->get();
            $offset = $index === 0 ? 0 : ($index === 1 ? 13 : 25);
            $keepMemberIds = $cohortIds->slice($offset, $legacy[$index][2]);
            foreach ($bookings as $booking) {
                if ($keepMemberIds->contains($booking->member_id)) {
                    continue;
                }
                DB::table('point_ledger_entries')->where('booking_id', $booking->id)->delete();
                DB::table('bookings')->where('id', $booking->id)->delete();
            }
            DB::table('shift_schedules')->where('id', $schedule->id)->update(['starts_at' => $legacy[$index][0], 'ends_at' => $legacy[$index][1], 'quota' => 50, 'status' => 'open']);
        }
        $old = DB::table('shift_schedules')->where('starts_at', '2026-08-14 01:00:00')->first();
        $siteStableId = (string) DB::table('operator_sites')->where('operator_site_id', PrestigeClinicSeeder::OPERATOR_SITE_ID)->value('id');
        $profileId = (string) DB::table('operator_profiles')->where('employee_code', 'like', 'OPR-PRES-%')->orderBy('employee_code')->value('id');
        $bookings = DB::table('bookings')->where('shift_schedule_id', $old->id)->orderBy('id')->get();
        DB::table('bookings')->whereIn('id', $bookings->take(4)->pluck('id'))->update(['status' => 'checked_in']);
        $now = now();
        foreach ($bookings->take(4) as $index => $booking) {
            $ticketId = (string) Str::uuid();
            DB::table('operator_paper_tickets')->insert(['id' => $ticketId, 'booking_id' => $booking->id, 'member_schedule_id' => $old->id, 'operator_site_id' => $siteStableId, 'operator_profile_id' => $profileId, 'ticket_number' => 'LEGACY-'.($index + 1), 'issued_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $arrivalId = (string) Str::uuid();
            DB::table('operator_arrivals')->insert(['id' => $arrivalId, 'booking_id' => $booking->id, 'member_schedule_id' => $old->id, 'operator_site_id' => $siteStableId, 'operator_profile_id' => $profileId, 'occurrence_at' => $now, 'recorded_at' => $now, 'operation_id' => (string) Str::uuid(), 'source' => 'legacy-test', 'status' => 'recorded', 'created_at' => $now, 'updated_at' => $now]);
            $verificationId = (string) Str::uuid();
            DB::table('operator_identity_verifications')->insert(['id' => $verificationId, 'arrival_id' => $arrivalId, 'booking_id' => $booking->id, 'member_schedule_id' => $old->id, 'operator_site_id' => $siteStableId, 'operator_profile_id' => $profileId, 'active_claim_operator_profile_id' => null, 'state' => 'verified', 'started_at' => $now, 'decided_at' => $now, 'reason_category' => null, 'reason' => null, 'operation_id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
            DB::table('operator_identity_verification_events')->insert(['id' => (string) Str::uuid(), 'verification_id' => $verificationId, 'event_type' => 'verified', 'from_state' => 'open', 'to_state' => 'verified', 'reason' => null, 'operation_id' => (string) Str::uuid(), 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('member_paper_questionnaires')->insert(['id' => (string) Str::uuid(), 'member_id' => $booking->member_id, 'booking_id' => $booking->id, 'member_schedule_id' => $old->id, 'examination_site_id' => $site->id, 'operator_site_id' => $siteStableId, 'operator_profile_id' => $profileId, 'completed_at' => $now, 'form_version' => 'V1', 'private_photo_object_key' => 'legacy-questionnaire-'.$index, 'private_photo_checksum' => hash('sha256', (string) $index), 'private_photo_bytes' => 1, 'private_photo_format' => 'image/jpeg', 'operation_id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
            $assessmentId = (string) Str::uuid();
            DB::table('member_vital_signs_assessments')->insert(['id' => $assessmentId, 'member_id' => $booking->member_id, 'booking_id' => $booking->id, 'member_schedule_id' => $old->id, 'systolic_bp_value' => '120', 'systolic_bp_unit' => 'mmHg', 'systolic_bp_missing_reason' => null, 'diastolic_bp_value' => '80', 'diastolic_bp_unit' => 'mmHg', 'diastolic_bp_missing_reason' => null, 'temperature_value' => '36.5', 'temperature_unit' => 'C', 'temperature_missing_reason' => null, 'height_value' => '180', 'height_unit' => 'cm', 'height_missing_reason' => null, 'weight_value' => '75', 'weight_unit' => 'kg', 'weight_missing_reason' => null, 'bmi_value' => '23.1', 'bmi_unit' => 'kg/m2', 'bmi_missing_reason' => null, 'effective_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('booking_status_events')->insert(['id' => (string) Str::uuid(), 'booking_id' => $booking->id, 'source_service' => 'legacy-test', 'source_operator_id' => $profileId, 'event_type' => 'checked_in', 'occurred_at' => $now, 'received_at' => $now, 'idempotency_key' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
        }
        $tickets = DB::table('operator_paper_tickets')->where('member_schedule_id', $old->id)->get();
        foreach ($tickets as $ticket) {
            foreach (['basic_examination', 'xray'] as $stage) {
                $admissionId = (string) Str::uuid();
                DB::table('operator_queue_admissions')->insert(['id' => $admissionId, 'operator_paper_ticket_id' => $ticket->id, 'operator_site_id' => $siteStableId, 'member_schedule_id' => $old->id, 'queue_class' => 'advance', 'stage' => $stage, 'state' => 'waiting', 'ready_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('operator_queue_admission_history')->insert(['id' => (string) Str::uuid(), 'operator_queue_admission_id' => $admissionId, 'operator_profile_id' => $profileId, 'event_type' => 'admitted', 'from_state' => null, 'to_state' => 'waiting', 'operation_id' => (string) Str::uuid(), 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $assessments = DB::table('member_vital_signs_assessments')->where('member_schedule_id', $old->id)->get();
        $queues = DB::table('operator_queue_admissions')->where('member_schedule_id', $old->id)->orderBy('id')->get();
        foreach ($assessments as $index => $assessment) {
            DB::table('operator_vital_signs_executions')->insert(['id' => (string) Str::uuid(), 'member_vital_signs_assessment_id' => $assessment->id, 'operator_queue_admission_id' => $queues[$index]->id, 'operator_profile_id' => $profileId, 'operator_site_id' => $siteStableId, 'occurred_at' => $now, 'operation_id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('point_ledger_entries')->insert(['id' => (string) Str::uuid(), 'member_id' => $bookings->first()->member_id, 'booking_id' => null, 'funding_source' => 'member', 'entry_type' => 'credit', 'point_delta' => '5.0000', 'source_reference' => 'legacy-test-non-booking', 'reverses_id' => null, 'created_at' => $now]);
        $this->assertSame([13, 12, 12], DB::table('shift_schedules')->orderBy('starts_at')->get()->map(fn ($schedule) => DB::table('bookings')->where('shift_schedule_id', $schedule->id)->count())->all());
        $this->assertSame(['checked_in' => 4, 'confirmed' => 9], DB::table('bookings')->where('shift_schedule_id', $old->id)->select('status', DB::raw('count(*) as aggregate'))->groupBy('status')->pluck('aggregate', 'status')->all());
        $this->assertSame([13, 12, 12], DB::table('shift_schedules')->orderBy('starts_at')->get()->map(fn ($schedule) => DB::table('point_ledger_entries')->whereIn('booking_id', DB::table('bookings')->where('shift_schedule_id', $schedule->id)->pluck('id'))->where('entry_type', 'charge')->count())->all());
        $this->assertSame([0, 4, 8, 4, 4, 4, 4, 0], [0, DB::table('operator_paper_tickets')->where('member_schedule_id', $old->id)->count(), DB::table('operator_queue_admissions')->where('member_schedule_id', $old->id)->count(), DB::table('operator_arrivals')->where('member_schedule_id', $old->id)->count(), DB::table('operator_identity_verifications')->where('member_schedule_id', $old->id)->count(), DB::table('member_paper_questionnaires')->where('member_schedule_id', $old->id)->count(), DB::table('member_vital_signs_assessments')->where('member_schedule_id', $old->id)->count(), DB::table('image_gateway_capture_sets')->where('member_schedule_id', $old->id)->count()]);
        $this->assertSame(4, DB::table('operator_paper_tickets')->count());
        $this->assertSame(8, DB::table('operator_queue_admissions')->count());
        $this->assertSame(4, DB::table('member_vital_signs_assessments')->count());
        $this->assertSame(4, DB::table('operator_vital_signs_executions')->count());
    }

    /** @return array<string, list<array<string, mixed>> > */
    private function legacyStateSnapshot(): array
    {
        $scheduleIds = DB::table('shift_schedules')->whereIn('starts_at', ['2026-08-14 01:00:00', '2026-08-26 01:00:00', '2026-08-27 01:00:00'])->pluck('id')->all();
        $bookingIds = DB::table('bookings')->whereIn('shift_schedule_id', $scheduleIds)->pluck('id')->all();
        $eligibleIds = DB::table('operator_eligible_shifts')->whereIn('member_schedule_id', $scheduleIds)->pluck('id')->all();
        $queueIds = DB::table('operator_queue_admissions')->whereIn('member_schedule_id', $scheduleIds)->pluck('id')->all();
        $verificationIds = DB::table('operator_identity_verifications')->whereIn('member_schedule_id', $scheduleIds)->pluck('id')->all();
        $assessmentIds = DB::table('member_vital_signs_assessments')->whereIn('member_schedule_id', $scheduleIds)->pluck('id')->all();

        return [
            'schedules' => $this->snapshotRows('shift_schedules', 'id', $scheduleIds),
            'bookings' => $this->snapshotRows('bookings', 'id', $bookingIds),
            'charges' => $this->snapshotRows('point_ledger_entries', 'booking_id', $bookingIds),
            'paper_tickets' => $this->snapshotRows('operator_paper_tickets', 'member_schedule_id', $scheduleIds),
            'queue_admissions' => $this->snapshotRows('operator_queue_admissions', 'member_schedule_id', $scheduleIds),
            'queue_history' => $this->snapshotRows('operator_queue_admission_history', 'operator_queue_admission_id', $queueIds),
            'arrivals' => $this->snapshotRows('operator_arrivals', 'member_schedule_id', $scheduleIds),
            'identity_verifications' => $this->snapshotRows('operator_identity_verifications', 'member_schedule_id', $scheduleIds),
            'identity_events' => $this->snapshotRows('operator_identity_verification_events', 'verification_id', $verificationIds),
            'questionnaires' => $this->snapshotRows('member_paper_questionnaires', 'member_schedule_id', $scheduleIds),
            'vital_assessments' => $this->snapshotRows('member_vital_signs_assessments', 'member_schedule_id', $scheduleIds),
            'vital_executions' => $this->snapshotRowsWhereIn('operator_vital_signs_executions', [['member_vital_signs_assessment_id', $assessmentIds], ['operator_queue_admission_id', $queueIds]]),
            'assignments' => $this->snapshotRows('operator_shift_assignments', 'operator_eligible_shift_id', $eligibleIds),
            'eligible_shifts' => $this->snapshotRows('operator_eligible_shifts', 'id', $eligibleIds),
        ];
    }

    /** @param list<string> $ids @return list<array<string, mixed>> */
    private function snapshotRows(string $table, string $column, array $ids): array
    {
        return $ids === [] ? [] : DB::table($table)->whereIn($column, $ids)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
    }

    /** @param list<array{0: string, 1: list<string>}> $filters @return list<array<string, mixed>> */
    private function snapshotRowsWhereIn(string $table, array $filters): array
    {
        $query = DB::table($table)->whereRaw('1 = 0');
        foreach ($filters as [$column, $ids]) {
            if ($ids !== []) {
                $query->orWhereIn($column, $ids);
            }
        }

        return $query->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
    }

    /** @return array<string, string> */
    private function makeUnrelatedFixture(): array
    {
        $memberId = $this->insertNonPrestigeMember();
        $memberUserId = (string) DB::table('members')->where('id', $memberId)->value('user_id');
        $operator = User::factory()->create(['email' => 'unrelated-operator-'.Str::lower(Str::random(8)).'@example.test']);
        $now = now();
        $organizationId = (string) Str::uuid();
        $organizationStableId = 'unrelated-org-'.Str::lower(Str::random(8));
        $siteReferenceId = (string) Str::uuid();
        $siteLocalId = (string) Str::uuid();
        $siteStableId = 'unrelated-site-'.Str::lower(Str::random(8));
        $offeringId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $rateId = (string) DB::table('point_exchange_rates')->where('status', 'active')->orderBy('effective_at')->value('id');
        $bookingId = (string) Str::uuid();
        $profileId = (string) Str::uuid();
        $eligibleId = (string) Str::uuid();

        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => $organizationStableId, 'name' => 'Unrelated Organization', 'source_version' => '1', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('examination_site_refs')->insert(['id' => $siteReferenceId, 'operator_site_id' => $siteStableId, 'operator_organization_ref_id' => $organizationId, 'code' => 'UNRELATED', 'display_name' => 'Unrelated Site', 'timezone' => 'Asia/Jakarta', 'source_version' => '1', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_sites')->insert(['id' => $siteLocalId, 'operator_site_id' => $siteStableId, 'organization_id' => $organizationStableId, 'organization_name' => 'Unrelated Organization', 'code' => 'UNRELATED', 'display_name' => 'Unrelated Site', 'address_line' => null, 'timezone' => 'Asia/Jakarta', 'active' => true, 'source_version' => '1', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('service_offerings')->insert(['id' => $offeringId, 'code' => 'UNRELATED-SERVICE', 'name' => 'Unrelated Service', 'includes_ai' => false, 'includes_doctor' => false, 'point_price' => '2.5000', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'display_reference' => 'JAD-OTHER', 'examination_site_id' => $siteReferenceId, 'service_offering_id' => $offeringId, 'starts_at' => '2040-01-10 03:00:00', 'ends_at' => '2040-01-10 04:00:00', 'quota' => 1, 'status' => 'open', 'eligible_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('bookings')->insert(['id' => $bookingId, 'member_id' => $memberId, 'shift_schedule_id' => $scheduleId, 'service_offering_id' => $offeringId, 'examination_site_id_snapshot' => $siteReferenceId, 'booking_type' => 'b2c', 'funding_source' => 'personal', 'status' => 'confirmed', 'service_code_snapshot' => 'UNRELATED-SERVICE', 'point_cost_snapshot' => '2.5000', 'point_exchange_rate_id' => $rateId, 'includes_ai_snapshot' => false, 'includes_doctor_snapshot' => false, 'site_code_snapshot' => 'UNRELATED', 'site_name_snapshot' => 'Unrelated Site', 'site_timezone_snapshot' => 'Asia/Jakarta', 'created_at' => $now, 'confirmed_at' => $now, 'updated_at' => $now]);
        DB::table('point_ledger_entries')->insert(['id' => (string) Str::uuid(), 'member_id' => $memberId, 'booking_id' => $bookingId, 'funding_source' => 'personal', 'entry_type' => 'charge', 'point_delta' => '-2.5000', 'source_reference' => 'unrelated-charge', 'reverses_id' => null, 'created_at' => $now]);
        DB::table('operator_profiles')->insert(['id' => $profileId, 'user_id' => $operator->id, 'display_name' => 'Unrelated Operator', 'employee_code' => 'OPR-UNRELATED', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_site_assignments')->insert(['id' => (string) Str::uuid(), 'operator_profile_id' => $profileId, 'operator_site_id' => $siteLocalId, 'active' => true, 'assigned_by_user_id' => $operator->id, 'assigned_at' => $now, 'revoked_at' => null, 'reason' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_eligible_shifts')->insert(['id' => $eligibleId, 'member_schedule_id' => $scheduleId, 'operator_site_id' => $siteStableId, 'schedule_starts_at' => '2040-01-10 03:00:00', 'schedule_ends_at' => '2040-01-10 04:00:00', 'confirmed_count_at_eligibility' => 1, 'quota' => 1, 'event_version' => 1, 'source_event_id' => 'unrelated:eligible', 'eligible_at' => $now, 'sync_status' => 'eligible', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_shift_assignments')->insert(['id' => (string) Str::uuid(), 'operator_eligible_shift_id' => $eligibleId, 'operator_profile_id' => $profileId, 'assigned_by_user_id' => $operator->id, 'status' => 'active', 'assigned_at' => $now, 'revoked_at' => null, 'reason' => null, 'created_at' => $now, 'updated_at' => $now]);

        return compact('memberId', 'memberUserId', 'siteReferenceId', 'siteLocalId', 'offeringId', 'scheduleId', 'rateId', 'bookingId', 'profileId', 'eligibleId') + ['operatorUserId' => (string) $operator->id];
    }

    /** @param array<string, string> $fixture @return array<string, list<array<string, mixed>>> */
    private function unrelatedStateSnapshot(array $fixture): array
    {
        return [
            'users' => $this->snapshotRows('users', 'id', [$fixture['memberUserId'], $fixture['operatorUserId']]),
            'members' => $this->snapshotRows('members', 'id', [$fixture['memberId']]),
            'site_refs' => $this->snapshotRows('examination_site_refs', 'id', [$fixture['siteReferenceId']]),
            'operator_sites' => $this->snapshotRows('operator_sites', 'id', [$fixture['siteLocalId']]),
            'offerings' => $this->snapshotRows('service_offerings', 'id', [$fixture['offeringId']]),
            'schedules' => $this->snapshotRows('shift_schedules', 'id', [$fixture['scheduleId']]),
            'bookings' => $this->snapshotRows('bookings', 'id', [$fixture['bookingId']]),
            'ledger' => $this->snapshotRows('point_ledger_entries', 'booking_id', [$fixture['bookingId']]),
            'profiles' => $this->snapshotRows('operator_profiles', 'id', [$fixture['profileId']]),
            'site_assignments' => $this->snapshotRows('operator_site_assignments', 'operator_profile_id', [$fixture['profileId']]),
            'eligible_shifts' => $this->snapshotRows('operator_eligible_shifts', 'id', [$fixture['eligibleId']]),
            'assignments' => $this->snapshotRows('operator_shift_assignments', 'operator_eligible_shift_id', [$fixture['eligibleId']]),
        ];
    }

    private function insertUnexpectedPrestigeSchedule(): void
    {
        $siteId = DB::table('examination_site_refs')->where('operator_site_id', PrestigeClinicSeeder::OPERATOR_SITE_ID)->value('id');
        $offeringId = DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->value('id');
        $now = now();
        DB::table('shift_schedules')->insert([
            'id' => (string) Str::uuid(),
            'display_reference' => 'JAD-EXTRA',
            'examination_site_id' => $siteId,
            'service_offering_id' => $offeringId,
            'starts_at' => '2026-08-29 01:00:00',
            'ends_at' => '2026-08-29 10:00:00',
            'quota' => 37,
            'status' => 'open',
            'eligible_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertNonPrestigeMember(): string
    {
        $user = User::factory()->create(['email' => 'non-prestige-'.Str::lower(Str::random(8)).'@example.test']);
        $memberId = (string) Str::uuid();
        $now = now();
        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-NON-PRESTIGE',
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'protected',
            'nik_lookup_digest' => hash('sha256', $memberId),
            'name' => 'Non Prestige Member',
            'birth_date' => '1988-01-01',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Synthetic address',
            'emergency_contact_name' => 'Synthetic contact',
            'emergency_contact_relationship' => null,
            'emergency_contact_phone' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $memberId;
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
