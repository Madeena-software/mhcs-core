<?php

declare(strict_types=1);

namespace Tests\Operator;

use App\Models\User;
use App\Shared\Security\ProtectedIdentifierService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait Mvp04Fixtures
{
    /** @return array<string, mixed> */
    protected function operatorFixture(bool $administrator = true): array
    {
        $now = now();
        $operator = User::factory()->create(['email' => 'operator-'.Str::lower(Str::random(8)).'@example.test']);
        $memberUser = User::factory()->create(['email' => 'member-'.Str::lower(Str::random(8)).'@example.test']);
        $memberId = (string) Str::uuid();
        $siteLocalId = (string) Str::uuid();
        $siteStableId = 'operator-site-'.Str::lower(Str::random(8));
        $organizationLocalId = (string) Str::uuid();
        $organizationStableId = 'operator-org-'.Str::lower(Str::random(8));
        $siteReferenceId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $scheduleId = (string) Str::uuid();
        $scheduleDisplayReference = 'JAD-'.Str::upper(Str::random(8));
        $bookingId = (string) Str::uuid();
        $profileId = (string) Str::uuid();
        $eligibleId = (string) Str::uuid();
        $siteStart = '2040-01-10 03:00:00';
        $siteEnd = '2040-01-10 04:00:00';
        $protected = app(ProtectedIdentifierService::class)->protect('900000000001');

        DB::table('members')->insert([
            'id' => $memberId,
            'user_id' => $memberUser->id,
            'family_id' => null,
            'medical_record_number' => 'MRN-'.substr($memberId, 0, 8),
            'identity_status' => 'verified',
            'identity_document_type' => 'ktp',
            'encrypted_nik' => $protected['encrypted_display'],
            'nik_lookup_digest' => $protected['lookup_digest'],
            'name' => 'Synthetic Arrival Member',
            'birth_date' => '1988-01-10',
            'administrative_gender' => 'unspecified',
            'registration_source' => 'administrator',
            'phone' => null,
            'current_address' => 'Synthetic address',
            'emergency_contact_name' => 'Synthetic contact',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '0800000000',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_organization_refs')->insert([
            'id' => $organizationLocalId,
            'operator_organization_id' => $organizationStableId,
            'name' => 'Synthetic Operator Organization',
            'source_version' => '1',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('examination_site_refs')->insert([
            'id' => $siteReferenceId,
            'operator_site_id' => $siteStableId,
            'operator_organization_ref_id' => $organizationLocalId,
            'code' => 'SITE-'.substr($siteLocalId, 0, 8),
            'display_name' => 'Synthetic Operator Site',
            'timezone' => 'Asia/Jakarta',
            'source_version' => '1',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_sites')->insert([
            'id' => $siteLocalId,
            'operator_site_id' => $siteStableId,
            'organization_id' => $organizationStableId,
            'organization_name' => 'Synthetic Operator Organization',
            'code' => 'SITE-'.substr($siteLocalId, 0, 8),
            'display_name' => 'Synthetic Operator Site',
            'address_line' => null,
            'timezone' => 'Asia/Jakarta',
            'active' => true,
            'source_version' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('service_offerings')->insert([
            'id' => $serviceId,
            'code' => 'RAD-'.substr($serviceId, 0, 8),
            'name' => 'Synthetic Radiography',
            'includes_ai' => true,
            'includes_doctor' => false,
            'point_price' => '2.5000',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('shift_schedules')->insert([
            'id' => $scheduleId,
            'display_reference' => $scheduleDisplayReference,
            'examination_site_id' => $siteReferenceId,
            'service_offering_id' => $serviceId,
            'starts_at' => $siteStart,
            'ends_at' => $siteEnd,
            'quota' => 5,
            'status' => 'open',
            'eligible_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rateId = (string) Str::uuid();
        DB::table('point_exchange_rates')->insert([
            'id' => $rateId,
            'rupiah_per_point' => 10000,
            'status' => 'active',
            'effective_at' => $now,
            'configured_by_admin_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('bookings')->insert([
            'id' => $bookingId,
            'member_id' => $memberId,
            'shift_schedule_id' => $scheduleId,
            'service_offering_id' => $serviceId,
            'examination_site_id_snapshot' => $siteReferenceId,
            'booking_type' => 'b2c',
            'funding_source' => 'personal',
            'status' => 'confirmed',
            'service_code_snapshot' => 'RAD-'.substr($serviceId, 0, 8),
            'point_cost_snapshot' => '2.5000',
            'point_exchange_rate_id' => $rateId,
            'includes_ai_snapshot' => true,
            'includes_doctor_snapshot' => false,
            'site_code_snapshot' => 'SITE-'.substr($siteLocalId, 0, 8),
            'site_name_snapshot' => 'Synthetic Operator Site',
            'site_timezone_snapshot' => 'Asia/Jakarta',
            'created_at' => $now,
            'confirmed_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('point_ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $memberId,
            'booking_id' => $bookingId,
            'funding_source' => 'personal',
            'entry_type' => 'charge',
            'point_delta' => '-2.5000',
            'source_reference' => 'test:arrival:'.$bookingId,
            'reverses_id' => null,
            'created_at' => $now,
        ]);
        DB::table('operator_profiles')->insert([
            'id' => $profileId,
            'user_id' => $operator->id,
            'display_name' => 'Synthetic Operator',
            'employee_code' => 'OPR-'.substr($profileId, 0, 8),
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $profileId,
            'operator_site_id' => $siteLocalId,
            'active' => true,
            'assigned_by_user_id' => $operator->id,
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_eligible_shifts')->insert([
            'id' => $eligibleId,
            'member_schedule_id' => $scheduleId,
            'operator_site_id' => $siteStableId,
            'schedule_starts_at' => $siteStart,
            'schedule_ends_at' => $siteEnd,
            'confirmed_count_at_eligibility' => 5,
            'quota' => 5,
            'event_version' => 1,
            'source_event_id' => 'test:shift-eligible:'.$scheduleId,
            'eligible_at' => $now,
            'sync_status' => 'eligible',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_shift_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_eligible_shift_id' => $eligibleId,
            'operator_profile_id' => $profileId,
            'assigned_by_user_id' => $operator->id,
            'status' => 'active',
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->grant($operator, $administrator);

        return compact('operator', 'memberUser', 'memberId', 'siteLocalId', 'siteStableId', 'organizationStableId', 'siteReferenceId', 'serviceId', 'scheduleId', 'scheduleDisplayReference', 'bookingId', 'profileId', 'eligibleId');
    }

    protected function grant(User $user, bool $administrator = true, array $extraPermissions = []): void
    {
        $roles = ['operator'];
        if ($administrator) {
            $roles[] = 'administrator';
        }
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
        $permissions = [
            'operator.portal.access',
            'operator.site.read',
            'operator.assignment.read',
            'operator.shift.read',
            'operator.attendance.read',
            'operator.arrival.record',
            'operator.audit.read',
            'operator.site.manage',
            'operator.assignment.manage',
            'operator.shift.manage',
            ...$extraPermissions,
        ];
        foreach (array_unique($permissions) as $permission) {
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

    /** @param array<string, mixed> $fixture */
    protected function secondOperatorFixture(array $fixture): array
    {
        $now = now();
        $operator = User::factory()->create(['email' => 'operator-second-'.Str::lower(Str::random(8)).'@example.test']);
        $profileId = (string) Str::uuid();

        DB::table('operator_profiles')->insert([
            'id' => $profileId,
            'user_id' => $operator->id,
            'display_name' => 'Synthetic Second Operator',
            'employee_code' => 'OPR-'.substr($profileId, 0, 8),
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $profileId,
            'operator_site_id' => $fixture['siteLocalId'],
            'active' => true,
            'assigned_by_user_id' => $operator->id,
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('operator_shift_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_eligible_shift_id' => $fixture['eligibleId'],
            'operator_profile_id' => $profileId,
            'assigned_by_user_id' => $operator->id,
            'status' => 'active',
            'assigned_at' => $now,
            'revoked_at' => null,
            'reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->grant($operator, false);

        return ['operator' => $operator, 'profileId' => $profileId];
    }
}
