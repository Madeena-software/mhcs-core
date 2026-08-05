<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\Models\OperatorSiteAssignment;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperatorSiteAssignmentService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function assign(string $profileId, string $siteId): OperatorSiteAssignment
    {
        $context = $this->authorization->assignmentManage();

        return DB::transaction(function () use ($profileId, $siteId, $context): OperatorSiteAssignment {
            $profile = OperatorProfile::query()->whereKey($profileId)->lockForUpdate()->first();
            $site = OperatorSite::query()->whereKey($siteId)->lockForUpdate()->first();
            if ($profile === null || ! $profile->active || $site === null || ! $site->active) {
                throw new OperatorException('assignment_invalid', 'Only active Operator profiles and sites can be assigned.');
            }
            $existing = OperatorSiteAssignment::query()
                ->where('operator_profile_id', $profileId)
                ->where('operator_site_id', $siteId)
                ->where('active', true)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $id = (string) Str::uuid();
            $now = $this->clock->now();
            $assignment = OperatorSiteAssignment::query()->create([
                'id' => $id,
                'operator_profile_id' => $profileId,
                'operator_site_id' => $siteId,
                'active' => true,
                'assigned_by_user_id' => (string) $context->actorId,
                'assigned_at' => $now,
                'revoked_at' => null,
                'reason' => null,
            ]);
            $this->audit->append(AuditEvent::fromContext($context, 'operator.site-assignment.create', 'operator', 'success', $now, OperatorSiteAssignment::class, $id, metadata: ['operator_profile_id' => $profileId, 'operator_site_id' => $site->operator_site_id]));

            return $assignment;
        });
    }

    public function revoke(OperatorSiteAssignment $assignment, string $reason): OperatorSiteAssignment
    {
        $context = $this->authorization->assignmentManage();
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            throw new OperatorException('assignment_reason_required', 'A reason is required to revoke an assignment.');
        }

        return DB::transaction(function () use ($assignment, $reason, $context): OperatorSiteAssignment {
            $record = OperatorSiteAssignment::query()->whereKey($assignment->getKey())->lockForUpdate()->first();
            if ($record === null) {
                throw new OperatorException('assignment_unavailable', 'The site assignment is unavailable.');
            }
            if (! $record->active) {
                return $record;
            }
            $now = $this->clock->now();
            $record->forceFill(['active' => false, 'revoked_at' => $now, 'reason' => $reason])->save();
            $this->audit->append(AuditEvent::fromContext($context, 'operator.site-assignment.revoke', 'operator', 'success', $now, OperatorSiteAssignment::class, (string) $record->getKey(), reason: $reason, metadata: ['operator_profile_id' => $record->operator_profile_id, 'operator_site_id' => $record->operator_site_id]));

            return $record->refresh();
        });
    }
}
