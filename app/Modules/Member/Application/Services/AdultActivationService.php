<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Data\AdultActivationResult;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AdultActivationService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function activate(string $memberId, string $operationId): AdultActivationResult
    {
        $context = $this->authorization->accountState();
        if (trim($memberId) === '' || trim($operationId) === '') {
            throw new MemberIdentityException('An adult-activation target and operation identity are required.');
        }

        return DB::transaction(function () use ($memberId, $operationId, $context): AdultActivationResult {
            $member = DB::table('members')->where('id', $memberId)->lockForUpdate()->first();
            if ($member === null) {
                throw new MemberIdentityException('The Member identity was not found.');
            }

            $user = User::query()->whereKey($member->user_id)->lockForUpdate()->first();
            if ($user === null) {
                throw new MemberIdentityException('The Member authentication record was not found.');
            }

            $payloadHash = hash('sha256', $memberId.'|'.$operationId);
            $operation = DB::table('member_operations')
                ->where('operation_type', 'adult-activation')
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();

            if ($operation !== null) {
                if (! hash_equals($operation->payload_hash, $payloadHash)) {
                    throw new MemberIdentityException('The adult-activation operation was reused for another target.');
                }

                if ($operation->status === 'handled' && $operation->result !== null) {
                    $result = json_decode($operation->result, true, 512, JSON_THROW_ON_ERROR);

                    return new AdultActivationResult(
                        memberId: $result['member_id'],
                        userId: $result['user_id'],
                        accountStatus: $result['account_status'],
                        mustChangePassword: (bool) $result['must_change_password'],
                        replayed: true,
                    );
                }

                throw new MemberIdentityException('The adult-activation operation is already in progress.');
            }

            DB::table('member_operations')->insert([
                'id' => (string) Str::uuid(),
                'operation_type' => 'adult-activation',
                'operation_id' => $operationId,
                'payload_hash' => $payloadHash,
                'status' => 'pending',
                'result' => null,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);

            if ($user->account_status === 'suspended') {
                throw new MemberIdentityException('A suspended account cannot be activated.');
            }

            if ($user->account_status !== 'pending_activation') {
                throw new MemberIdentityException('Adult activation requires a pending account.');
            }

            if (new \DateTimeImmutable((string) $member->birth_date) > $this->clock->now()->modify('-17 years')->setTime(0, 0)) {
                throw new MemberIdentityException('Only an adult Member may use this activation transition.');
            }

            if ($member->identity_status !== IdentityStatus::Verified->value) {
                throw new MemberIdentityException('A verified Member identity is required.');
            }

            $this->assertCurrentEvidence($memberId);

            $mustChangePassword = (bool) $user->must_change_password;
            $user->forceFill([
                'account_status' => 'active',
                'login_enabled' => true,
                'must_change_password' => $mustChangePassword,
            ])->save();

            $now = $this->clock->now();
            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.adult-activation',
                source: 'member',
                outcome: 'activated',
                occurredAt: $now,
                targetType: Member::class,
                targetId: $memberId,
                metadata: [
                    'account_status' => 'active',
                    'login_enabled' => true,
                    'change_required' => $mustChangePassword,
                ],
            ));

            $result = [
                'member_id' => $memberId,
                'user_id' => (string) $user->id,
                'account_status' => 'active',
                'must_change_password' => $mustChangePassword,
            ];
            DB::table('member_operations')
                ->where('operation_type', 'adult-activation')
                ->where('operation_id', $operationId)
                ->update([
                    'status' => 'handled',
                    'result' => json_encode($result, JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                ]);

            return new AdultActivationResult(
                memberId: $memberId,
                userId: (string) $user->id,
                accountStatus: 'active',
                mustChangePassword: $mustChangePassword,
            );
        });
    }

    private function assertCurrentEvidence(string $memberId): void
    {
        foreach (['ktp', 'profile_photo'] as $type) {
            if (! DB::table('member_verification_assets')
                ->where('member_id', $memberId)
                ->where('type', $type)
                ->where('review_status', 'approved')
                ->where('is_current', true)
                ->exists()) {
                throw new MemberIdentityException("Approved current {$type} evidence is required.");
            }
        }
    }
}
