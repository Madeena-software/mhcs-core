<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminOperationsFixtures
{
    public static function create(?array $permissions = null): array
    {
        $admin = User::factory()->create(['email' => 'board-'.Str::lower(Str::random(8)).'@example.test']);
        DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'role' => 'administrator', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (array_unique(['member.admin.access', ...($permissions ?? ['member.schedule.read', 'member.booking.read', 'operator.audit.read', 'operator.site.read', 'operator.site.manage', 'member.schedule.manage', 'member.account.read', 'member.registration.manage'])]) as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $admin->id, 'permission' => $permission, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $siteId = (string) Str::uuid();
        $operatorSiteId = (string) Str::uuid();
        $organizationId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $date = '2040-02-01';
        DB::table('operator_sites')->insert(['id' => $operatorSiteId, 'operator_site_id' => 'stable-'.$siteId, 'organization_id' => 'synthetic-org-'.$siteId, 'organization_name' => 'Organisasi Sintetis', 'code' => 'SITE-'.substr($siteId, 0, 8), 'display_name' => 'Lokasi Sintetis', 'timezone' => 'Asia/Jakarta', 'active' => true, 'source_version' => '1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('operator_organization_refs')->insert(['id' => $organizationId, 'operator_organization_id' => 'synthetic-org-'.$siteId, 'name' => 'Organisasi Sintetis', 'active' => true, 'source_version' => '1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('examination_site_refs')->insert(['id' => $siteId, 'operator_site_id' => 'stable-'.$siteId, 'operator_organization_ref_id' => $organizationId, 'code' => 'SITE-'.substr($siteId, 0, 8), 'display_name' => 'Lokasi Sintetis', 'timezone' => 'Asia/Jakarta', 'active' => true, 'source_version' => '1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('service_offerings')->insert(['id' => $serviceId, 'code' => 'RAD-'.substr($siteId, 0, 8), 'name' => 'Radiografi Sintetis', 'point_price' => '1.0000', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shift_schedules')->insert(['id' => $scheduleId, 'display_reference' => DB::table('shift_schedules')->where('display_reference', 'JAD-BOARD001')->exists() ? 'JAD-'.Str::upper(Str::random(8)) : 'JAD-BOARD001', 'examination_site_id' => $siteId, 'service_offering_id' => $serviceId, 'starts_at' => '2040-02-01 03:00:00', 'ends_at' => '2040-02-01 04:00:00', 'quota' => 5, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        return compact('admin', 'siteId', 'operatorSiteId', 'serviceId', 'scheduleId', 'date') + ['email' => $admin->email];
    }

    public static function booking(array $f, string $status = 'confirmed'): string
    {
        $id = (string) Str::uuid();
        $member = (string) Str::uuid();
        $rate = (string) Str::uuid();
        $user = User::factory()->create();
        DB::table('members')->insert(['id' => $member, 'user_id' => $user->id, 'medical_record_number' => 'SYN-'.substr($id, 0, 8), 'identity_status' => 'verified', 'identity_document_type' => 'ktp', 'encrypted_nik' => 'synthetic-protected', 'nik_lookup_digest' => hash('sha256', $member), 'name' => 'Private Synthetic Identity', 'birth_date' => '1988-01-01', 'administrative_gender' => 'unspecified', 'registration_source' => 'administrator', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('point_exchange_rates')->insert(['id' => $rate, 'rupiah_per_point' => 10000, 'status' => 'active', 'effective_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bookings')->insert(['id' => $id, 'member_id' => $member, 'shift_schedule_id' => $f['scheduleId'], 'service_offering_id' => $f['serviceId'], 'examination_site_id_snapshot' => $f['siteId'], 'booking_type' => 'personal', 'funding_source' => 'personal', 'status' => $status, 'service_code_snapshot' => 'SYNTHETIC', 'point_cost_snapshot' => '1.0000', 'point_exchange_rate_id' => $rate, 'includes_ai_snapshot' => false, 'includes_doctor_snapshot' => false, 'site_code_snapshot' => 'SYN', 'site_name_snapshot' => 'Private Synthetic Site Snapshot', 'site_timezone_snapshot' => 'Asia/Jakarta', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public static function arrival(array $f, string $booking, ?string $site = null, string $status = 'recorded'): void
    {
        $profile = DB::table('operator_profiles')->where('user_id', $f['admin']->id)->value('id');
        if (! $profile) {
            $profile = (string) Str::uuid();
            DB::table('operator_profiles')->insert(['id' => $profile, 'user_id' => $f['admin']->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('operator_arrivals')->insert(['id' => (string) Str::uuid(), 'booking_id' => $booking, 'member_schedule_id' => $f['scheduleId'], 'operator_site_id' => $site ?? $f['operatorSiteId'], 'operator_profile_id' => $profile, 'occurrence_at' => now(), 'recorded_at' => now(), 'operation_id' => (string) Str::uuid(), 'source' => 'synthetic-test', 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }
}
