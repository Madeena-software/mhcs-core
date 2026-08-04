<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Data\AgeTransitionResult;
use App\Modules\Member\Domain\Enums\GuardianStatus;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Security\TemporaryCredentialIssuer;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Age17TransitionService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private TemporaryCredentialIssuer $credentials,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function transition(string $memberId, string $operationId): AgeTransitionResult
    {
        $context = $this->authorization->ageTransition();
        if (trim($operationId) === '') {
            throw new MemberIdentityException('An age-transition operation identity is required.');
        }

        return DB::transaction(function () use ($memberId, $operationId, $context): AgeTransitionResult {
            $payloadHash = hash('sha256', $memberId.'|'.$operationId);
            $operation = DB::table('member_operations')
                ->where('operation_type', 'age-transition')
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();

            if ($operation !== null) {
                if (! hash_equals($operation->payload_hash, $payloadHash)) {
                    throw new MemberIdentityException('The age-transition operation was reused.');
                }

                if ($operation->status === 'handled' && $operation->result !== null) {
                    $result = json_decode($operation->result, true, 512, JSON_THROW_ON_ERROR);

                    return new AgeTransitionResult(
                        memberId: $result['member_id'],
                        userId: $result['user_id'],
                        accountStatus: $result['account_status'],
                        endedGuardianRelations: $result['ended_guardian_relations'],
                        temporaryCredential: null,
                        replayed: true,
                    );
                }

                throw new MemberIdentityException('The age-transition operation is already in progress.');
            }

            DB::table('member_operations')->insert([
                'id' => (string) Str::uuid(),
                'operation_type' => 'age-transition',
                'operation_id' => $operationId,
                'payload_hash' => $payloadHash,
                'status' => 'pending',
                'result' => null,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);

            $member = DB::table('members')->where('id', $memberId)->lockForUpdate()->first();
            if ($member === null) {
                throw new MemberIdentityException('The Member identity was not found.');
            }

            $user = User::query()->whereKey($member->user_id)->lockForUpdate()->first();
            if ($user === null) {
                throw new MemberIdentityException('The Member authentication record was not found.');
            }

            if (new \DateTimeImmutable($member->birth_date) > $this->clock->now()->modify('-17 years')->setTime(0, 0)) {
                throw new MemberIdentityException('The Member is not yet eligible for independent access.');
            }

            if ($user->login_enabled && $user->account_status === 'active') {
                $now = $this->clock->now();
                $result = ['member_id' => $memberId, 'user_id' => $user->id, 'account_status' => 'active', 'ended_guardian_relations' => 0];
                DB::table('member_operations')
                    ->where('operation_type', 'age-transition')
                    ->where('operation_id', $operationId)
                    ->update(['status' => 'handled', 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => $now]);

                return new AgeTransitionResult($memberId, $user->id, 'active', 0, null, true);
            }

            if ($user->account_status === 'suspended') {
                throw new MemberIdentityException('A suspended account requires an independent restoration decision.');
            }

            $ktp = DB::table('member_verification_assets')
                ->where('member_id', $memberId)
                ->where('type', 'ktp')
                ->where('review_status', 'approved')
                ->where('is_current', true)
                ->first();
            if ($ktp === null) {
                throw new MemberIdentityException('Approved current KTP evidence is required.');
            }

            if (! DB::table('member_verification_assets')
                ->where('member_id', $memberId)
                ->where('type', 'profile_photo')
                ->where('review_status', 'approved')
                ->where('is_current', true)
                ->exists()) {
                throw new MemberIdentityException('An approved current profile photograph is required.');
            }

            $temporaryCredential = $this->credentials->issue($user);
            $user->forceFill([
                'account_status' => 'active',
                'login_enabled' => true,
                'must_change_password' => true,
            ])->save();
            $now = $this->clock->now();
            $ended = DB::table('member_guardians')
                ->where('child_member_id', $memberId)
                ->where('status', GuardianStatus::Verified->value)
                ->whereNull('ends_at')
                ->update([
                    'status' => GuardianStatus::Ended->value,
                    'ends_at' => $now,
                    'updated_at' => $now,
                ]);
            DB::table('member_verification_assets')
                ->where('member_id', $memberId)
                ->where('type', 'kia')
                ->where('is_current', true)
                ->update(['is_current' => false, 'updated_at' => $now]);
            DB::table('members')->where('id', $memberId)->update([
                'identity_status' => IdentityStatus::Verified->value,
                'updated_at' => $now,
            ]);

            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.age-transition',
                source: 'member',
                outcome: 'activated',
                occurredAt: $now,
                targetType: Member::class,
                targetId: $memberId,
                metadata: ['ended_guardian_relations' => $ended, 'change_required' => true],
            ));

            $result = ['member_id' => $memberId, 'user_id' => $user->id, 'account_status' => 'active', 'ended_guardian_relations' => $ended];
            DB::table('member_operations')
                ->where('operation_type', 'age-transition')
                ->where('operation_id', $operationId)
                ->update(['status' => 'handled', 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => $now]);

            return new AgeTransitionResult($memberId, $user->id, 'active', $ended, $temporaryCredential);
        });
    }
}
