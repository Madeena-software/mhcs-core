<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp03OfferingService;
use App\Modules\Member\Application\Services\Mvp03ScheduleService;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\LocalImagingOrder;
use App\Modules\Member\Domain\Models\PointLedgerEntry;
use App\Modules\Member\Filament\Resources\Bookings\BookingResource;
use App\Modules\Member\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Modules\Member\Filament\Resources\ExaminationSites\ExaminationSiteReferenceResource;
use App\Modules\Member\Filament\Resources\ServiceOfferings\Pages\CreateServiceOffering;
use App\Modules\Member\Filament\Resources\ServiceOfferings\Pages\EditServiceOffering;
use App\Modules\Member\Filament\Resources\ServiceOfferings\ServiceOfferingResource;
use App\Modules\Member\Filament\Resources\ShiftSchedules\Pages\CreateShiftSchedule;
use App\Modules\Member\Filament\Resources\ShiftSchedules\Pages\EditShiftSchedule;
use App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
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

    public function test_filament_create_and_edit_pages_use_services_and_reauthorize_after_mount(): void
    {
        $admin = $this->admin(['member.catalogue.read', 'member.catalogue.manage', 'member.schedule.read', 'member.schedule.manage']);
        $now = now();
        $organizationId = (string) Str::uuid();
        $siteId = (string) Str::uuid();
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'org-'.$organizationId, 'name' => 'Organisasi Sintetis', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('examination_site_refs')->insert(['id' => $siteId, 'operator_site_id' => 'site-'.$siteId, 'operator_organization_ref_id' => $organizationId, 'code' => 'SITE-'.substr($siteId, 0, 8), 'display_name' => 'Lokasi Sintetis', 'timezone' => 'Asia/Jakarta', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateServiceOffering::class)
            ->fillForm(['code' => 'RAD-LIVEWIRE', 'name' => 'Radiografi Livewire', 'point_price' => '3.1250', 'includes_ai' => true, 'includes_doctor' => false, 'active' => true])
            ->call('create')
            ->assertHasNoErrors();
        $offering = DB::table('service_offerings')->where('code', 'RAD-LIVEWIRE')->firstOrFail();

        Livewire::test(EditServiceOffering::class, ['record' => $offering->id])
            ->fillForm(['code' => 'RAD-LIVEWIRE', 'name' => 'Radiografi Livewire Diubah', 'point_price' => '3.2500', 'includes_ai' => true, 'includes_doctor' => true, 'active' => true])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('service_offerings', ['id' => $offering->id, 'name' => 'Radiografi Livewire Diubah', 'point_price' => '3.2500']);

        Livewire::test(CreateShiftSchedule::class)
            ->fillForm(['examination_site_id' => $siteId, 'service_offering_id' => $offering->id, 'starts_at' => '2040-04-01T10:00:00+07:00', 'ends_at' => '2040-04-01T11:00:00+07:00', 'quota' => 5, 'status' => 'open'])
            ->call('create')
            ->assertHasNoErrors();
        $schedule = DB::table('shift_schedules')->where('service_offering_id', $offering->id)->firstOrFail();

        Livewire::test(EditShiftSchedule::class, ['record' => $schedule->id])
            ->fillForm(['examination_site_id' => $siteId, 'service_offering_id' => $offering->id, 'starts_at' => '2040-04-01T10:00:00+07:00', 'ends_at' => '2040-04-01T11:00:00+07:00', 'quota' => 6, 'status' => 'open'])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertDatabaseHas('shift_schedules', ['id' => $schedule->id, 'quota' => 6]);

        $mounted = Livewire::test(CreateServiceOffering::class);
        DB::table('authorization_permission_assignments')
            ->where('user_id', $admin->id)
            ->where('permission', 'member.catalogue.manage')
            ->update(['active' => false]);

        try {
            $mounted->fillForm(['code' => 'RAD-REVOKED', 'name' => 'Tidak boleh', 'point_price' => '1.0000'])->call('create');
            $this->fail('Revoked catalogue permission must fail at execution time.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseMissing('service_offerings', ['code' => 'RAD-REVOKED']);
    }

    public function test_booking_audit_is_exactly_scoped_and_does_not_serialize_unrelated_reason_or_metadata(): void
    {
        $admin = $this->admin(['member.booking.read', 'member.booking.audit.read']);
        $booking = $this->auditBooking();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        $this->audit('member.booking.confirmed', Booking::class, $booking['id'], null);
        $this->audit('member.point-charge', PointLedgerEntry::class, (string) Str::uuid(), null, ['booking_id' => $booking['id']]);
        $this->audit('member.imaging-order.create', LocalImagingOrder::class, (string) Str::uuid(), null, ['booking_id' => $booking['id']]);
        $this->audit('member.booking.failed', Booking::class, $booking['id'], 'capacity_full');
        $this->audit('member.account-state', Booking::class, $booking['id'], 'sensitive reason marker', ['opaque_marker' => 'sensitive metadata marker']);
        $this->audit('member.booking.confirmed', Booking::class, $booking['id'], 'sensitive reason marker');
        $this->audit('member.point-charge', Booking::class, $booking['id'], null, ['booking_id' => $booking['id']]);

        $authorized = Livewire::test(ViewBooking::class, ['record' => $booking['id']])
            ->assertSee('capacity_full')
            ->assertCountTableRecords(4);
        $authorized->assertDontSee('sensitive reason marker')->assertDontSee('sensitive metadata marker');

        $readOnly = $this->admin(['member.booking.read']);
        $this->actingAs($readOnly);
        $this->assertFalse(BookingResource::canReadAudit());
        Livewire::test(ViewBooking::class, ['record' => $booking['id']])
            ->assertCountTableRecords(0)
            ->assertDontSee('capacity_full')
            ->assertDontSee('sensitive reason marker');
    }

    /** @return array{id: string} */
    private function auditBooking(): array
    {
        $now = now();
        $memberUser = User::factory()->create(['email' => 'audit-member@example.test']);
        $memberId = (string) Str::uuid();
        $organizationId = (string) Str::uuid();
        $siteId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $rateId = (string) Str::uuid();
        $bookingId = (string) Str::uuid();
        DB::table('members')->insert(['id' => $memberId, 'user_id' => $memberUser->id, 'family_id' => null, 'medical_record_number' => 'MRN-AUDIT', 'identity_status' => 'verified', 'identity_document_type' => 'ktp', 'encrypted_nik' => 'protected', 'nik_lookup_digest' => hash('sha256', $memberId), 'name' => 'Audit Member', 'birth_date' => '1988-01-01', 'administrative_gender' => 'unspecified', 'registration_source' => 'administrator', 'phone' => null, 'current_address' => 'Address', 'emergency_contact_name' => 'Contact', 'emergency_contact_relationship' => 'Sibling', 'emergency_contact_phone' => '0800000000', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'org-'.$organizationId, 'name' => 'Organization', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('examination_site_refs')->insert(['id' => $siteId, 'operator_site_id' => 'site-'.$siteId, 'operator_organization_ref_id' => $organizationId, 'code' => 'SITE-AUDIT', 'display_name' => 'Audit Site', 'timezone' => 'Asia/Jakarta', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('service_offerings')->insert(['id' => $serviceId, 'code' => 'AUDIT-SERVICE', 'name' => 'Audit Service', 'includes_ai' => true, 'includes_doctor' => false, 'point_price' => '1.0000', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('point_exchange_rates')->insert(['id' => $rateId, 'rupiah_per_point' => 10000, 'status' => 'active', 'effective_at' => $now, 'configured_by_admin_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'examination_site_id' => $siteId, 'service_offering_id' => $serviceId, 'starts_at' => '2040-01-01 03:00:00', 'ends_at' => '2040-01-01 04:00:00', 'quota' => 5, 'status' => 'open', 'eligible_at' => null, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('bookings')->insert(['id' => $bookingId, 'member_id' => $memberId, 'shift_schedule_id' => $scheduleId, 'service_offering_id' => $serviceId, 'examination_site_id_snapshot' => $siteId, 'booking_type' => 'b2c', 'funding_source' => 'personal', 'status' => 'confirmed', 'service_code_snapshot' => 'AUDIT-SERVICE', 'point_cost_snapshot' => '1.0000', 'point_exchange_rate_id' => $rateId, 'includes_ai_snapshot' => true, 'includes_doctor_snapshot' => false, 'site_code_snapshot' => 'SITE-AUDIT', 'site_name_snapshot' => 'Audit Site', 'site_timezone_snapshot' => 'Asia/Jakarta', 'created_at' => $now, 'confirmed_at' => $now, 'updated_at' => $now]);

        return ['id' => $bookingId];
    }

    /** @param array<string, mixed> $metadata */
    private function audit(string $action, string $targetType, string $targetId, ?string $reason, array $metadata = []): void
    {
        $context = app(AuthenticatedContextProvider::class)->current();
        app(AuditStore::class)->append(AuditEvent::fromContext($context, $action, 'member', 'success', app(Clock::class)->now(), $targetType, $targetId, reason: $reason, metadata: $metadata));
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
