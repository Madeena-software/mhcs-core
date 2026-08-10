<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Member\Application\Services\Mvp04OperatorSiteReferenceService;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MvpOperatorSeeder extends Seeder
{
    private const USER_EMAIL = 'mvp-admin@example.test';

    private const OPERATOR_SITE_ID = 'synthetic-operator-site-mvp03';

    private const ORGANIZATION_ID = 'synthetic-operator-org-mvp03';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('MvpOperatorSeeder is limited to local and testing environments.');
        }

        $user = User::query()->where('email', self::USER_EMAIL)->first();
        if ($user === null || ! $user->canAuthenticate()) {
            throw new RuntimeException('Run MvpAdminSeeder before MvpOperatorSeeder.');
        }

        $siteId = $this->site();
        $profileId = $this->profile($user);
        $this->claims($user);
        $this->siteAssignment($user, $profileId, $siteId);
        [$scheduleId, $eligibleId] = $this->eligibleShift();
        $this->shiftAssignment($user, $profileId, $eligibleId);

        $this->command?->info('MVP-04 synthetic Operator foundation is ready.');
        $this->command?->info('Safe Operator site ID: '.$siteId);
        $this->command?->info('Safe eligible schedule ID: '.$scheduleId);
        $this->command?->info('Safe portal route: /operator');
    }

    private function site(): string
    {
        $site = DB::table('operator_sites')->where('operator_site_id', self::OPERATOR_SITE_ID)->first();
        if ($site !== null) {
            if ($site->organization_id !== self::ORGANIZATION_ID || $site->organization_name !== 'Synthetic Operator Organization' || $site->code !== 'SYN-MVP03' || $site->display_name !== 'Synthetic MVP-03 site' || $site->timezone !== 'Asia/Jakarta' || $site->source_version !== 'mvp04-v1' || ! $site->active) {
                throw new RuntimeException('The existing synthetic Operator site is inconsistent.');
            }

            app(Mvp04OperatorSiteReferenceService::class)->synchronize(
                self::ORGANIZATION_ID,
                'Synthetic Operator Organization',
                self::OPERATOR_SITE_ID,
                'SYN-MVP03',
                'Synthetic MVP-03 site',
                'Asia/Jakarta',
                true,
                'mvp04-v1',
            );

            return (string) $site->id;
        }

        $localId = (string) Str::uuid();
        $now = now();
        DB::transaction(function () use ($localId, $now): void {
            DB::table('operator_sites')->insert([
                'id' => $localId,
                'operator_site_id' => self::OPERATOR_SITE_ID,
                'organization_id' => self::ORGANIZATION_ID,
                'organization_name' => 'Synthetic Operator Organization',
                'code' => 'SYN-MVP03',
                'display_name' => 'Synthetic MVP-03 site',
                'address_line' => null,
                'timezone' => 'Asia/Jakarta',
                'active' => true,
                'source_version' => 'mvp04-v1',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
        app(Mvp04OperatorSiteReferenceService::class)->synchronize(
            self::ORGANIZATION_ID,
            'Synthetic Operator Organization',
            self::OPERATOR_SITE_ID,
            'SYN-MVP03',
            'Synthetic MVP-03 site',
            'Asia/Jakarta',
            true,
            'mvp04-v1',
        );

        return $localId;
    }

    private function profile(User $user): string
    {
        $profile = DB::table('operator_profiles')->where('user_id', $user->getKey())->first();
        if ($profile !== null) {
            if (! $profile->active || $profile->display_name !== 'Synthetic Operator' || $profile->employee_code !== 'SYN-OPR-01') {
                throw new RuntimeException('The existing synthetic Operator profile is inactive.');
            }

            return (string) $profile->id;
        }

        $id = (string) Str::uuid();
        DB::table('operator_profiles')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'display_name' => 'Synthetic Operator',
            'employee_code' => 'SYN-OPR-01',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function claims(User $user): void
    {
        $now = now();
        $role = DB::table('authorization_role_assignments')->where('user_id', $user->getKey())->where('role', OperatorAuthorization::ROLE)->first();
        if ($role !== null && ! $role->active) {
            throw new RuntimeException('The existing synthetic Operator role assignment is inconsistent.');
        }
        if ($role === null) {
            DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->getKey(), 'role' => OperatorAuthorization::ROLE, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([
            OperatorAuthorization::PORTAL_ACCESS,
            OperatorAuthorization::SITE_READ,
            OperatorAuthorization::SITE_MANAGE,
            OperatorAuthorization::ASSIGNMENT_READ,
            OperatorAuthorization::ASSIGNMENT_MANAGE,
            OperatorAuthorization::SHIFT_READ,
            OperatorAuthorization::SHIFT_MANAGE,
            OperatorAuthorization::ATTENDANCE_READ,
            OperatorAuthorization::ARRIVAL_RECORD,
            OperatorAuthorization::IDENTITY_VERIFY,
            OperatorAuthorization::AUDIT_READ,
            OperatorAuthorization::PROTOCOL_READ,
            OperatorAuthorization::PROTOCOL_MANAGE,
        ] as $permission) {
            $assignment = DB::table('authorization_permission_assignments')->where('user_id', $user->getKey())->where('permission', $permission)->first();
            if ($assignment !== null && ! $assignment->active) {
                throw new RuntimeException('The existing synthetic Operator permission assignment is inconsistent.');
            }
            if ($assignment === null) {
                DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->getKey(), 'permission' => $permission, 'assigned_by_user_id' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    private function siteAssignment(User $user, string $profileId, string $siteId): void
    {
        $existing = DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('operator_site_id', $siteId)->first();
        if ($existing !== null && ! $existing->active) {
            throw new RuntimeException('The existing synthetic site assignment is inconsistent.');
        }
        if ($existing !== null) {
            return;
        }
        DB::table('operator_site_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_profile_id' => $profileId,
            'operator_site_id' => $siteId,
            'active' => true,
            'assigned_by_user_id' => $user->getKey(),
            'assigned_at' => now(),
            'revoked_at' => null,
            'reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function eligibleShift(): array
    {
        $schedule = DB::table('shift_schedules')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->where('examination_site_refs.operator_site_id', self::OPERATOR_SITE_ID)
            ->orderBy('shift_schedules.starts_at')
            ->select('shift_schedules.*')
            ->first();
        if ($schedule === null) {
            throw new RuntimeException('Run MvpBookingSeeder before MvpOperatorSeeder.');
        }
        $existing = DB::table('operator_eligible_shifts')->where('member_schedule_id', $schedule->id)->first();
        if ($existing !== null) {
            if ($existing->operator_site_id !== self::OPERATOR_SITE_ID || $existing->sync_status !== 'eligible' || (int) $existing->quota !== (int) $schedule->quota || $existing->source_event_id !== 'mvp04:synthetic:shift-eligible:'.$schedule->id) {
                throw new RuntimeException('The existing synthetic eligible shift is inconsistent.');
            }

            return [(string) $schedule->id, (string) $existing->id];
        }
        $eligibleId = (string) Str::uuid();
        DB::table('operator_eligible_shifts')->insert([
            'id' => $eligibleId,
            'member_schedule_id' => $schedule->id,
            'operator_site_id' => self::OPERATOR_SITE_ID,
            'schedule_starts_at' => $schedule->starts_at,
            'schedule_ends_at' => $schedule->ends_at,
            'confirmed_count_at_eligibility' => 5,
            'quota' => (int) $schedule->quota,
            'event_version' => 1,
            'source_event_id' => 'mvp04:synthetic:shift-eligible:'.$schedule->id,
            'eligible_at' => $schedule->eligible_at ?? now(),
            'sync_status' => 'eligible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [(string) $schedule->id, $eligibleId];
    }

    private function shiftAssignment(User $user, string $profileId, string $eligibleId): void
    {
        $existing = DB::table('operator_shift_assignments')->where('operator_eligible_shift_id', $eligibleId)->where('operator_profile_id', $profileId)->first();
        if ($existing !== null && $existing->status !== 'active') {
            throw new RuntimeException('The existing synthetic shift assignment is inconsistent.');
        }
        if ($existing !== null) {
            return;
        }
        DB::table('operator_shift_assignments')->insert([
            'id' => (string) Str::uuid(),
            'operator_eligible_shift_id' => $eligibleId,
            'operator_profile_id' => $profileId,
            'assigned_by_user_id' => $user->getKey(),
            'status' => 'active',
            'assigned_at' => now(),
            'revoked_at' => null,
            'reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
