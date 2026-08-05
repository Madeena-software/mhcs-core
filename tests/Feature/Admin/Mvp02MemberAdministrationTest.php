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
        $memberUser = User::query()->findOrFail($member->user_id);
        $rememberToken = 'remember-token-marker';
        DB::table('users')->where('id', $memberUser->id)->update(['remember_token' => $rememberToken]);
        $nikDigest = (string) DB::table('members')->where('id', $member->id)->value('nik_lookup_digest');

        $this->actingAs($admin)
            ->get('/admin/members')
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee($member->medical_record_number)
            ->assertDontSee($nik)
            ->assertDontSee('synthetic-protected-value')
            ->assertDontSee($nikDigest)
            ->assertDontSee('Private address should not render')
            ->assertDontSee('Private contact should not render')
            ->assertDontSee($memberUser->password)
            ->assertDontSee($rememberToken)
            ->assertDontSee('name="data[password]"', false);

        $this->actingAs($admin)
            ->get('/admin/members/'.$member->id)
            ->assertOk()
            ->assertSee($member->name)
            ->assertSee('Kelengkapan profil')
            ->assertSee('Audit Member')
            ->assertDontSee($nik)
            ->assertDontSee($nikDigest)
            ->assertDontSee('Private address should not render')
            ->assertDontSee('Private contact should not render')
            ->assertDontSee($memberUser->password)
            ->assertDontSee($rememberToken)
            ->assertDontSee('session_id')
            ->assertDontSee('roles')
            ->assertDontSee('permissions');
    }

    public function test_member_name_mrn_and_email_search_each_isolates_the_intended_record(): void
    {
        $admin = $this->admin(['member.account.read']);
        [$byName] = $this->member(null, [
            'name' => 'Search Name Alpha',
            'medical_record_number' => 'MRN-SEARCH-ALPHA',
        ]);
        [$byMrn] = $this->member(null, [
            'name' => 'Search MRN Beta',
            'medical_record_number' => 'MRN-SEARCH-BETA',
        ]);
        $emailUser = User::factory()->create(['email' => 'search-email@example.test']);
        [$byEmail] = $this->member($emailUser, [
            'name' => 'Search Email Gamma',
            'medical_record_number' => 'MRN-SEARCH-GAMMA',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListMembers::class)
            ->searchTable('Search Name Alpha')
            ->assertCanSeeTableRecords([$byName])
            ->assertCanNotSeeTableRecords([$byMrn, $byEmail]);
        Livewire::test(ListMembers::class)
            ->searchTable('MRN-SEARCH-BETA')
            ->assertCanSeeTableRecords([$byMrn])
            ->assertCanNotSeeTableRecords([$byName, $byEmail]);
        Livewire::test(ListMembers::class)
            ->searchTable('search-email@example.test')
            ->assertCanSeeTableRecords([$byEmail])
            ->assertCanNotSeeTableRecords([$byName, $byMrn]);
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

    public function test_authorized_member_detail_does_not_serialize_audit_claim_session_digest_or_metadata_values(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage', 'member.audit.read']);
        [$member] = $this->member();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        app(AccountStateService::class)->suspend((string) $member->user_id, 'visible audit reason');
        $event = DB::table('audit_events')
            ->where('action', 'member.account-state')
            ->where('target_id', $member->user_id)
            ->firstOrFail();
        DB::table('audit_events')->where('event_id', $event->event_id)->update([
            'metadata' => json_encode(['opaque_marker' => 'audit-forbidden-metadata-marker'], JSON_THROW_ON_ERROR),
        ]);

        $component = Livewire::test(ViewMember::class, ['record' => $member->id])
            ->assertSee('visible audit reason');

        foreach (array_filter([
            'administrator',
            'member.account.manage',
            $event->session_id,
            $event->previous_state_digest,
            $event->new_state_digest,
            'audit-forbidden-metadata-marker',
        ]) as $forbiddenValue) {
            $component->assertDontSee($forbiddenValue);
        }
    }

    public function test_suspend_action_rechecks_permission_after_mount(): void
    {
        [$member] = $this->member();
        $admin = $this->admin(['member.account.read', 'member.account.manage']);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        $component = Livewire::test(ListMembers::class)
            ->assertTableActionVisible('suspend', $member)
            ->mountTableAction('suspend', $member)
            ->setTableActionData(['reason' => 'revoked permission attempt'])
            ->assertSet('mountedActions.0.context.recordKey', $member->id);
        $this->assertTrue($component->instance()->getMountedAction()?->isVisible());

        DB::table('authorization_permission_assignments')
            ->where('user_id', $admin->id)
            ->where('permission', 'member.account.manage')
            ->update(['active' => false]);
        app()->forgetScopedInstances();

        $component->instance()->callMountedTableAction();
        $component->assertNotified();
        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'active']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'account_status' => 'active']);
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'member.account-state',
            'target_id' => $member->user_id,
            'outcome' => 'suspended',
        ]);
    }

    public function test_suspend_action_rechecks_server_linkage_after_mount(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage']);
        [$member] = $this->member();
        $originalUserId = (string) $member->user_id;

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        $component = Livewire::test(ListMembers::class)
            ->assertTableActionVisible('suspend', $member)
            ->mountTableAction('suspend', $member)
            ->setTableActionData(['reason' => 'self linkage attempt'])
            ->assertSet('mountedActions.0.context.recordKey', $member->id);

        DB::table('members')->where('id', $member->id)->update(['user_id' => $admin->id]);

        $component->instance()->callMountedTableAction();
        $component->assertNotified();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'account_status' => 'active']);
        $this->assertDatabaseHas('users', ['id' => $originalUserId, 'account_status' => 'active']);
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'member.account-state',
            'target_id' => $admin->id,
            'outcome' => 'suspended',
        ]);
    }

    public function test_suspend_action_rechecks_source_state_after_mount(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage']);
        [$member] = $this->member();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        $component = Livewire::test(ListMembers::class)
            ->assertTableActionVisible('suspend', $member)
            ->mountTableAction('suspend', $member)
            ->setTableActionData(['reason' => 'changed state attempt'])
            ->assertSet('mountedActions.0.context.recordKey', $member->id);

        DB::table('users')->where('id', $member->user_id)->update(['account_status' => 'pending_activation']);

        $component->instance()->callMountedTableAction();
        $component->assertNotified();
        $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'pending_activation']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'account_status' => 'active']);
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'member.account-state',
            'target_id' => $member->user_id,
            'outcome' => 'suspended',
        ]);
    }

    public function test_account_actions_reject_blank_whitespace_and_overlong_reasons(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage']);

        foreach (['', '   ', str_repeat('x', 1001)] as $reason) {
            [$member] = $this->member();
            $this->actingAs($admin);
            Filament::setCurrentPanel('admin');

            Livewire::test(ListMembers::class)
                ->callTableAction('suspend', $member, ['reason' => $reason])
                ->assertHasErrors(['mountedActions.0.data.reason']);

            $this->assertDatabaseHas('users', ['id' => $member->user_id, 'account_status' => 'active']);
            $this->assertDatabaseMissing('audit_events', [
                'action' => 'member.account-state',
                'target_id' => $member->user_id,
                'outcome' => 'suspended',
            ]);
        }
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

    public function test_member_resource_has_no_mutation_bulk_or_export_actions(): void
    {
        $admin = $this->admin(['member.account.read', 'member.account.manage']);
        [$member] = $this->member();

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(ListMembers::class)
            ->assertTableActionDoesNotExist('create', null, $member)
            ->assertTableActionDoesNotExist('edit', null, $member)
            ->assertTableActionDoesNotExist('delete', null, $member)
            ->assertTableActionDoesNotExist('replicate', null, $member)
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('export');
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
    private function member(?User $existingUser = null, array $attributes = []): array
    {
        $user = $existingUser ?? User::factory()->create(['email' => 'member-'.Str::random(8).'@example.test']);
        $nik = '900000000'.str_pad((string) User::query()->count(), 3, '0', STR_PAD_LEFT);
        $memberId = (string) Str::uuid();

        DB::table('members')->insert(array_merge([
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
        ], $attributes));

        return [Member::query()->findOrFail($memberId), $nik];
    }
}
