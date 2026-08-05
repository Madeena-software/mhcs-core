<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Providers\Filament\Pages\AdminLogin;
use App\Shared\Context\AuthenticatedContextProvider;
use Database\Seeders\MvpAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
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

        $this->seed(MvpAdminSeeder::class);

        $this->assertSame($password, $first->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('authorization_role_assignments', 1);
        $this->assertDatabaseCount('authorization_permission_assignments', 4);
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
