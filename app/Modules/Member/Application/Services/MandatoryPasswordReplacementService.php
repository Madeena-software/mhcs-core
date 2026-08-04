<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Security\TemporaryCredentialIssuer;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class MandatoryPasswordReplacementService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private MemberContextResolver $members,
        private TemporaryCredentialIssuer $credentials,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function replace(string $userId, string $temporaryCredential, string $replacementPassword, string $operationId): void
    {
        $context = $this->authorization->context('member.password-replacement');

        if (trim($temporaryCredential) === '' || trim($replacementPassword) === '' || trim($operationId) === '') {
            throw new MemberIdentityException('Password replacement requires complete credentials.');
        }

        DB::transaction(function () use ($userId, $temporaryCredential, $replacementPassword, $operationId, $context): void {
            if (
                (string) $context->actorId !== $userId
                && ! $this->authorization->hasAdministratorPermission($context, MemberAuthorization::ACCOUNT_STATE_PERMISSION)
            ) {
                throw new MemberIdentityException('Password replacement authorization failed.');
            }

            $payloadHash = hash('sha256', $userId.'|'.$operationId);
            $operation = DB::table('member_operations')
                ->where('operation_type', 'password-replacement')
                ->where('operation_id', $operationId)
                ->lockForUpdate()
                ->first();

            if ($operation !== null) {
                if (! hash_equals($operation->payload_hash, $payloadHash)) {
                    throw new MemberIdentityException('The password replacement operation was reused.');
                }

                if ($operation->status === 'handled') {
                    return;
                }

                throw new MemberIdentityException('The password replacement operation is already in progress.');
            }

            DB::table('member_operations')->insert([
                'id' => (string) Str::uuid(),
                'operation_type' => 'password-replacement',
                'operation_id' => $operationId,
                'payload_hash' => $payloadHash,
                'status' => 'pending',
                'result' => null,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);

            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if (
                $user === null
                || $user->account_status !== 'active'
                || ! ($user->login_enabled ?? false)
                || ! $user->must_change_password
            ) {
                throw new MemberIdentityException('Password replacement credentials are invalid.');
            }

            $linkedMembers = Member::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->get();
            $member = $linkedMembers->count() === 1 ? $linkedMembers->first() : null;

            if ($member === null || ! $this->members->isEligibleAdult($member)) {
                throw new MemberIdentityException('Password replacement credentials are invalid.');
            }

            if (! Hash::check($temporaryCredential, $user->password)) {
                throw new MemberIdentityException('Password replacement credentials are invalid.');
            }

            $this->credentials->replace($user, $replacementPassword);
            $now = $this->clock->now();
            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.password-replacement',
                source: 'member',
                outcome: 'success',
                occurredAt: $now,
                targetType: User::class,
                targetId: $userId,
                metadata: ['mandatory_change_cleared' => true],
            ));

            DB::table('member_operations')
                ->where('operation_type', 'password-replacement')
                ->where('operation_id', $operationId)
                ->update(['status' => 'handled', 'result' => json_encode(['completed' => true]), 'updated_at' => $now]);
        });
    }
}
