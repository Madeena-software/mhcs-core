<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Providers\Filament\Pages\AdminLogin;
use App\Shared\Authorization\AuthorizationClaimResolver;
use App\Shared\Authorization\DatabaseAuthorizationClaimResolver;
use App\Shared\Context\AuthenticatedContextProvider;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MvpAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

final class Mvp02AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.security.identifier_key' => str_repeat('i', 32),
            'mhcs.security.object_key' => str_repeat('o', 32),
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.security.login' => [
                'pair_max_attempts' => 5,
                'origin_max_attempts' => 10,
                'identifier_max_attempts' => 20,
                'decay_seconds' => 60,
            ],
        ]);
    }

    public function test_shared_admin_panel_uses_persisted_exact_claims_and_no_request_claims(): void
    {
        $admin = $this->userWithClaims();

        $this->assertTrue($admin->canAccessPanel(filament()->getPanel('admin')));

        $admin->trusted_roles = ['administrator'];
        $admin->trusted_permissions = ['not-trusted'];
        request()->attributes->set('trusted_roles', ['administrator']);
        request()->attributes->set('trusted_permissions', ['not-trusted']);
        session()->put(['trusted_roles' => ['administrator'], 'trusted_permissions' => ['not-trusted']]);
        $this->actingAs($admin);

        $context = app(AuthenticatedContextProvider::class)->current();

        $this->assertSame(['administrator'], $context->roles);
        $this->assertSame([
            'member.account.manage',
            'member.account.read',
            'member.admin.access',
            'member.audit.read',
        ], $context->permissions);
    }

    public function test_panel_access_fails_closed_for_missing_claims_and_account_gates(): void
    {
        $missingClaims = User::factory()->create();
        $suspended = $this->userWithClaims(['account_status' => 'suspended']);
        $disabled = $this->userWithClaims(['login_enabled' => false]);
        $temporary = $this->userWithClaims(['must_change_password' => true]);
        $wrongRole = User::factory()->create();
        $this->grant($wrongRole, [], ['member.admin.access']);

        foreach ([$missingClaims, $suspended, $disabled, $temporary, $wrongRole] as $user) {
            $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
        }
    }

    public function test_admin_login_is_email_password_only_and_uses_the_shared_credential_path(): void
    {
        $admin = $this->userWithClaims(['email' => 'mvp-admin-login@example.test']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Email')
            ->assertSee('Kata sandi')
            ->assertDontSee('remember');

        $this->withSession(['url.intended' => 'https://external.example/unsafe']);
        $before = $this->app['session.store']->getId();

        Livewire::test(AdminLogin::class)
            ->fillForm(['email' => $admin->email, 'password' => 'admin-password'])
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($before, $this->app['session.store']->getId());
        $this->assertNull($this->app['session.store']->get('url.intended'));
        $this->assertDatabaseHas('audit_events', [
            'action' => 'credential.verify',
            'outcome' => 'success',
            'target_id' => $admin->id,
        ]);
    }

    public function test_admin_login_rejects_all_unauthorized_states_with_one_generic_failure(): void
    {
        $this->get('/admin/login')->assertOk();
        $known = $this->userWithClaims(['email' => 'known-admin@example.test']);
        $noClaims = User::factory()->create([
            'email' => 'no-claims@example.test',
            'password' => Hash::make('admin-password'),
        ]);
        $roleOnly = User::factory()->create([
            'email' => 'role-only@example.test',
            'password' => Hash::make('admin-password'),
        ]);
        $permissionOnly = User::factory()->create([
            'email' => 'permission-only@example.test',
            'password' => Hash::make('admin-password'),
        ]);
        $this->grant($roleOnly, ['administrator'], []);
        $this->grant($permissionOnly, [], ['member.admin.access']);

        $attempts = [
            [$known->email, 'wrong-password'],
            ['unknown-admin@example.test', 'wrong-password'],
            [$noClaims->email, 'admin-password'],
            [$roleOnly->email, 'admin-password'],
            [$permissionOnly->email, 'admin-password'],
        ];

        foreach (['suspended', 'pending_activation'] as $status) {
            $user = $this->userWithClaims([
                'email' => $status.'-admin@example.test',
                'account_status' => $status,
            ]);
            $attempts[] = [$user->email, 'admin-password'];
        }

        foreach ([
            ['email' => 'disabled-admin@example.test', 'login_enabled' => false],
            ['email' => 'change-admin@example.test', 'must_change_password' => true],
        ] as $attributes) {
            $user = $this->userWithClaims($attributes);
            $attempts[] = [$user->email, 'admin-password'];
        }

        $messages = [];
        foreach ($attempts as [$email, $password]) {
            $component = Livewire::test(AdminLogin::class)
                ->fillForm(['email' => $email, 'password' => $password])
                ->call('authenticate')
                ->assertHasErrors(['data.email']);

            $this->assertGuest();
            $messages[] = $component->errors()->first('data.email');
        }

        $this->assertSame(array_fill(0, count($attempts), 'Email atau kata sandi tidak sesuai.'), $messages);
        $audit = DB::table('audit_events')->where('action', 'credential.verify')->get()->toJson();
        $this->assertStringNotContainsString('known-admin@example.test', $audit);
        $this->assertStringNotContainsString('admin-password', $audit);
    }

    public function test_authenticated_panel_access_redirects_to_the_explicit_member_resource(): void
    {
        $this->actingAs($this->userWithClaims())
            ->get('/admin')
            ->assertRedirect('/admin/members');
    }

    public function test_admin_logout_is_post_only_and_invalidates_the_shared_session(): void
    {
        $this->actingAs($this->userWithClaims());

        $this->get('/admin/logout')->assertStatus(405);
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_mvp_admin_seeder_is_explicit_local_only_idempotent_and_never_creates_a_member(): void
    {
        $this->seed(MvpAdminSeeder::class);
        $first = User::query()->where('email', 'mvp-admin@example.test')->firstOrFail();
        $password = $first->password;

        $this->artisan('db:seed', ['--class' => MvpAdminSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('credential and claims were unchanged.')
            ->assertExitCode(0);

        $this->assertSame($password, $first->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('authorization_role_assignments', 1);
        $this->assertDatabaseCount('authorization_permission_assignments', 11);
        $this->assertTrue(Hash::check('admin-password', $password) === false);
    }

    private function userWithClaims(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email' => 'admin-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('admin-password'),
        ], $attributes));

        $this->grant($user, ['administrator'], [
            'member.admin.access',
            'member.account.read',
            'member.account.manage',
            'member.audit.read',
        ]);

        return $user->fresh();
    }

    public function test_claim_resolution_filters_inactive_blank_and_wildcard_assignments_and_observes_deactivation_next_request(): void
    {
        $admin = $this->userWithClaims();
        DB::table('authorization_role_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'role' => 'inactive-role',
            'assigned_by_user_id' => null,
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'permission' => 'member.*',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'permission' => ' ',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/members');

        DB::table('authorization_permission_assignments')
            ->where('user_id', $admin->id)
            ->where('permission', 'member.admin.access')
            ->update(['active' => false]);

        app()->forgetScopedInstances();
        $this->get('/admin')->assertStatus(403);
    }

    public function test_claim_resolver_returns_exact_claim_arrays_and_does_not_mix_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->grant($first, ['member-manager', 'administrator'], ['member.account.read', 'member.admin.access']);
        $this->grant($second, ['operator'], ['operator.queue.read']);

        DB::table('authorization_role_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $first->id,
            'role' => 'inactive-role',
            'assigned_by_user_id' => null,
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $first->id,
            'permission' => ' ',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $first->id,
            'permission' => 'member.*',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = new DatabaseAuthorizationClaimResolver;

        $this->assertSame(['administrator', 'member-manager'], $resolver->roles($first));
        $this->assertSame(['member.account.read', 'member.admin.access'], $resolver->permissions($first));
        $this->assertSame(['operator'], $resolver->roles($second));
        $this->assertSame(['operator.queue.read'], $resolver->permissions($second));
        $this->assertSame([], $resolver->roles((string) Str::uuid()));
        $this->assertSame([], $resolver->permissions((string) Str::uuid()));
    }

    public function test_claim_resolver_fails_closed_when_assignment_storage_is_unavailable(): void
    {
        DB::shouldReceive('table')->andThrow(new RuntimeException('assignment storage unavailable'));

        $resolver = new DatabaseAuthorizationClaimResolver;

        $this->assertSame([], $resolver->roles((string) Str::uuid()));
        $this->assertSame([], $resolver->permissions((string) Str::uuid()));
    }

    public function test_new_scoped_claim_resolver_observes_deactivation_and_untrusted_inputs_add_no_claims(): void
    {
        $admin = $this->userWithClaims();

        request()->attributes->set('trusted_roles', ['attacker']);
        request()->merge(['trusted_permissions' => ['attacker.permission']]);
        session()->put(['trusted_roles' => ['attacker'], 'trusted_permissions' => ['attacker.permission']]);
        $route = new Route('GET', '/admin/{trusted_permissions}', static fn (): string => '');
        $route->bind(Request::create('/admin/attacker.permission'));
        $route->setParameter('trusted_permissions', ['attacker.permission']);
        request()->setRouteResolver(static fn (): Route => $route);
        filament()->setCurrentPanel('admin');
        Livewire::test(AdminLogin::class)
            ->set('data.trusted_roles', ['attacker'])
            ->set('data.trusted_permissions', ['attacker.permission']);
        $this->actingAs($admin);

        $resolver = app(AuthorizationClaimResolver::class);
        $this->assertSame(['administrator'], $resolver->roles($admin));
        $this->assertSame([
            'member.account.manage',
            'member.account.read',
            'member.admin.access',
            'member.audit.read',
        ], $resolver->permissions($admin));

        DB::table('authorization_permission_assignments')
            ->where('user_id', $admin->id)
            ->where('permission', 'member.admin.access')
            ->update(['active' => false]);

        app()->forgetScopedInstances();
        $nextRequestResolver = app(AuthorizationClaimResolver::class);
        $this->assertSame(['administrator'], $nextRequestResolver->roles($admin));
        $this->assertSame([
            'member.account.manage',
            'member.account.read',
            'member.audit.read',
        ], $nextRequestResolver->permissions($admin));
    }

    public function test_admin_login_uses_pair_origin_and_identifier_throttles(): void
    {
        filament()->setCurrentPanel('admin');

        config(['mhcs.security.login' => [
            'pair_max_attempts' => 1,
            'origin_max_attempts' => 5,
            'identifier_max_attempts' => 10,
            'decay_seconds' => 60,
        ]]);
        request()->server->set('REMOTE_ADDR', '10.0.0.1');

        Livewire::test(AdminLogin::class)
            ->fillForm(['email' => 'pair@example.test', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasErrors(['data.email']);
        Livewire::test(AdminLogin::class)
            ->fillForm(['email' => 'pair@example.test', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasErrors(['data.email']);

        config(['mhcs.security.login' => [
            'pair_max_attempts' => 5,
            'origin_max_attempts' => 1,
            'identifier_max_attempts' => 10,
            'decay_seconds' => 60,
        ]]);
        request()->server->set('REMOTE_ADDR', '10.0.0.2');
        foreach (['origin-one@example.test', 'origin-two@example.test'] as $email) {
            Livewire::test(AdminLogin::class)
                ->fillForm(['email' => $email, 'password' => 'wrong'])
                ->call('authenticate')
                ->assertHasErrors(['data.email']);
        }

        config(['mhcs.security.login' => [
            'pair_max_attempts' => 1,
            'origin_max_attempts' => 2,
            'identifier_max_attempts' => 3,
            'decay_seconds' => 60,
        ]]);
        foreach (range(1, 4) as $attempt) {
            request()->server->set('REMOTE_ADDR', '10.0.0.'.$attempt);
            Livewire::test(AdminLogin::class)
                ->fillForm(['email' => 'identifier@example.test', 'password' => 'wrong'])
                ->call('authenticate')
                ->assertHasErrors(['data.email']);
        }

        $this->assertGreaterThanOrEqual(3, DB::table('audit_events')
            ->where('action', 'credential.verify')
            ->where('outcome', 'failure')
            ->where('metadata', 'like', '%rate_limited%')
            ->count());
        $this->assertGuest();
    }

    public function test_existing_admin_reconciles_only_missing_claims_and_preserves_password_hash(): void
    {
        $this->seed(MvpAdminSeeder::class);
        $admin = User::query()->where('email', 'mvp-admin@example.test')->firstOrFail();
        $password = $admin->password;

        DB::table('authorization_role_assignments')->where('user_id', $admin->id)->delete();
        DB::table('authorization_permission_assignments')
            ->where('user_id', $admin->id)
            ->where('permission', 'member.audit.read')
            ->delete();

        $this->artisan('db:seed', ['--class' => MvpAdminSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('missing bootstrap claims were reconciled.')
            ->doesntExpectOutputToContain('credential and claims were unchanged.')
            ->doesntExpectOutputToContain('admin-password')
            ->assertExitCode(0);

        $this->assertSame($password, $admin->fresh()->password);
        $this->assertDatabaseHas('authorization_role_assignments', [
            'user_id' => $admin->id,
            'role' => 'administrator',
            'assigned_by_user_id' => null,
            'active' => true,
        ]);
        $this->assertDatabaseHas('authorization_permission_assignments', [
            'user_id' => $admin->id,
            'permission' => 'member.audit.read',
            'assigned_by_user_id' => null,
            'active' => true,
        ]);
        $this->assertDatabaseCount('authorization_role_assignments', 1);
        $this->assertDatabaseCount('authorization_permission_assignments', 11);
    }

    public function test_existing_admin_stops_on_inactive_or_unrelated_claims_without_reactivating_them(): void
    {
        $this->seed(MvpAdminSeeder::class);
        $admin = User::query()->where('email', 'mvp-admin@example.test')->firstOrFail();

        DB::table('authorization_role_assignments')
            ->where('user_id', $admin->id)
            ->where('role', 'administrator')
            ->update(['active' => false]);

        try {
            $this->seed(MvpAdminSeeder::class);
            $this->fail('An inactive role assignment must stop reconciliation.');
        } catch (RuntimeException) {
            // Expected fail-closed behavior.
        }

        $this->assertDatabaseHas('authorization_role_assignments', [
            'user_id' => $admin->id,
            'role' => 'administrator',
            'active' => false,
        ]);
    }

    public function test_existing_admin_stops_on_an_inactive_permission(): void
    {
        $this->seed(MvpAdminSeeder::class);
        $admin = User::query()->where('email', 'mvp-admin@example.test')->firstOrFail();

        DB::table('authorization_permission_assignments')
            ->where('user_id', $admin->id)
            ->where('permission', 'member.audit.read')
            ->update(['active' => false]);

        try {
            $this->seed(MvpAdminSeeder::class);
            $this->fail('An inactive permission assignment must stop reconciliation.');
        } catch (RuntimeException) {
            // Expected fail-closed behavior.
        }

        $this->assertDatabaseHas('authorization_permission_assignments', [
            'user_id' => $admin->id,
            'permission' => 'member.audit.read',
            'active' => false,
        ]);
    }

    public function test_existing_admin_stops_on_unrelated_assignments(): void
    {
        $this->seed(MvpAdminSeeder::class);
        $admin = User::query()->where('email', 'mvp-admin@example.test')->firstOrFail();

        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'permission' => 'unrelated.permission',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->seed(MvpAdminSeeder::class);
    }

    public function test_mvp_admin_seeder_is_not_invoked_by_database_seeder_and_refuses_non_local_environments(): void
    {
        (new DatabaseSeeder)->run();
        $this->assertDatabaseMissing('users', ['email' => 'mvp-admin@example.test']);

        app()->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        (new MvpAdminSeeder)->run();
    }

    /** @param list<string> $roles @param list<string> $permissions */
    private function grant(User $user, array $roles, array $permissions): void
    {
        foreach ($roles as $role) {
            DB::table('authorization_role_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'role' => $role,
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'permission' => $permission,
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
