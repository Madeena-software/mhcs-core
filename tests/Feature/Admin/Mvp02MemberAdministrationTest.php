<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Member\Application\Services\AccountStateService;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Filament\Resources\Members\Pages\ListMembers;
use App\Modules\Member\Filament\Resources\Members\Pages\ViewMember;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class Mvp02MemberAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_list_and_detail_are_bounded_to_safe_fields(): void
    {
        $admin = $this->admin(['member.account.read', 'member.audit.read']);
        [$member, $nik] = $this->member();

        $this->actingAs($admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($member->medical_record_number)
            ->assertDontSee($nik)
            ->assertDontSee('encrypted_nik')
            ->assertDontSee('nik_lookup_digest')
            ->assertDontSee('remember_token')
            ->assertDontSee('name="data[password]"', false);

        $this->actingAs($admin)
            ->get('/admin/members/'.$member->id)
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('Kelengkapan profil')
            ->assertSee('Audit Member')
            ->assertDontSee($nik)
            ->assertDontSee('current_address')
            ->assertDontSee('session_id')
            ->assertDontSee('roles')
            ->assertDontSee('permissions');
    }

    public function test_member_read_and_audit_permissions_are_independent_and_mutations_are_not_registered(): void
    {
        $readOnly = $this->admin(['member.account.read']);
        [$member] = $this->member();

        $this->actingAs($readOnly)->get('/admin/members')->assertOk();
        $this->actingAs($readOnly)->get('/admin/members/'.$member->id)->assertOk()->assertDontSee('Audit Member');
        $this->assertStringNotContainsString('/create', (string) $this->app['router']->getRoutes()->getByName('filament.admin.resources.members.index')?->uri());
        $this->assertNull($this->app['router']->getRoutes()->getByName('filament.admin.resources.members.create'));
        $this->assertNull($this->app['router']->getRoutes()->getByName('filament.admin.resources.members.edit'));
    }

    public function test_member_list_and_detail_require_account_read(): void
    {
        $admin = $this->admin([]);
        [$member] = $this->member();

        $this->actingAs($admin)->get('/admin/members')->assertForbidden();
        $this->actingAs($admin)->get('/admin/members/'.$member->id)->assertForbidden();
    }

    public function test_account_state_service_is_the_only_state_transition_path_and_audit_is_target_bounded(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage', 'member.audit.read']);
        [$member] = $this->member();
        $unrelated = $this->member()[0];

        $this->actingAs($admin)->get('/admin/members/'.$member->id)->assertOk();

        app(AccountStateService::class)->suspend((string) $member->user_id, 'bounded test reason');
        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'suspended']);

        $response = $this->actingAs($admin)->get('/admin/members/'.$member->id);
        $response->assertOk()->assertSee('bounded test reason')->assertDontSee($unrelated->id);
    }

    public function test_suspend_action_requires_reason_and_invokes_account_state_service(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage']);
        [$member] = $this->member();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListMembers::class)
            ->assertTableActionExists('suspend', null, $member)
            ->assertTableActionVisible('suspend', $member)
            ->callTableAction('suspend', $member, ['reason' => 'bounded action reason']);

        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'suspended']);
    }

    public function test_unauthorized_livewire_audit_table_cannot_retrieve_a_known_value(): void
    {
        $writer = $this->admin(['member.account.read', 'member.account.manage', 'member.audit.read']);
        [$member] = $this->member();

        $this->actingAs($writer);
        app(AccountStateService::class)->suspend((string) $member->user_id, 'known audit value');

        $readOnly = $this->admin(['member.account.read']);
        $this->actingAs($readOnly);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewMember::class, ['record' => $member->id])
            ->assertDontSee('known audit value')
            ->assertCountTableRecords(0);
    }

    public function test_account_actions_reauthorize_at_execution_and_reject_invalid_targets_and_reasons(): void
    {
        [$member] = $this->member();
        $readOnly = $this->admin(['member.account.read']);

        $this->actingAs($readOnly);
        Filament::setCurrentPanel('admin');
        Livewire::test(ListMembers::class)
            ->assertTableActionHidden('suspend', $member)
            ->mountTableAction('suspend', $member)
            ->setTableActionData(['reason' => 'read-only execution attempt'])
            ->callMountedTableAction();
        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'active']);

        $self = $this->admin(['member.account.read', 'member.account.manage']);
        [$selfMember] = $this->member($self);
        $this->actingAs($self);
        Livewire::test(ListMembers::class)
            ->assertTableActionHidden('suspend', $selfMember)
            ->mountTableAction('suspend', $selfMember)
            ->setTableActionData(['reason' => 'self execution attempt'])
            ->callMountedTableAction();
        $this->assertDatabaseHas('users', ['id' => $self->id, 'account_status' => 'active']);

        [$pending] = $this->member();
        DB::table('users')->where('id', $pending->user_id)->update(['account_status' => 'pending_activation']);
        Livewire::test(ListMembers::class)
            ->mountTableAction('suspend', $pending)
            ->setTableActionData(['reason' => 'pending execution attempt'])
            ->callMountedTableAction();
        $this->assertDatabaseHas('users', ['id' => $pending->user_id, 'account_status' => 'pending_activation']);
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'member.account-state',
            'target_id' => $pending->user_id,
            'outcome' => 'suspended',
        ]);
    }

    public function test_suspend_and_restore_use_the_server_record_and_keep_member_access_closed_after_suspension(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage', 'member.audit.read']);
        [$member] = $this->member();
        [$other] = $this->member();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(ListMembers::class)
            ->callTableAction('suspend', $member, [
                'reason' => '  execution boundary reason  ',
                'user_id' => $other->user_id,
                'target_state' => 'active',
            ]);

        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'suspended']);
        $this->assertDatabaseHas('users', ['id' => $other->user_id, 'account_status' => 'active']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.account-state',
            'outcome' => 'suspended',
            'target_id' => $member->user_id,
            'reason' => 'execution boundary reason',
        ]);

        Auth::logout();
        $this->actingAs(User::query()->findOrFail($member->user_id))
            ->get('/member/dashboard')
            ->assertRedirect(route('login'));

        $this->actingAs($admin);
        Livewire::test(ListMembers::class)
            ->callTableAction('restore', $member, ['reason' => 'restore boundary reason']);

        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'active']);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.account-state',
            'outcome' => 'active',
            'target_id' => $member->user_id,
            'reason' => 'restore boundary reason',
        ]);
    }

    public function test_authorized_audit_is_newest_first_and_excludes_unrelated_members(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage', 'member.audit.read']);
        [$member] = $this->member();
        [$unrelated] = $this->member();

        $this->actingAs($admin);
        app(AccountStateService::class)->suspend((string) $member->user_id, 'older audit value');
        app(AccountStateService::class)->suspend((string) $unrelated->user_id, 'unrelated audit value');
        app(AccountStateService::class)->restore((string) $member->user_id, 'newer audit value');
        DB::table('audit_events')->where('reason', 'older audit value')->update(['occurred_at' => '2026-08-05 00:00:00']);
        DB::table('audit_events')->where('reason', 'newer audit value')->update(['occurred_at' => '2026-08-05 00:01:00']);

        Filament::setCurrentPanel('admin');
        Livewire::test(ViewMember::class, ['record' => $member->id])
            ->assertSeeInOrder(['newer audit value', 'older audit value'])
            ->assertDontSee('unrelated audit value');
    }

    /** @param list<string> $permissions */
    private function admin(array $permissions): User
    {
        $user = User::factory()->create([
            'email' => 'admin-'.Str::random(8).'@example.test',
            'password' => Hash::make('admin-password'),
        ]);

        DB::table('authorization_role_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'role' => 'administrator',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (array_values(array_unique(['member.admin.access', ...$permissions])) as $permission) {
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

        return $user->fresh();
    }

    /** @return array{0: Member, 1: string} */
    private function member(?User $existingUser = null): array
    {
        $user = $existingUser ?? User::factory()->create(['email' => 'member-'.Str::random(8).'@example.test']);
        $nik = '900000000'.str_pad((string) User::query()->count(), 3, '0', STR_PAD_LEFT);
        $memberId = (string) Str::uuid();

        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => (string) Str::uuid(),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'synthetic-protected-value',
            'nik_lookup_digest' => hash('sha256', $nik),
            'name' => 'Synthetic Admin Member',
            'birth_date' => '1985-08-04',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Private address should not render',
            'emergency_contact_name' => 'Private contact should not render',
            'emergency_contact_relationship' => null,
            'emergency_contact_phone' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [Member::query()->findOrFail($memberId), $nik];
    }
}
