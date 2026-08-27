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

            $schedule = DB::table('shift_schedules')->where('display_reference', 'JAD-PRES-NPZ-TEST')->first();
            $this->assertNotNull($schedule);
            $this->assertSame('PRES-01', DB::table('examination_site_refs')->where('id', $schedule->examination_site_id)->value('code'));
            $this->assertSame('SYN-CHEST-A', DB::table('service_offerings')->where('id', $schedule->service_offering_id)->value('code'));
            $this->assertSame(2, (int) $schedule->quota);
            $this->assertSame('open', $schedule->status);
            $now = now()->format('Y-m-d H:i:s');
            $this->assertTrue($schedule->starts_at <= $now && $now < $schedule->ends_at);
            $this->assertSame(2, DB::table('bookings')->where('shift_schedule_id', $schedule->id)->where('status', 'confirmed')->count());
            $this->assertSame(2, DB::table('point_ledger_entries')->where('entry_type', 'credit')->whereIn('member_id', $fixtureIds)->count());
            $this->assertSame(2, DB::table('point_ledger_entries')->where('entry_type', 'charge')->whereIn('booking_id', DB::table('bookings')->where('shift_schedule_id', $schedule->id)->pluck('id'))->count());
            foreach ($fixtureIds as $memberId) {
                $this->assertSame('0.0000', (string) app(Mvp03PointService::class)->personalBalance((string) $memberId));
            }
            $this->assertSame(1, DB::table('operator_eligible_shifts')->where('member_schedule_id', $schedule->id)->count());
            $this->assertSame(5, DB::table('operator_shift_assignments')->whereIn('operator_eligible_shift_id', DB::table('operator_eligible_shifts')->where('member_schedule_id', $schedule->id)->pluck('id'))->count());

            $bookingIds = DB::table('bookings')->where('shift_schedule_id', $schedule->id)->pluck('id');
            $this->assertSame(0, DB::table('operator_arrivals')->whereIn('booking_id', $bookingIds)->count());
            $this->assertSame(0, DB::table('operator_identity_verifications')->whereIn('booking_id', $bookingIds)->count());
            $this->assertSame(0, DB::table('examination_consents')->whereIn('booking_id', $bookingIds)->count());
            $this->assertSame(0, DB::table('operator_paper_tickets')->whereIn('booking_id', $bookingIds)->count());
            $this->assertSame(0, DB::table('operator_queue_admissions')->where('member_schedule_id', $schedule->id)->count());
            $this->assertSame(0, DB::table('member_vital_signs_assessments')->where('member_schedule_id', $schedule->id)->count());
            $this->assertSame(0, DB::table('member_paper_questionnaires')->where('member_schedule_id', $schedule->id)->count());
            $this->assertSame(0, DB::table('image_gateway_capture_sets')->where('member_schedule_id', $schedule->id)->count());
            $this->assertSame(0, DB::table('local_imaging_orders')->where('shift_schedule_id', $schedule->id)->count());
            $this->assertSame(0, DB::table('operator_vital_signs_executions')->whereIn('operator_queue_admission_id', DB::table('operator_queue_admissions')->where('member_schedule_id', $schedule->id)->pluck('id'))->count());
            $this->assertSame(0, DB::table('image_gateway_studies')->whereIn('capture_set_id', DB::table('image_gateway_capture_sets')->where('member_schedule_id', $schedule->id)->pluck('id'))->count());

            $before = [DB::table('users')->count(), DB::table('members')->count(), DB::table('shift_schedules')->count(), DB::table('bookings')->count(), DB::table('point_ledger_entries')->count(), DB::table('operator_eligible_shifts')->count(), DB::table('operator_shift_assignments')->count()];
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $this->assertSame($before, [DB::table('users')->count(), DB::table('members')->count(), DB::table('shift_schedules')->count(), DB::table('bookings')->count(), DB::table('point_ledger_entries')->count(), DB::table('operator_eligible_shifts')->count(), DB::table('operator_shift_assignments')->count()]);
        });
    }

    public function test_operator_can_see_both_subjects_before_manual_workflow(): void
    {
        $this->withPrestige(function (): void {
            $this->seed(PrestigeWebTestMembersSeeder::class);
            $schedule = DB::table('shift_schedules')->where('display_reference', 'JAD-PRES-NPZ-TEST')->firstOrFail();
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
            $eligibleId = DB::table('operator_eligible_shifts')->where('source_event_id', 'prestige:web-test:shift-eligible')->value('id');
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
            $schedule = DB::table('shift_schedules')->where('display_reference', 'JAD-PRES-NPZ-TEST')->firstOrFail();
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
