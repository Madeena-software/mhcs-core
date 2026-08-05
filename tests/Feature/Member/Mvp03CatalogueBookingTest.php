<?php

declare(strict_types=1);

namespace Tests\Feature\Member;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Mvp03CatalogueBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_browse_only_active_catalogue_and_book_owner_scoped_status(): void
    {
        $fixture = $this->fixture('catalogue@example.test', complete: true, credit: '20.0000');
        $this->actingAs($fixture['user']);

        $this->get('/member/services')
            ->assertOk()
            ->assertSee('Radiografi Dada')
            ->assertDontSee('Layanan tidak aktif')
            ->assertDontSee('nik_lookup_digest')
            ->assertDontSee('authorization_permission_assignments');
        $this->get('/member/services/'.$fixture['service_id'])->assertOk()->assertSee('Lokasi Sintetis');
        $this->get('/member/schedules')->assertOk()->assertSee('Lokasi Sintetis');

        $response = $this->post('/member/bookings', [
            'schedule_id' => $fixture['schedule_id'],
            'point_cost' => '2.5000',
            'confirmation' => '1',
            'idempotency_key' => 'mvp03-route-request',
            'member_id' => (string) Str::uuid(),
            'site_id' => (string) Str::uuid(),
            'funding_source' => 'business',
            'status' => 'cancelled',
        ]);

        $bookingId = (string) DB::table('bookings')->value('id');
        $response->assertRedirect(route('member.bookings.show', $bookingId));
        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'member_id' => $fixture['member_id'],
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
        ]);
        $this->get('/member/bookings')->assertOk()->assertSee('Sesi Foto Radiografi Terjadwal');
        $this->get('/member/bookings/'.$bookingId)->assertOk()->assertSee('Sesi Foto Radiografi Terjadwal');

        $other = $this->fixture('other@example.test', complete: true, credit: '1.0000');
        $this->actingAs($other['user'])->get('/member/bookings/'.$bookingId)->assertNotFound();
    }

    public function test_incomplete_and_suspended_members_cannot_use_booking_routes(): void
    {
        $incomplete = $this->fixture('incomplete@example.test', complete: false, credit: '10.0000');
        $this->actingAs($incomplete['user'])->get('/member/services')->assertRedirect(route('member.profile'));

        $suspended = $this->fixture('suspended@example.test', complete: true, credit: '10.0000');
        DB::table('users')->where('id', $suspended['user']->id)->update(['account_status' => 'suspended']);
        $suspended['user']->refresh();
        $this->actingAs($suspended['user'])->get('/member/services')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_site_offering_and_closed_schedule_are_not_bookable(): void
    {
        $fixture = $this->fixture('availability@example.test', complete: true, credit: '10.0000');
        DB::table('service_offerings')->where('id', $fixture['inactive_service_id'])->update(['active' => false]);
        DB::table('examination_site_refs')->where('id', $fixture['inactive_site_id'])->update(['active' => false]);
        DB::table('shift_schedules')->where('id', $fixture['closed_schedule_id'])->update(['status' => 'closed']);

        $this->actingAs($fixture['user'])->get('/member/services')->assertOk()->assertDontSee('Layanan tidak aktif');
        $this->get('/member/schedules')->assertOk()->assertDontSee('Lokasi tidak aktif')->assertDontSee('Jadwal tertutup');
        $this->post('/member/bookings', [
            'schedule_id' => $fixture['closed_schedule_id'],
            'point_cost' => '2.5000',
            'confirmation' => '1',
        ])->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @return array<string, mixed> */
    private function fixture(string $email, bool $complete, string $credit): array
    {
        $user = User::factory()->create(['email' => $email]);
        $memberId = (string) Str::uuid();
        $now = now();
        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $user->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.Str::upper(Str::random(8)),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => 'protected-test-value',
            'nik_lookup_digest' => hash('sha256', $memberId),
            'name' => 'Synthetic Member',
            'birth_date' => '1988-01-01',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => $complete ? 'Alamat sintetis' : null,
            'emergency_contact_name' => $complete ? 'Kontak sintetis' : null,
            'emergency_contact_relationship' => $complete ? 'Saudara' : null,
            'emergency_contact_phone' => $complete ? '0800000000' : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $organizationId = (string) Str::uuid();
        $suffix = Str::upper(substr(hash('sha256', $email), 0, 8));
        $siteId = (string) Str::uuid();
        $inactiveSiteId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $inactiveServiceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $closedScheduleId = (string) Str::uuid();
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'org-'.$organizationId, 'name' => 'Organisasi Sintetis', 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([[$siteId, 'Lokasi Sintetis', true, 'SITE-'.$suffix], [$inactiveSiteId, 'Lokasi tidak aktif', false, 'OFFSITE-'.$suffix]] as [$id, $name, $active, $code]) {
            DB::table('examination_site_refs')->insert(['id' => $id, 'operator_site_id' => 'site-'.$id, 'operator_organization_ref_id' => $organizationId, 'code' => $code, 'display_name' => $name, 'timezone' => 'Asia/Jakarta', 'active' => $active, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([[$serviceId, 'Radiografi Dada', 'RAD-'.$suffix, true], [$inactiveServiceId, 'Layanan tidak aktif', 'OFF-'.$suffix, false]] as [$id, $name, $code, $active]) {
            DB::table('service_offerings')->insert(['id' => $id, 'code' => $code, 'name' => $name, 'includes_ai' => true, 'includes_doctor' => true, 'point_price' => '2.5000', 'active' => $active, 'created_at' => $now, 'updated_at' => $now]);
        }
        $rateId = (string) Str::uuid();
        DB::table('point_exchange_rates')->insert(['id' => $rateId, 'rupiah_per_point' => 10000, 'status' => 'active', 'effective_at' => $now, 'configured_by_admin_id' => null, 'created_at' => $now, 'updated_at' => $now]);
        $start = now()->addDays(10)->setTime(3, 0, 0);
        $end = $start->copy()->addHour();
        foreach ([[$scheduleId, $siteId, $serviceId, 'Jadwal Sintetis', 'open'], [$closedScheduleId, $siteId, $serviceId, 'Jadwal tertutup', 'open']] as [$id, $scheduledSiteId, $scheduledServiceId, $label, $status]) {
            DB::table('shift_schedules')->insert(['id' => $id, 'examination_site_id' => $scheduledSiteId, 'service_offering_id' => $scheduledServiceId, 'starts_at' => $start, 'ends_at' => $end, 'quota' => 5, 'status' => $status, 'eligible_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            if ($label === 'Jadwal tertutup') {
                DB::table('shift_schedules')->where('id', $id)->update(['status' => 'closed']);
            }
            $start = $end->copy()->addHour();
            $end = $start->copy()->addHour();
        }
        DB::table('point_ledger_entries')->insert(['id' => (string) Str::uuid(), 'member_id' => $memberId, 'booking_id' => null, 'funding_source' => 'personal', 'entry_type' => 'credit', 'point_delta' => $credit, 'source_reference' => 'test:route-credit:'.$email, 'reverses_id' => null, 'created_at' => $now]);

        return [
            'user' => $user,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'inactive_site_id' => $inactiveSiteId,
            'service_id' => $serviceId,
            'inactive_service_id' => $inactiveServiceId,
            'schedule_id' => $scheduleId,
            'closed_schedule_id' => $closedScheduleId,
        ];
    }
}
