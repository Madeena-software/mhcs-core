<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorShiftAssignment;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperatorShiftAssignmentService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    public function assign(string $eligibleShiftId, string $profileId): OperatorShiftAssignment
    {
        $context = $this->authorization->shiftManage();

        return DB::transaction(function () use ($eligibleShiftId, $profileId, $context): OperatorShiftAssignment {
            $eligible = OperatorEligibleShift::query()->whereKey($eligibleShiftId)->where('sync_status', 'eligible')->lockForUpdate()->first();
            $profile = OperatorProfile::query()->whereKey($profileId)->lockForUpdate()->first();
            if ($eligible === null || $profile === null || ! $profile->active) {
                throw new OperatorException('shift_assignment_invalid', 'The eligible schedule or Operator is unavailable.');
            }
            $site = OperatorSite::query()->where('operator_site_id', $eligible->operator_site_id)->where('active', true)->first();
            if ($site === null || ! DB::table('operator_site_assignments')->where('operator_profile_id', $profileId)->where('operator_site_id', $site->getKey())->where('active', true)->exists()) {
                throw new OperatorException('shift_assignment_site_mismatch', 'The Operator is not assigned to the eligible schedule site.');
            }
            $existing = OperatorShiftAssignment::query()
                ->where('operator_eligible_shift_id', $eligibleShiftId)
                ->where('operator_profile_id', $profileId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $id = (string) Str::uuid();
            $now = $this->clock->now();
            $assignment = OperatorShiftAssignment::query()->create([
                'id' => $id,
                'operator_eligible_shift_id' => $eligibleShiftId,
                'operator_profile_id' => $profileId,
                'assigned_by_user_id' => (string) $context->actorId,
                'status' => 'active',
                'assigned_at' => $now,
                'revoked_at' => null,
                'reason' => null,
            ]);
            $this->audit->append(AuditEvent::fromContext($context, 'operator.shift-assignment.create', 'operator', 'success', $now, OperatorShiftAssignment::class, $id, metadata: ['eligible_shift_id' => $eligibleShiftId, 'operator_profile_id' => $profileId, 'operator_site_id' => $eligible->operator_site_id]));
            $this->outbox->record(new VersionedDomainEvent(LocalId::fromString((string) Str::uuid()), 'operator.shift-assigned', 1, $now, ['eligible_shift_id' => $eligibleShiftId, 'operator_profile_id' => $profileId, 'operator_site_id' => $eligible->operator_site_id], LocalId::fromString($id), $context->operationId));

            return $assignment;
        });
    }

    public function revoke(OperatorShiftAssignment $assignment, string $reason): OperatorShiftAssignment
    {
        $context = $this->authorization->shiftManage();
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            throw new OperatorException('shift_assignment_reason_required', 'A reason is required to revoke a shift assignment.');
        }

        return DB::transaction(function () use ($assignment, $reason, $context): OperatorShiftAssignment {
            $record = OperatorShiftAssignment::query()->whereKey($assignment->getKey())->lockForUpdate()->first();
            if ($record === null) {
                throw new OperatorException('shift_assignment_unavailable', 'The shift assignment is unavailable.');
            }
            if ($record->status !== 'active') {
                return $record;
            }
            $now = $this->clock->now();
            $record->forceFill(['status' => 'revoked', 'revoked_at' => $now, 'reason' => $reason])->save();
            $this->audit->append(AuditEvent::fromContext($context, 'operator.shift-assignment.revoke', 'operator', 'success', $now, OperatorShiftAssignment::class, (string) $record->getKey(), reason: $reason, metadata: ['eligible_shift_id' => $record->operator_eligible_shift_id, 'operator_profile_id' => $record->operator_profile_id]));

            return $record->refresh();
        });
    }

    /** @return list<OperatorEligibleShift> */
    public function assignedToCurrentOperator(): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);

        return OperatorEligibleShift::query()
            ->join('operator_shift_assignments', 'operator_shift_assignments.operator_eligible_shift_id', '=', 'operator_eligible_shifts.id')
            ->where('operator_shift_assignments.operator_profile_id', $portal['profile']->getKey())
            ->where('operator_shift_assignments.status', 'active')
            ->where('operator_eligible_shifts.operator_site_id', $site->operator_site_id)
            ->where('operator_eligible_shifts.sync_status', 'eligible')
            ->select('operator_eligible_shifts.*')
            ->orderBy('operator_eligible_shifts.schedule_starts_at')
            ->get()
            ->all();
    }

    public function isAssigned(string $profileId, string $scheduleId, string $operatorSiteId): bool
    {
        return DB::table('operator_shift_assignments')
            ->join('operator_eligible_shifts', 'operator_eligible_shifts.id', '=', 'operator_shift_assignments.operator_eligible_shift_id')
            ->where('operator_shift_assignments.operator_profile_id', $profileId)
            ->where('operator_shift_assignments.status', 'active')
            ->where('operator_eligible_shifts.member_schedule_id', $scheduleId)
            ->where('operator_eligible_shifts.operator_site_id', $operatorSiteId)
            ->where('operator_eligible_shifts.sync_status', 'eligible')
            ->exists();
    }
}
