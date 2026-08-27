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

            foreach (['operator_arrivals', 'operator_identity_verifications', 'operator_paper_tickets', 'operator_queue_admissions', 'member_vital_signs_assessments', 'operator_vital_signs_executions', 'examination_consents', 'image_gateway_capture_sets', 'image_gateway_studies', 'local_imaging_orders'] as $table) {
                $this->assertSame(0, DB::table($table)->whereIn($table === 'operator_arrivals' ? 'booking_id' : ($table === 'operator_paper_tickets' ? 'booking_id' : 'id'), DB::table('bookings')->where('shift_schedule_id', $schedule->id)->pluck('id'))->count(), $table);
            }

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
