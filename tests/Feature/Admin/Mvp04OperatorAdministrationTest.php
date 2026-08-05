<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Operator\Application\Services\OperatorSiteService;
use App\Modules\Operator\Domain\Models\OperatorArrival;
use App\Modules\Operator\Filament\Resources\OperatorArrivals\OperatorArrivalResource;
use App\Modules\Operator\Filament\Resources\OperatorProfiles\OperatorProfileResource;
use App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\OperatorShiftAssignmentResource;
use App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\OperatorSiteAssignmentResource;
use App\Modules\Operator\Filament\Resources\OperatorSites\OperatorSiteResource;
use App\Modules\Operator\Filament\Resources\OperatorSites\Pages\ListOperatorSites;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class Mvp04OperatorAdministrationTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_read_permissions_are_independent_and_operator_records_are_read_only(): void
    {
        $admin = $this->admin(['member.admin.access', 'operator.site.read', 'operator.profile.read', 'operator.assignment.read', 'operator.shift.read', 'operator.audit.read']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->get('/admin/operator-sites')->assertOk();
        $this->get('/admin/operator-profiles')->assertOk();
        $this->get('/admin/operator-site-assignments')->assertOk();
        $this->get('/admin/operator-eligible-shifts')->assertOk();
        $this->get('/admin/operator-arrivals')->assertOk();

        $this->assertFalse(OperatorSiteResource::canCreate());
        $this->assertFalse(OperatorProfileResource::canCreate());
        $this->assertFalse(OperatorSiteAssignmentResource::canCreate());
        $this->assertFalse(OperatorShiftAssignmentResource::canCreate());
        $this->assertFalse(OperatorArrivalResource::canCreate());
        $this->assertFalse(OperatorArrivalResource::canEdit(new OperatorArrival));
        $this->assertFalse(OperatorArrivalResource::canDelete(new OperatorArrival));
        Livewire::test(ListOperatorSites::class)->assertActionDoesNotExist('create');
    }

    public function test_manage_permissions_enable_only_the_bounded_operator_mutations(): void
    {
        $admin = $this->admin([
            'member.admin.access',
            'operator.site.read', 'operator.site.manage',
            'operator.profile.read', 'operator.profile.manage',
            'operator.assignment.read', 'operator.assignment.manage',
            'operator.shift.read', 'operator.shift.manage',
            'operator.audit.read',
        ]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->assertTrue(OperatorSiteResource::canCreate());
        $this->assertTrue(OperatorProfileResource::canCreate());
        $this->assertTrue(OperatorSiteAssignmentResource::canCreate());
        $this->assertTrue(OperatorShiftAssignmentResource::canCreate());
        $this->assertFalse(OperatorArrivalResource::canCreate());

        $site = app(OperatorSiteService::class)->create([
            'operator_site_id' => 'admin-resource-site',
            'organization_id' => 'admin-resource-org',
            'organization_name' => 'Admin resource organization',
            'code' => 'ADMIN-RESOURCE',
            'display_name' => 'Admin resource site',
            'timezone' => 'Asia/Jakarta',
            'source_version' => '1',
            'active' => true,
        ]);
        $this->assertDatabaseHas('operator_sites', ['id' => $site->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'operator.site.create', 'outcome' => 'success']);
        $this->assertDatabaseMissing('bookings', []);
    }

    private function admin(array $permissions): User
    {
        $admin = User::factory()->create(['email' => 'admin-'.Str::lower(Str::random(8)).'@example.test']);
        DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role' => 'administrator', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'permission' => $permission, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $admin;
    }
}
