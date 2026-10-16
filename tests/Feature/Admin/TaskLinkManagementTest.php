<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\Doctor\Application\Services\RadiologistWorkspace;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RadiographerFixtures;
use Tests\Support\RadiologistFixtures;
use Tests\TestCase;

final class TaskLinkManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    public static function professions(): array
    {
        return [['radiographer'], ['radiologist']];
    }

    private function fixture(string $role): array
    {
        $f = $role === 'radiographer' ? RadiographerFixtures::create() : RadiologistFixtures::create();
        $this->actingAs($f['admin']);
        $url = '/admin/'.$role.'-workspaces/'.$f[$role === 'radiographer' ? 'reference' : 'handoff'];
        if ($role === 'radiologist') {
            $this->postJson($url, ['action' => 'credential', 'user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC-ONLY', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password'])->assertOk();
        }

        return [$f, $url, ['action' => 'issue', 'user' => $f[$role === 'radiographer' ? 'worker' : 'doctor']->id] + ($role === 'radiologist' ? ['password' => 'password'] : [])];
    }

    private function emit($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    #[DataProvider('professions')]
    public function test_issuance_redirect_and_refresh_never_replay_or_renew(string $role): void
    {
        [$f, $url, $input] = $this->fixture($role);
        $this->get($url)->assertOk()->assertSee('Belum ditugaskan');
        $this->post($url, $input)->assertStatus(303)->assertRedirect($url);
        $result = $this->get($url)->assertOk()->assertSee('Akses aktif')->assertSee('Salin tautan')->assertSee('copy-status');
        $this->assertStringContainsString('assignment-url', $this->emit($result->baseResponse));
        $table = $role === 'radiographer' ? 'operator_radiographer_workspaces' : 'doctor_workspaces';
        $before = (array) DB::table($table)->first();
        $this->get($url)->assertOk()->assertDontSee('id="assignment-url"', false);
        $this->head($url)->assertOk();
        $this->assertSame($before, (array) DB::table($table)->first());
        $this->assertSame(0, DB::table('member_result_publications')->count());
    }

    #[DataProvider('professions')]
    public function test_current_assignee_expiry_and_entitlement_states_are_owner_reads(string $role): void
    {
        [$f, $url, $input] = $this->fixture($role);
        $w = $this->postJson($url, $input)->assertOk()->json();
        $read = $this->getJson($url)->assertOk();
        $read->assertJsonPath('access_status', 'active')->assertJsonPath('assignee', $f[$role === 'radiographer' ? 'worker' : 'doctor']->email);
        $expires = $read->json('expires_at');
        $this->get($url)->assertSee($expires)->assertSee('Kembali ke daftar studi');
        $this->app->instance(Clock::class, new FrozenClock(new \DateTimeImmutable($expires)));
        $this->getJson($url)->assertOk()->assertJsonPath('access_status', 'expired');
        $this->postJson($url, ['action' => 'revoke'] + ($role === 'radiologist' ? ['generation' => $w['generation'], 'password' => 'password'] : []))->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('access_status', 'revoked');
        $this->actingAs($f[$role === 'radiographer' ? 'worker' : 'doctor']);
        $this->get($url)->assertForbidden()->assertDontSee($expires)->assertSee('hubungi Admin');
    }

    #[DataProvider('professions')]
    public function test_withdrawn_eligibility_is_not_presented_as_active(string $role): void
    {
        [$f, $url, $input] = $this->fixture($role);
        $this->postJson($url, $input)->assertOk();
        DB::table($role === 'radiographer' ? 'operator_radiographer_eligibility' : 'doctor_eligibility')->update(['status' => 'revoked']);
        $this->getJson($url)->assertOk()->assertJsonPath('access_status', 'unavailable');
    }

    public function test_reverified_doctor_requires_a_new_assignment_binding(): void
    {
        [$f, $url, $input] = $this->fixture('radiologist');
        $this->postJson($url, $input)->assertOk();
        $this->postJson($url, ['action' => 'credential', 'user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC-REVERIFIED', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('access_status', 'unavailable');
    }

    public static function staleResponses(): array
    {
        $cases = [];
        foreach (['radiographer', 'radiologist'] as $role) {
            foreach (['reassign', 'revoke', 'expire', 'eligibility'] as $change) {
                foreach (['html', 'json', 'head'] as $format) {
                    $cases[] = [$role, $change, $format];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('staleResponses')]
    public function test_prepared_management_response_withholds_obsolete_state(string $role, string $change, string $format): void
    {
        [$f, $url, $input] = $this->fixture($role);
        $w = $this->postJson($url, $input)->assertOk()->json();
        if ($format === 'html') {
            $this->post($url, array_replace($input, ['action' => 'reassign'] + ($role === 'radiologist' ? ['generation' => $w['generation']] : [])))->assertStatus(303);
        }
        $response = match ($format) {
            'json' => $this->postJson($url, array_replace($input, ['action' => 'reassign'] + ($role === 'radiologist' ? ['generation' => $w['generation']] : [])))->assertOk()->baseResponse,
            'head' => $this->head($url)->assertOk()->baseResponse,
            default => $this->get($url)->assertOk()->baseResponse,
        };
        if ($change === 'expire') {
            $expires = DB::table($role === 'radiographer' ? 'operator_radiographer_workspaces' : 'doctor_workspaces')->value('expires_at');
            $this->app->instance(Clock::class, new FrozenClock(new \DateTimeImmutable($expires)));
        } elseif ($change === 'eligibility') {
            DB::table($role === 'radiographer' ? 'operator_radiographer_eligibility' : 'doctor_eligibility')->update(['status' => 'revoked']);
        } else {
            $service = app($role === 'radiographer' ? RadiographerWorkspace::class : RadiologistWorkspace::class);
            request()->session()->start();
            $generation = DB::table($role === 'radiographer' ? 'operator_radiographer_workspaces' : 'doctor_workspaces')->value('generation');
            $command = $change === 'reassign' ? ['user' => $input['user']] : [];
            if ($role === 'radiologist') {
                $command += ['generation' => $generation, 'password' => 'password'];
            }
            $service->manage($f[$role === 'radiographer' ? 'reference' : 'handoff'], $change, $command);
        }
        $body = $this->emit($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('signature=', $body);
        $this->assertStringNotContainsString($f[$role === 'radiographer' ? 'worker' : 'doctor']->email, $body);
        $this->assertStringNotContainsString('SYNTHETIC-MRN', $body);
        if ($format === 'head') {
            $this->assertSame('', $body);
        }
    }

    #[DataProvider('professions')]
    public function test_flash_result_is_discarded_when_generation_changes_before_get(string $role): void
    {
        [$f, $url, $input] = $this->fixture($role);
        $this->post($url, $input)->assertStatus(303);
        DB::table($role === 'radiographer' ? 'operator_radiographer_workspaces' : 'doctor_workspaces')->increment('generation');
        $this->get($url)->assertOk()->assertDontSee('id="assignment-url"', false);
    }
}
