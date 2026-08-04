<?php

declare(strict_types=1);

namespace Tests\Feature\Member;

use App\Models\User;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Security\ProtectedIdentifierService;
use Database\Seeders\MvpMemberSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp01MemberAccessTest extends TestCase
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
        Storage::fake('local');
        RateLimiter::clear('credential:'.hash('sha256', 'mvp01'));
    }

    public function test_required_routes_and_guest_login_form_exist(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="identifier"', false)->assertSee('name="password"', false);

        foreach ([
            ['GET', '/login', 'login'],
            ['POST', '/login', 'login.store'],
            ['GET', '/password/change-required', 'password.change-required'],
            ['POST', '/password/change-required', 'password.change-required.update'],
            ['GET', '/member/profile', 'member.profile'],
            ['PATCH', '/member/profile', 'member.profile.update'],
            ['GET', '/member/dashboard', 'member.dashboard'],
            ['POST', '/logout', 'logout'],
        ] as [$method, $uri, $name]) {
            $route = collect(Route::getRoutes()->getRoutes())->first(static fn ($route): bool => $route->getName() === $name);
            $this->assertNotNull($route);
            $this->assertContains($method, $route->methods());
            $this->assertSame(ltrim($uri, '/'), $route->uri());
        }
    }

    public function test_email_and_nik_login_are_generic_and_regenerate_the_session(): void
    {
        [$user, $nik] = $this->member(['email' => 'login-member@example.test', 'password' => Hash::make('member-password')]);
        $before = $this->app['session.store']->getId();

        $response = $this->withSession(['url.intended' => 'https://external.example/unsafe'])->post('/login', [
            'identifier' => ' LOGIN-MEMBER@EXAMPLE.TEST ',
            'password' => 'member-password',
        ]);

        $response->assertRedirect(route('member.profile'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, $this->app['session.store']->getId());
        $this->assertNotSame('https://external.example/unsafe', $response->headers->get('Location'));

        Auth::logout();
        $this->post('/login', ['identifier' => $nik, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['identifier'])
            ->assertDontSee($nik);

        $this->post('/login', ['identifier' => $nik, 'password' => 'member-password'])
            ->assertRedirect(route('member.profile'));
    }

    public function test_unknown_and_wrong_credentials_are_generic_and_rejected_accounts_cannot_login(): void
    {
        $this->member(['email' => 'known-member@example.test', 'password' => Hash::make('member-password')]);
        $wrong = $this->post('/login', ['identifier' => 'known-member@example.test', 'password' => 'wrong-password']);
        $unknown = $this->post('/login', ['identifier' => 'unknown-member@example.test', 'password' => 'wrong-password']);

        $wrong->assertSessionHasErrors(['identifier']);
        $unknown->assertSessionHasErrors(['identifier']);
        $this->assertSame($wrong->getSession()->get('errors')->first('identifier'), $unknown->getSession()->get('errors')->first('identifier'));

        foreach (['suspended', 'pending'] as $status) {
            $this->post('/login', ['identifier' => "{$status}-member@example.test", 'password' => 'member-password']);
            [$user] = $this->member(['email' => "{$status}-member@example.test", 'password' => Hash::make('member-password'), 'account_status' => $status]);
            $response = $this->post('/login', ['identifier' => $user->email, 'password' => 'member-password']);
            $response->assertSessionHasErrors(['identifier']);
        }

        [$disabled] = $this->member(['email' => 'disabled-member@example.test', 'password' => Hash::make('member-password'), 'login_enabled' => false]);
        $this->post('/login', ['identifier' => $disabled->email, 'password' => 'member-password'])->assertSessionHasErrors(['identifier']);
    }

    public function test_mandatory_change_account_enters_restricted_session_and_strict_auth_stays_strict(): void
    {
        [$user] = $this->member([
            'email' => 'temporary-member@example.test',
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'temporary-password'])
            ->assertRedirect(route('password.change-required'));
        $this->assertAuthenticatedAs($user);
        $this->get('/member/profile')->assertRedirect(route('password.change-required'));
        $this->get('/member/dashboard')->assertRedirect(route('password.change-required'));

        Auth::logout();
        $this->assertFalse(Auth::attempt(['email' => $user->email, 'password' => 'temporary-password']));
    }

    public function test_password_replacement_is_restricted_atomic_and_regenerates_session(): void
    {
        [$user] = $this->member([
            'email' => 'replace-member@example.test',
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'temporary-password']);
        $before = $this->app['session.store']->getId();
        $this->post('/password/change-required', [
            'current_password' => 'temporary-password',
            'password' => 'replacement-password-1',
            'password_confirmation' => 'replacement-password-1',
        ])->assertRedirect(route('member.profile'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('replacement-password-1', $user->password));
        $this->assertFalse(Hash::check('temporary-password', $user->password));
        $this->assertNotSame($before, $this->app['session.store']->getId());
        $this->assertStringNotContainsString('temporary-password', json_encode(DB::table('audit_events')->get()->all(), JSON_THROW_ON_ERROR));
    }

    public function test_invalid_password_replacement_keeps_the_old_credential_and_flag(): void
    {
        [$user] = $this->member([
            'email' => 'failed-replace@example.test',
            'password' => Hash::make('temporary-password'),
            'must_change_password' => true,
        ]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'temporary-password']);
        $this->post('/password/change-required', [
            'current_password' => 'wrong-password',
            'password' => 'weak',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors();

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('temporary-password', $user->password));
    }

    public function test_profile_update_is_server_owned_atomic_and_completion_redirects_to_dashboard(): void
    {
        [$user, $nik, $member] = $this->member(['email' => 'profile-member@example.test', 'password' => Hash::make('member-password')]);
        $this->actingAs($user);

        $this->patch('/member/profile', [
            'email' => 'PROFILE-MEMBER@EXAMPLE.TEST',
            'phone' => '081234567890',
            'current_address' => 'Alamat sintetis beta',
            'emergency_contact_name' => 'Kontak Sintetis',
            'emergency_contact_relationship' => 'Saudara',
            'emergency_contact_phone' => '081111111111',
            'name' => 'Tidak boleh berubah',
            'nik_lookup_digest' => 'tidak boleh berubah',
            'medical_record_number' => 'tidak boleh berubah',
        ])->assertRedirect(route('member.dashboard'));

        $member->refresh();
        $user->refresh();
        $this->assertSame('profile-member@example.test', $user->email);
        $this->assertSame($nik, app(ProtectedIdentifierService::class)->display($member->encrypted_nik));
        $this->assertSame('Synthetic Member', $member->name);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'member.profile-update')->count());
        $audit = (string) DB::table('audit_events')->where('action', 'member.profile-update')->value('metadata');
        $this->assertStringContainsString('current_address', $audit);
        $this->assertStringNotContainsString('Alamat sintetis beta', $audit);
    }

    public function test_profile_patch_preserves_approved_fields_omitted_from_a_partial_request(): void
    {
        [$user] = $this->member([
            'email' => 'partial-profile@example.test',
            'password' => Hash::make('member-password'),
        ]);
        DB::table('members')->where('user_id', $user->id)->update([
            'phone' => '081234567890',
            'current_address' => 'Alamat lama',
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->patch('/member/profile', ['emergency_contact_name' => 'Kontak Baru']);

        $this->assertDatabaseHas('members', [
            'user_id' => $user->id,
            'phone' => '081234567890',
            'current_address' => 'Alamat lama',
            'emergency_contact_name' => 'Kontak Baru',
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'partial-profile@example.test']);
    }

    public function test_dashboard_is_safe_and_logout_is_post_only(): void
    {
        [$user, $nik] = $this->member(['email' => 'dashboard-member@example.test', 'password' => Hash::make('member-password')]);
        $response = $this->actingAs($user)->get('/member/dashboard');
        $response->assertOk()->assertSee('Synthetic Member')->assertSee('Nomor rekam medis')->assertDontSee($nik)->assertDontSee('nik_lookup_digest');

        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/member/dashboard')->assertRedirect(route('login'));
    }

    public function test_synthetic_seeder_is_local_only_idempotent_and_protects_credentials_and_assets(): void
    {
        $this->seed(MvpMemberSeeder::class);
        $firstPasswords = User::query()->pluck('password', 'email')->all();

        $this->seed(MvpMemberSeeder::class);

        $this->assertCount(2, User::query()->get());
        $this->assertCount(2, DB::table('members')->get());
        $this->assertCount(4, DB::table('member_verification_assets')->get());
        $this->assertSame($firstPasswords, User::query()->pluck('password', 'email')->all());
        $this->assertTrue(User::query()->where('must_change_password', true)->count() === 2);
        $this->assertStringNotContainsString('development-only credential', json_encode(DB::table('audit_events')->get()->all(), JSON_THROW_ON_ERROR));
    }

    /** @return array{0: User, 1: string, 2: Member} */
    private function member(array $userAttributes = []): array
    {
        $user = User::factory()->create($userAttributes);
        $nik = '900000000'.str_pad((string) User::query()->count(), 3, '0', STR_PAD_LEFT);
        $protected = app(ProtectedIdentifierService::class)->protect($nik);
        $memberId = (string) Str::uuid();

        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => (string) Str::uuid(),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protected['encrypted_display'],
            'nik_lookup_digest' => $protected['lookup_digest'],
            'name' => 'Synthetic Member',
            'birth_date' => '1985-08-04',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => null,
            'emergency_contact_name' => null,
            'emergency_contact_relationship' => null,
            'emergency_contact_phone' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user->fresh(), $nik, Member::query()->findOrFail($memberId)];
    }
}
