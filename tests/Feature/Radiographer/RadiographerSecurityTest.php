<?php

declare(strict_types=1);

namespace Tests\Feature\Radiographer;

use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use DateTimeImmutable;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RadiographerFixtures;
use Tests\TestCase;

final class RadiographerSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    private function issue(array $f, int $hours = 24): array
    {
        $this->actingAs($f['admin']);

        return app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', ['user' => $f['worker']->id, 'hours' => $hours]);
    }

    public static function lifetimes(): array
    {
        return [[24], [72], [168]];
    }

    #[DataProvider('lifetimes')]
    public function test_allowed_lifetimes_and_exact_expiry(int $hours): void
    {
        $f = RadiographerFixtures::create();
        $now = new DateTimeImmutable('2040-01-01T00:00:00Z');
        $this->app->instance(Clock::class, new FrozenClock($now));
        $w = $this->issue($f, $hours);
        $this->actingAs($f['worker']);
        app(RadiographerWorkspace::class)->entry($w['id'], 1);
        $this->app->instance(Clock::class, new FrozenClock($now->modify('+'.$hours.' hours')));
        $this->expectException(\DomainException::class);
        app(RadiographerWorkspace::class)->entry($w['id'], 1);
    }

    public static function denials(): array
    {
        return array_map(fn ($case) => [$case], ['monitor_only', 'unmanaged', 'unverified', 'revoked_qualification', 'inactive_site', 'inactive_account', 'invalid_hours', 'extra_scope']);
    }

    #[DataProvider('denials')]
    public function test_issuance_denials_never_create_grants(string $case): void
    {
        $f = RadiographerFixtures::create();
        $this->actingAs($f['admin']);
        $input = ['user' => $f['worker']->id, 'hours' => 24];
        match ($case) {
            'monitor_only' => DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'like', 'operator.radiographer-workspace.%')->update(['active' => false]),
            'unmanaged' => DB::table('operator_workspace_managers')->update(['active' => false]),
            'unverified', 'revoked_qualification' => DB::table('operator_radiographer_eligibility')->update(['status' => $case]),
            'inactive_site' => DB::table('operator_sites')->update(['active' => false]),
            'inactive_account' => DB::table('users')->where('id', $f['worker']->id)->update(['account_status' => 'suspended']),
            'invalid_hours' => $input['hours'] = 48,
            'extra_scope' => $input['site'] = $f['operatorSiteId'],
        };
        try {
            app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', $input);
            $this->fail('Unsafe issuance');
        } catch (\DomainException) {
        }
        $this->assertSame(0, DB::table('operator_radiographer_workspaces')->count());
    }

    public function test_reassignment_old_generation_forwarding_tampering_and_login(): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->issue($f);
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertRedirect();
        $this->get(str_replace('generation=1', 'generation=2', $w['url']))->assertForbidden();
        $this->actingAs($f['admin']);
        $new = app(RadiographerWorkspace::class)->manage($f['reference'], 'reassign', ['user' => $f['worker']->id, 'hours' => 72]);
        $this->actingAs($f['worker']);
        $this->get('/radiographer/workspaces/'.$w['id'])->assertForbidden();
        $this->get($w['url'])->assertForbidden();
        $this->get($new['url'])->assertRedirect();
        $this->post('/logout')->assertRedirect('/login');
        $this->get($new['url'])->assertRedirect('/login');
        $before = session()->getId();
        $this->post('/login', ['identifier' => $f['worker']->email, 'password' => 'password'])->assertRedirect($new['url']);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_same_actor_reassignment_cannot_upgrade_a_stale_service_generation(): void
    {
        $f = RadiographerFixtures::create();
        $w = $this->issue($f);
        $this->actingAs($f['admin']);
        app(RadiographerWorkspace::class)->manage($f['reference'], 'reassign', ['user' => $f['worker']->id, 'hours' => 24]);
        $this->actingAs($f['worker']);
        $this->expectException(\DomainException::class);
        app(RadiographerWorkspace::class)->read($w['id'], 1);
    }

    public function test_audit_append_failure_rolls_back_issuance_and_denies(): void
    {
        $f = RadiographerFixtures::create();
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('synthetic-failure');
            }
        });
        $this->actingAs($f['admin']);
        $this->postJson('/admin/radiographer-workspaces/'.$f['reference'], ['action' => 'issue', 'user' => $f['worker']->id, 'hours' => 24])->assertStatus(503);
        $this->assertSame(0, DB::table('operator_radiographer_workspaces')->count());
        $this->assertSame(0, DB::table('operator_radiographer_history')->count());
    }

    public function test_required_commit_failure_rolls_back_issuance(): void
    {
        $f = RadiographerFixtures::create();
        $this->app['events']->listen(TransactionCommitting::class, function (): void {
            throw new \RuntimeException('synthetic-commit-failure');
        });
        $this->actingAs($f['admin']);
        try {
            app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', ['user' => $f['worker']->id]);
            $this->fail('Commit failure succeeded');
        } catch (\DomainException) {
        }
        $this->assertSame(0, DB::table('operator_radiographer_workspaces')->count());
    }
}
