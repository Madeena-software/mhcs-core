<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Models\User;
use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Authorization\AuthorizationGuard;
use App\Shared\Time\Clock;
use App\Shared\Validation\NonclinicalValidationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class NonclinicalValidationOperatorContextProvisioningService
{
    private const PURPOSE = 'production.validation-context.operator-context-provision';

    /** @var list<string> */
    private const PERMISSIONS = [
        OperatorAuthorization::ARRIVAL_RECORD,
        OperatorAuthorization::ATTENDANCE_READ,
        OperatorAuthorization::IDENTITY_VERIFY,
        OperatorAuthorization::PORTAL_ACCESS,
    ];

    public function __construct(
        private AuthorizationGuard $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @return array{profile_state: string, site_assignment: string, shift_assignment: string} */
    public function provision(string $operatorUserId, string $scheduleId, string $operatorSiteId, string $eligibleShiftId): array
    {
        $context = $this->authorization->current(self::PURPOSE);
        if (! in_array('system', $context->roles, true) || $context->actorId === null) {
            throw new RuntimeException('Validation Operator context requires trusted system authorization.');
        }

        return DB::transaction(function () use ($context, $operatorUserId, $scheduleId, $operatorSiteId, $eligibleShiftId): array {
            $this->assertOperator($operatorUserId);
            $this->assertCandidate($scheduleId, $operatorSiteId, $eligibleShiftId);
            $profile = $this->profile($operatorUserId, $context);
            $site = DB::table('operator_sites')->where('operator_site_id', $operatorSiteId)->where('active', true)->first();
            if ($site === null) {
                throw new RuntimeException('The validation Operator site is unavailable.');
            }
            $this->siteAssignment((string) $profile->getKey(), (string) $site->id, $context);
            $this->shiftAssignment((string) $profile->getKey(), $eligibleShiftId, $context);

            return [
                'profile_state' => $profile->wasRecentlyCreated ? 'CREATED' : 'EXISTING_VALID',
                'site_assignment' => 'PASS',
                'shift_assignment' => 'PASS',
            ];
        });
    }

    private function assertOperator(string $userId): void
    {
        $user = User::query()->whereKey($userId)->first();
        if ($user === null || ! $user->canAuthenticate()) {
            throw new RuntimeException('The validation Operator account is inconsistent.');
        }
        $roles = DB::table('authorization_role_assignments')->where('user_id', $userId)->where('active', true)->orderBy('role')->pluck('role')->all();
        $permissions = DB::table('authorization_permission_assignments')->where('user_id', $userId)->where('active', true)->orderBy('permission')->pluck('permission')->all();
        $expected = self::PERMISSIONS;
        sort($expected);
        if ($roles !== [OperatorAuthorization::ROLE] || $permissions !== $expected) {
            throw new RuntimeException('The validation Operator grants are inconsistent.');
        }
    }

    private function assertCandidate(string $scheduleId, string $operatorSiteId, string $eligibleShiftId): void
    {
        $schedule = DB::table('shift_schedules')->where('id', $scheduleId)->first();
        $eligible = OperatorEligibleShift::query()->whereKey($eligibleShiftId)->where('sync_status', 'eligible')->first();
        if ($schedule === null || $eligible === null
            || (string) $eligible->member_schedule_id !== $scheduleId
            || (string) $eligible->operator_site_id !== $operatorSiteId
            || (string) $eligible->schedule_starts_at !== (string) $schedule->starts_at
            || (string) $eligible->schedule_ends_at !== (string) $schedule->ends_at) {
            throw new RuntimeException('The validation Operator schedule projection is inconsistent.');
        }
    }

    private function profile(string $userId, $context): OperatorProfile
    {
        $profiles = OperatorProfile::query()->where('user_id', $userId)->lockForUpdate()->get();
        if ($profiles->count() > 1) {
            throw new RuntimeException('The validation Operator profile state is duplicated.');
        }
        $profile = $profiles->first();
        if ($profile !== null) {
            if (! $this->owned('operator-profile.provisioned', (string) $profile->getKey()) || ! $profile->active) {
                throw new RuntimeException('The validation Operator profile is not owned by this context.');
            }

            return $profile;
        }
        $now = $this->clock->now();
        $profile = OperatorProfile::query()->create([
            'id' => (string) Str::uuid(), 'user_id' => $userId,
            'display_name' => 'Nonclinical validation operator',
            'employee_code' => 'VALIDATION-REAL-E2E-V1', 'active' => true,
        ]);
        $this->audit->append($this->event($context, 'operator-profile.provisioned', OperatorProfile::class, (string) $profile->getKey(), $now));

        return $profile;
    }

    private function siteAssignment(string $profileId, string $siteId, $context): void
    {
        $all = DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('active', true)->lockForUpdate()->get();
        if ($all->count() > 1 || ($all->isNotEmpty() && (string) $all->first()->operator_site_id !== $siteId)) {
            throw new RuntimeException('The validation Operator site assignments are inconsistent.');
        }
        $rows = DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('operator_site_id', $siteId)->where('active', true)->lockForUpdate()->get();
        if ($rows->count() > 1 || ($rows->isNotEmpty() && (! $this->owned('site-assignment.provisioned', (string) $rows->first()->id) || $rows->first()->assigned_by_user_id !== null))) {
            throw new RuntimeException('The validation Operator site assignment is inconsistent.');
        }
        if ($rows->isNotEmpty()) {
            return;
        }
        $id = (string) Str::uuid();
        $now = $this->clock->now();
        DB::table('operator_site_assignments')->insert(['id' => $id, 'operator_profile_id' => $profileId, 'operator_site_id' => $siteId, 'active' => true, 'assigned_by_user_id' => null, 'assigned_at' => $now, 'revoked_at' => null, 'reason' => null, 'created_at' => $now, 'updated_at' => $now]);
        $this->audit->append($this->event($context, 'site-assignment.provisioned', 'operator-site-assignment', $id, $now));
    }

    private function shiftAssignment(string $profileId, string $eligibleId, $context): void
    {
        $all = DB::table('operator_shift_assignments')->where('operator_profile_id', $profileId)->where('status', 'active')->lockForUpdate()->get();
        if ($all->count() > 1 || ($all->isNotEmpty() && (string) $all->first()->operator_eligible_shift_id !== $eligibleId)) {
            throw new RuntimeException('The validation Operator shift assignments are inconsistent.');
        }
        $rows = DB::table('operator_shift_assignments')->where('operator_profile_id', $profileId)->where('operator_eligible_shift_id', $eligibleId)->where('status', 'active')->lockForUpdate()->get();
        if ($rows->count() > 1 || ($rows->isNotEmpty() && (! $this->owned('shift-assignment.provisioned', (string) $rows->first()->id) || $rows->first()->assigned_by_user_id !== null))) {
            throw new RuntimeException('The validation Operator shift assignment is inconsistent.');
        }
        if ($rows->isNotEmpty()) {
            return;
        }
        $id = (string) Str::uuid();
        $now = $this->clock->now();
        DB::table('operator_shift_assignments')->insert(['id' => $id, 'operator_eligible_shift_id' => $eligibleId, 'operator_profile_id' => $profileId, 'assigned_by_user_id' => null, 'status' => 'active', 'assigned_at' => $now, 'revoked_at' => null, 'reason' => null, 'created_at' => $now, 'updated_at' => $now]);
        $this->audit->append($this->event($context, 'shift-assignment.provisioned', 'operator-shift-assignment', $id, $now));
    }

    private function owned(string $suffix, string $id): bool
    {
        $events = DB::table('audit_events')->where('action', 'production.validation-context.'.$suffix)->where('target_id', $id)->where('outcome', 'success')->get();
        if ($events->count() !== 1) {
            return false;
        }

        return json_decode((string) $events->first()->metadata, true) === [
            'validation_context' => NonclinicalValidationContext::KEY,
            'nonclinical' => true,
            'provisioning_actor' => 'system',
            'human_assignment_performed' => false,
        ];
    }

    private function event($context, string $suffix, string $type, string $id, \DateTimeImmutable $now): AuditEvent
    {
        return AuditEvent::fromContext($context, 'production.validation-context.'.$suffix, 'operator', 'success', $now, $type, $id, metadata: ['validation_context' => NonclinicalValidationContext::KEY, 'nonclinical' => true, 'provisioning_actor' => 'system', 'human_assignment_performed' => false]);
    }
}
