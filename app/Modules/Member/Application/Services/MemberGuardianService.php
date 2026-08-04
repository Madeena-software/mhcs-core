<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Data\GuardianAuthorizationResult;
use App\Modules\Member\Domain\Enums\GuardianStatus;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class MemberGuardianService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function add(string $childMemberId, string $guardianMemberId): string
    {
        $context = $this->authorization->administrator('member.guardian.manage');

        return DB::transaction(function () use ($childMemberId, $guardianMemberId, $context): string {
            if ($childMemberId === $guardianMemberId) {
                throw new MemberIdentityException('A Member cannot be their own guardian.');
            }

            $child = DB::table('members')->where('id', $childMemberId)->first();
            if ($child === null || $this->isAdult($child->birth_date)) {
                throw new MemberIdentityException('Guardian access is only available for a dependent Member.');
            }

            $guardian = DB::table('members')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->where('members.id', $guardianMemberId)
                ->where('members.identity_status', IdentityStatus::Verified->value)
                ->where('users.account_status', 'active')
                ->where('users.login_enabled', true)
                ->where('users.must_change_password', false)
                ->first();

            if ($guardian === null) {
                throw new MemberIdentityException('The guardian must be an active verified Member account.');
            }

            if (DB::table('member_guardians')
                ->where('child_member_id', $childMemberId)
                ->where('guardian_member_id', $guardianMemberId)
                ->where('status', GuardianStatus::Verified->value)
                ->whereNull('ends_at')
                ->exists()) {
                throw new MemberIdentityException('The guardian relation is already active.');
            }

            $now = $this->clock->now();
            $id = (string) Str::uuid();
            DB::table('member_guardians')->insert([
                'id' => $id,
                'child_member_id' => $childMemberId,
                'guardian_member_id' => $guardianMemberId,
                'status' => GuardianStatus::Verified->value,
                'verified_by_user_id' => (string) $context->actorId,
                'starts_at' => $now,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.guardian.created',
                source: 'member',
                outcome: 'verified',
                occurredAt: $now,
                targetType: Member::class,
                targetId: $childMemberId,
                metadata: ['relation_id' => $id],
            ));

            return $id;
        });
    }

    public function end(string $relationId): void
    {
        $context = $this->authorization->administrator('member.guardian.manage');
        DB::transaction(function () use ($relationId, $context): void {
            $relation = DB::table('member_guardians')->where('id', $relationId)->lockForUpdate()->first();
            if ($relation === null) {
                throw new MemberIdentityException('The guardian relation was not found.');
            }

            if ($relation->status === GuardianStatus::Ended->value || $relation->ends_at !== null) {
                return;
            }

            $now = $this->clock->now();
            DB::table('member_guardians')->where('id', $relationId)->update([
                'status' => GuardianStatus::Ended->value,
                'ends_at' => $now,
                'updated_at' => $now,
            ]);
            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.guardian.ended',
                source: 'member',
                outcome: 'ended',
                occurredAt: $now,
                targetType: Member::class,
                targetId: $relation->child_member_id,
                metadata: ['relation_id' => $relationId],
            ));
        });
    }

    public function authorizeDependent(string $childMemberId, string $purpose = 'member.dependent-access'): GuardianAuthorizationResult
    {
        $context = $this->authorization->context($purpose);
        $now = $this->clock->now();
        $relation = DB::table('member_guardians')
            ->join('members as guardians', 'guardians.id', '=', 'member_guardians.guardian_member_id')
            ->join('users', 'users.id', '=', 'guardians.user_id')
            ->where('member_guardians.child_member_id', $childMemberId)
            ->where('member_guardians.status', GuardianStatus::Verified->value)
            ->whereNull('member_guardians.ends_at')
            ->where('guardians.identity_status', IdentityStatus::Verified->value)
            ->where('users.id', (string) $context->actorId)
            ->where('users.account_status', 'active')
            ->where('users.login_enabled', true)
            ->where('users.must_change_password', false)
            ->select('member_guardians.guardian_member_id')
            ->first();

        if ($relation === null) {
            throw new MemberIdentityException('The dependent action is not authorized.');
        }

        return new GuardianAuthorizationResult(
            actingGuardianMemberId: $relation->guardian_member_id,
            dependentMemberId: $childMemberId,
            purpose: $purpose,
            authorizedAt: $now,
        );
    }

    private function isAdult(string $birthDate): bool
    {
        return new \DateTimeImmutable($birthDate) <= $this->clock->now()->modify('-17 years')->setTime(0, 0);
    }
}
