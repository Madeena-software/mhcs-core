<?php

declare(strict_types=1);

namespace Tests\Feature\Operator;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp03PointService;
use App\Shared\Security\ProtectedIdentifierService;
use Database\Seeders\PrestigeClinicSeeder;
use Database\Seeders\PrestigeWebTestMembersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class PrestigeWebTestMembersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_is_minimal_protected_and_idempotent(): void
    {
        $this->withPrestige(function (): void {
            $before = $this->prestigeMemberCount();
            $this->assertSame(37, $before);
            $this->seed(PrestigeWebTestMembersSeeder::class);

            $this->assertSame($before, $this->prestigeMemberCount());
            $this->assertSame(2, DB::table('members')->whereIn('name', ['gbsuparta', 'ipang'])->count());
            $this->assertSame(39, DB::table('members')->count());
            $this->assertSame(1, DB::table('users')->where('email', 'gbsuparta@ugm.ac.id')->count());
            $this->assertSame(1, DB::table('users')->where('email', 'ipang.prestige@madeena-xray.com')->count());

            foreach (['9900000000000001', '9900000000000002'] as $nik) {
                $this->assertSame(1, DB::table('members')->where('nik_lookup_digest', app(ProtectedIdentifierService::class)->lookupDigest($nik))->count());
                $this->assertSame(0, DB::table('members')->where('encrypted_nik', $nik)->count());
            }

            $fixtureIds = DB::table('members')->whereIn('name', ['gbsuparta', 'ipang'])->pluck('id');
            $this->assertSame(4, DB::table('member_verification_assets')->whereIn('member_id', $fixtureIds)->count());
            $this->assertSame(0, DB::table('authorization_role_assignments')->whereIn('user_id', DB::table('members')->whereIn('id', $fixtureIds)->pluck('user_id'))->count());
            $this->assertSame(0, DB::table('authorization_permission_assignments')->whereIn('user_id', DB::table('members')->whereIn('id', $fixtureIds)->pluck('user_id'))->count());

            $schedules = DB::table('shift_schedules')->whereIn('display_reference', ['JAD-NPZ-0827', 'JAD-NPZ-0828'])->orderBy('display_reference')->get();
            $this->assertCount(2, $schedules);
            $this->assertSame(['JAD-NPZ-0827', 'JAD-NPZ-0828'], $schedules->pluck('display_reference')->all());
            foreach ($schedules as $schedule) {
                $this->assertLessThanOrEqual(12, strlen($schedule->display_reference));
            }
            $this->assertSame(['2026-08-26 17:00:00', '2026-08-27 17:00:00'], $schedules->pluck('starts_at')->all());
            $this->assertSame(['2026-08-27 17:00:00', '2026-08-28 17:00:00'], $schedules->pluck('ends_at')->all());
            foreach ($schedules as $schedule) {
                $this->assertSame('PRES-01', DB::table('examination_site_refs')->where('id', $schedule->examination_site_id)->value('code'));
                $this->assertSame('SYN-CHEST-B', DB::table('service_offerings')->where('id', $schedule->service_offering_id)->value('code'));
                $this->assertSame(50, (int) $schedule->quota);
                $this->assertSame('open', $schedule->status);
                $this->assertSame(2, DB::table('bookings')->where('shift_schedule_id', $schedule->id)->where('status', 'confirmed')->count());
                $this->assertSame(1, DB::table('operator_eligible_shifts')->where('member_schedule_id', $schedule->id)->count());
                $this->assertSame(5, DB::table('operator_shift_assignments')->whereIn('operator_eligible_shift_id', DB::table('operator_eligible_shifts')->where('member_schedule_id', $schedule->id)->pluck('id'))->count());
            }
            $this->assertSame(4, DB::table('bookings')->whereIn('shift_schedule_id', $schedules->pluck('id'))->where('status', 'confirmed')->count());
            $this->assertSame(4, DB::table('point_ledger_entries')->whereIn('member_id', $fixtureIds)->where('entry_type', 'credit')->count());
            $this->assertSame(4, DB::table('point_ledger_entries')->whereIn('booking_id', DB::table('bookings')->whereIn('shift_schedule_id', $schedules->pluck('id'))->pluck('id'))->where('entry_type', 'charge')->count());
            foreach ($fixtureIds as $memberId) {
                $this->assertSame('0.0000', (string) app(Mvp03PointService::class)->personalBalance((string) $memberId));
            }
            $this->assertSame(3, DB::table('shift_schedules')->whereIn('service_offering_id', DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->pluck('id'))->count());
            $this->assertSame(111, DB::table('bookings')->whereIn('shift_schedule_id', DB::table('shift_schedules')->whereIn('service_offering_id', DB::table('service_offerings')->where('code', 'SYN-CHEST-A')->pluck('id'))->pluck('id'))->count());
            $bookingIds = DB::table('bookings')->whereIn('shift_schedule_id', $schedules->pluck('id'))->pluck('id');
            foreach (['operator_arrivals', 'operator_identity_verifications', 'examination_consents', 'operator_paper_tickets'] as $table) {
                $this->assertSame(0, DB::table($table)->whereIn('booking_id', $bookingIds)->count());
            }
            foreach (['operator_queue_admissions', 'member_vital_signs_assessments', 'member_paper_questionnaires', 'image_gateway_capture_sets'] as $table) {
                $this->assertSame(0, DB::table($table)->whereIn('member_schedule_id', $schedules->pluck('id'))->count());
            }
            $this->assertSame(0, DB::table('local_imaging_orders')->whereIn('shift_schedule_id', $schedules->pluck('id'))->count());
            $this->assertSame(0, DB::table('operator_vital_signs_executions')->whereIn('operator_queue_admission_id', DB::table('operator_queue_admissions')->whereIn('member_schedule_id', $schedules->pluck('id'))->pluck('id'))->count());
            $this->assertSame(0, DB::table('image_gateway_studies')->whereIn('capture_set_id', DB::table('image_gateway_capture_sets')->whereIn('member_schedule_id', $schedules->pluck('id'))->pluck('id'))->count());

            $before = [DB::table('users')->count(), DB::table('members')->count(), DB::table('shift_schedules')->count(), DB::table('bookings')->count(), DB::table('point_ledger_entries')->count(), DB::table('operator_eligible_shifts')->count(), DB::table('operator_shift_assignments')->count()];
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $this->assertSame($before, [DB::table('users')->count(), DB::table('members')->count(), DB::table('shift_schedules')->count(), DB::table('bookings')->count(), DB::table('point_ledger_entries')->count(), DB::table('operator_eligible_shifts')->count(), DB::table('operator_shift_assignments')->count()]);
        });
    }

    public function test_operator_can_see_both_subjects_before_manual_workflow(): void
    {
        $this->withPrestige(function (): void {
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $schedule = DB::table('shift_schedules')->where('display_reference', 'JAD-NPZ-0827')->firstOrFail();
            $operator = DB::table('users')->where('email', 'operatorprestigesatu@madeena-xray.com')->firstOrFail();
            $siteId = DB::table('operator_sites')->where('operator_site_id', 'site-prestige')->value('id');

            $this->actingAs(User::query()->whereKey($operator->id)->firstOrFail())
                ->withSession(['operator.active_site_id' => $siteId])
                ->get(route('operator.attendance', ['schedule' => $schedule->id, 'at' => now()->format(DATE_ATOM)]))
                ->assertOk()
                ->assertSee('gbsuparta')
                ->assertSee('ipang');
        });
    }

    #[DataProvider('fixtureEmails')]
    public function test_unrelated_existing_subject_email_fails_closed(string $email): void
    {
        $this->withPrestige(function () use ($email): void {
            $userId = (string) Str::uuid();
            DB::table('users')->insert(['id' => $userId, 'email' => $email, 'email_verified_at' => now(), 'password' => 'not-a-fixture', 'account_status' => 'active', 'login_enabled' => true, 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now()]);

            $this->expectException(RuntimeException::class);
            $this->seed(PrestigeWebTestMembersSeeder::class);
        });
    }

    public function test_gbsuparta_only_state_fails_closed(): void
    {
        $this->withPrestige(function (): void {
            DB::table('users')->insert(['id' => (string) Str::uuid(), 'email' => 'gbsuparta@ugm.ac.id', 'email_verified_at' => now(), 'password' => 'fixture', 'account_status' => 'active', 'login_enabled' => true, 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now()]);
            $this->expectException(RuntimeException::class);
            $this->seed(PrestigeWebTestMembersSeeder::class);
        });
    }

    public function test_ipang_only_state_fails_closed(): void
    {
        $this->withPrestige(function (): void {
            DB::table('users')->insert(['id' => (string) Str::uuid(), 'email' => 'ipang.prestige@madeena-xray.com', 'email_verified_at' => now(), 'password' => 'fixture', 'account_status' => 'active', 'login_enabled' => true, 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now()]);
            $this->expectException(RuntimeException::class);
            $this->seed(PrestigeWebTestMembersSeeder::class);
        });
    }

    public function test_missing_booking_fails_closed(): void
    {
        $this->withPrestige(function (): void {
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $booking = DB::table('bookings')->whereIn('member_id', DB::table('members')->whereIn('name', ['gbsuparta', 'ipang'])->pluck('id'))->firstOrFail();
            DB::table('point_ledger_entries')->where('booking_id', $booking->id)->delete();
            DB::table('bookings')->where('id', $booking->id)->delete();
            $this->expectException(RuntimeException::class);
            $this->seed(PrestigeWebTestMembersSeeder::class);
        });
    }

    public function test_missing_operator_assignment_fails_closed(): void
    {
        $this->withPrestige(function (): void {
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $eligibleId = DB::table('operator_eligible_shifts')->where('member_schedule_id', DB::table('shift_schedules')->where('display_reference', 'JAD-NPZ-0827')->value('id'))->value('id');
            DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligibleId)->limit(1)->delete();
            $this->expectException(RuntimeException::class);
            $this->seed(PrestigeWebTestMembersSeeder::class);
        });
    }

    public function test_operational_progression_fails_closed(): void
    {
        $this->withPrestige(function (): void {
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $booking = DB::table('bookings')->whereIn('member_id', DB::table('members')->whereIn('name', ['gbsuparta', 'ipang'])->pluck('id'))->firstOrFail();
            $schedule = DB::table('shift_schedules')->where('display_reference', 'JAD-NPZ-0827')->firstOrFail();
            $profile = DB::table('operator_profiles')->where('employee_code', 'OPR-PRES-01')->firstOrFail();
            $site = DB::table('operator_sites')->where('operator_site_id', 'site-prestige')->firstOrFail();
            DB::table('operator_arrivals')->insert(['id' => (string) Str::uuid(), 'booking_id' => $booking->id, 'member_schedule_id' => $schedule->id, 'operator_site_id' => $site->id, 'operator_profile_id' => $profile->id, 'occurrence_at' => now(), 'recorded_at' => now(), 'operation_id' => (string) Str::uuid(), 'source' => 'test', 'status' => 'recorded', 'created_at' => now(), 'updated_at' => now()]);
            $this->expectException(RuntimeException::class);
            $this->seed(PrestigeWebTestMembersSeeder::class);
        });
    }

    public static function fixtureEmails(): array
    {
        return [['gbsuparta@ugm.ac.id'], ['ipang.prestige@madeena-xray.com']];
    }

    private function prestigeMemberCount(): int
    {
        return DB::table('members')->join('users', 'users.id', '=', 'members.user_id')->where('users.email', 'like', '%@prestige.madeena-xray.com')->count();
    }

    private function withPrestige(\Closure $callback): void
    {
        $path = tempnam(sys_get_temp_dir(), 'prestige-');
        if ($path === false) {
            throw new RuntimeException('Could not create synthetic CSV.');
        }
        $rows = ['no,name,place,birth_date,address,nik'];
        for ($i = 1; $i <= 37; $i++) {
            $rows[] = "$i,Prestige Employee $i,Yogyakarta,01-Jan-80,Synthetic Prestige Address $i,".sprintf('880000000%03d', $i);
        }
        file_put_contents($path, implode("\n", $rows));
        putenv('PRESTIGE_EMPLOYEE_CSV='.$path);
        try {
            $this->seed(PrestigeClinicSeeder::class);
            $callback();
        } finally {
            putenv('PRESTIGE_EMPLOYEE_CSV');
            @unlink($path);
        }
    }
}
