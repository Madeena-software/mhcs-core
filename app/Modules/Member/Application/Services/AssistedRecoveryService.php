<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Data\AssistedRecoveryData;
use App\Modules\Member\Application\Data\RecoveryResult;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Security\TemporaryCredentialIssuer;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final readonly class AssistedRecoveryService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private ProtectedIdentifierService $identifiers,
        private TemporaryCredentialIssuer $credentials,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function recover(AssistedRecoveryData $data): RecoveryResult
    {
        $context = $this->authorization->administrator('member.assisted-recovery');
        if (trim($data->operationId) === '') {
            throw new MemberIdentityException('A recovery operation identity is required.');
        }

        try {
            return DB::transaction(function () use ($data, $context): RecoveryResult {
                $payloadHash = hash('sha256', json_encode([
                    'operation_id' => $data->operationId,
                    'nik' => $data->nik,
                    'kk' => $data->kk,
                    'identity_asset_id' => $data->identityAssetId,
                    'profile_photo_asset_id' => $data->profilePhotoAssetId,
                ], JSON_THROW_ON_ERROR));
                $operation = DB::table('member_operations')
                    ->where('operation_type', 'assisted-recovery')
                    ->where('operation_id', $data->operationId)
                    ->lockForUpdate()
                    ->first();

                if ($operation !== null) {
                    if (! hash_equals($operation->payload_hash, $payloadHash)) {
                        throw new MemberIdentityException('The recovery operation was reused.');
                    }

                    if ($operation->status === 'handled' && $operation->result !== null) {
                        $result = json_decode($operation->result, true, 512, JSON_THROW_ON_ERROR);

                        return new RecoveryResult(
                            memberId: $result['member_id'],
                            userId: $result['user_id'],
                            accountStatus: $result['account_status'],
                            temporaryCredential: null,
                            replayed: true,
                        );
                    }

                    throw new MemberIdentityException('The recovery operation is already in progress.');
                }

                DB::table('member_operations')->insert([
                    'id' => (string) Str::uuid(),
                    'operation_type' => 'assisted-recovery',
                    'operation_id' => $data->operationId,
                    'payload_hash' => $payloadHash,
                    'status' => 'pending',
                    'result' => null,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

                $nikDigest = $this->identifiers->lookupDigest($data->nik);
                $kkDigest = $this->identifiers->lookupDigest($data->kk);
                $member = DB::table('members')
                    ->where('nik_lookup_digest', $nikDigest)
                    ->lockForUpdate()
                    ->first();
                $family = $member === null ? null : DB::table('families')
                    ->where('id', $member->family_id)
                    ->where('family_card_lookup_digest', $kkDigest)
                    ->first();

                if ($member === null || $family === null) {
                    throw new MemberIdentityException('Recovery evidence could not be verified.');
                }

                $expectedIdentityType = (new \DateTimeImmutable($member->birth_date)) <= $this->clock->now()->modify('-17 years')->setTime(0, 0)
                    ? VerificationAssetType::Ktp->value
                    : VerificationAssetType::Kia->value;
                $identity = DB::table('member_verification_assets')
                    ->where('id', $data->identityAssetId)
                    ->where('member_id', $member->id)
                    ->where('type', $expectedIdentityType)
                    ->where('review_status', 'approved')
                    ->where('is_current', true)
                    ->first();
                $profile = DB::table('member_verification_assets')
                    ->where('id', $data->profilePhotoAssetId)
                    ->where('member_id', $member->id)
                    ->where('type', VerificationAssetType::ProfilePhoto->value)
                    ->where('review_status', 'approved')
                    ->where('is_current', true)
                    ->first();

                if ($member->identity_status !== 'verified' || $identity === null || $profile === null) {
                    throw new MemberIdentityException('Recovery evidence could not be verified.');
                }

                $user = User::query()->whereKey($member->user_id)->lockForUpdate()->first();
                if ($user === null) {
                    throw new MemberIdentityException('Recovery evidence could not be verified.');
                }

                $temporaryCredential = $this->credentials->issue($user);
                $now = $this->clock->now();
                $this->audit->append(AuditEvent::fromContext(
                    $context,
                    action: 'member.assisted-recovery',
                    source: 'member',
                    outcome: 'success',
                    occurredAt: $now,
                    targetType: Member::class,
                    targetId: $member->id,
                    metadata: [
                        'identity_asset_type' => $expectedIdentityType,
                        'profile_evidence_approved' => true,
                        'account_status_preserved' => $user->account_status,
                    ],
                ));

                $result = [
                    'member_id' => $member->id,
                    'user_id' => $user->id,
                    'account_status' => $user->account_status,
                ];
                DB::table('member_operations')
                    ->where('operation_type', 'assisted-recovery')
                    ->where('operation_id', $data->operationId)
                    ->update(['status' => 'handled', 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => $now]);

                return new RecoveryResult($member->id, $user->id, $user->account_status, $temporaryCredential);
            });
        } catch (MemberIdentityException $exception) {
            $this->recordRejected($context);
            throw $exception;
        } catch (JsonException $exception) {
            $this->recordRejected($context);
            throw new MemberIdentityException('Recovery evidence could not be verified.', previous: $exception);
        } catch (\Throwable $exception) {
            $this->recordRejected($context);
            throw new MemberIdentityException('Recovery evidence could not be verified.', previous: $exception);
        }
    }

    private function recordRejected(AuthenticatedContext $context): void
    {
        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: 'member.assisted-recovery',
            source: 'member',
            outcome: 'rejected',
            occurredAt: $this->clock->now(),
            metadata: ['reason_code' => 'evidence_rejected'],
        ));
    }
}
