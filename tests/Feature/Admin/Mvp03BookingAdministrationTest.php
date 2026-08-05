<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp03OfferingService;
use App\Modules\Member\Application\Services\Mvp03ScheduleService;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Filament\Resources\Bookings\BookingResource;
use App\Modules\Member\Filament\Resources\ExaminationSites\ExaminationSiteReferenceResource;
use App\Modules\Member\Filament\Resources\ServiceOfferings\ServiceOfferingResource;
use App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp03BookingAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_read_permissions_expose_bounded_resources_and_booking_is_read_only(): void
    {
        $admin = $this->admin(['member.catalogue.read', 'member.schedule.read', 'member.booking.read']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->get('/admin/service-offerings')->assertOk();
        $this->get('/admin/shift-schedules')->assertOk();
        $this->get('/admin/examination-sites/examination-site-references')->assertOk();
        $this->get('/admin/bookings')->assertOk();
        $this->assertFalse(ServiceOfferingResource::canCreate());
        $this->assertFalse(ShiftScheduleResource::canCreate());
        $this->assertFalse(ExaminationSiteReferenceResource::canCreate());
        $this->assertFalse(BookingResource::canCreate());
        $this->assertFalse(BookingResource::canEdit(new Booking));
        $this->assertFalse(BookingResource::canDelete(new Booking));
        $this->assertFalse(BookingResource::canReadAudit());
    }

    public function test_manage_permissions_use_application_services_and_audit_mutations(): void
    {
        $admin = $this->admin(['member.catalogue.read', 'member.catalogue.manage', 'member.schedule.read', 'member.schedule.manage', 'member.booking.read', 'member.booking.audit.read']);
        $now = now();
        $organizationId = (string) Str::uuid();
        $siteId = (string) Str::uuid();
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'org-'.$organizationId, 'name' => 'Organisasi Sintetis', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('examination_site_refs')->insert(['id' => $siteId, 'operator_site_id' => 'site-'.$siteId, 'operator_organization_ref_id' => $organizationId, 'code' => 'SITE-'.substr($siteId, 0, 8), 'display_name' => 'Lokasi Sintetis', 'timezone' => 'Asia/Jakarta', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $offering = app(Mvp03OfferingService::class)->create(['code' => 'RAD-ADMIN', 'name' => 'Radiografi Admin', 'point_price' => '3.1250', 'includes_ai' => true, 'includes_doctor' => false, 'active' => true]);
        $schedule = app(Mvp03ScheduleService::class)->create(['examination_site_id' => $siteId, 'service_offering_id' => $offering->getKey(), 'starts_at' => '2040-02-01T10:00:00+07:00', 'ends_at' => '2040-02-01T11:00:00+07:00', 'quota' => 5]);

        $this->assertDatabaseHas('service_offerings', ['id' => $offering->getKey(), 'point_price' => '3.1250']);
        $this->assertDatabaseHas('shift_schedules', ['id' => $schedule->getKey(), 'examination_site_id' => $siteId, 'quota' => 5]);
        $this->assertSame(2, DB::table('audit_events')->whereIn('action', ['member.service-offering.create', 'member.schedule.create'])->count());
        $this->assertTrue(ServiceOfferingResource::canCreate());
        $this->assertTrue(ShiftScheduleResource::canCreate());
        $this->assertTrue(BookingResource::canReadAudit());
    }

    /** @param list<string> $permissions */
    private function admin(array $permissions): User
    {
        $user = User::factory()->create(['email' => 'mvp03-admin-'.Str::random(8).'@example.test']);
        DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'administrator', 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (array_unique(['member.admin.access', ...$permissions]) as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission' => $permission, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $user->fresh();
    }
}
