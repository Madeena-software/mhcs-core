<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\MemberRegistrationResult;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

final readonly class MemberRegistrationService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private ProtectedIdentifierService $identifiers,
        private MedicalRecordNumberGenerator $mrn,
        private MemberVerificationAssetService $assets,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function register(MemberRegistrationData $data): MemberRegistrationResult
    {
        $context = $this->authorization->context('member.registration');
        $this->assertRegistrationAuthorization($data, $context);
        $adult = $this->isAdult($data->birthDate);
        $approved = $this->authorization->isAdministrator($context);
        $payloadHash = $this->payloadHash($data);

        try {
            return DB::transaction(function () use ($data, $context, $adult, $approved, $payloadHash): MemberRegistrationResult {
                $operation = DB::table('member_operations')
                    ->where('operation_type', 'registration')
                    ->where('operation_id', $data->operationId)
                    ->lockForUpdate()
                    ->first();

                if ($operation !== null) {
                    if (! hash_equals($operation->payload_hash, $payloadHash)) {
                        throw new MemberIdentityException('The registration operation was reused with different data.');
                    }

                    if ($operation->status === 'handled' && $operation->result !== null) {
                        $result = json_decode($operation->result, true, 512, JSON_THROW_ON_ERROR);

                        return new MemberRegistrationResult(
                            memberId: $result['member_id'],
                            userId: $result['user_id'],
                            medicalRecordNumber: $result['medical_record_number'],
                            accountStatus: $result['account_status'],
                            identityStatus: $result['identity_status'],
                            replayed: true,
                        );
                    }

                    throw new MemberIdentityException('The registration operation is already in progress.');
                }

                DB::table('member_operations')->insert([
                    'id' => (string) Str::uuid(),
                    'operation_type' => 'registration',
                    'operation_id' => $data->operationId,
                    'payload_hash' => $payloadHash,
                    'status' => 'pending',
                    'result' => null,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

                $nik = $this->identifiers->protect($data->nik);
                $familyId = $this->familyId($data->kk);
                $memberId = (string) Str::uuid();
                $userId = (string) Str::uuid();
                $medicalRecordNumber = $this->mrn->generate();
                $password = $data->password ?? bin2hex(random_bytes(32));
                $accountStatus = $adult && $approved ? 'active' : 'pending_activation';
                $identityStatus = $approved ? IdentityStatus::Verified->value : IdentityStatus::PendingVerification->value;

                DB::table('users')->insert([
                    'id' => $userId,
                    'email' => $this->email($data->email),
                    'email_verified_at' => null,
                    'password' => Hash::make($password),
                    'remember_token' => null,
                    'account_status' => $accountStatus,
                    'login_enabled' => $adult && $approved,
                    'must_change_password' => $data->password === null || ! $adult,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

                DB::table('members')->insert([
                    'id' => $memberId,
                    'user_id' => $userId,
                    'family_id' => $familyId,
                    'medical_record_number' => $medicalRecordNumber,
                    'identity_status' => $identityStatus,
                    'identity_document_type' => $data->identityDocument->type->value,
                    'encrypted_nik' => $nik['encrypted_display'],
                    'nik_lookup_digest' => $nik['lookup_digest'],
                    'name' => $data->name,
                    'birth_date' => $data->birthDate->format('Y-m-d'),
                    'administrative_gender' => $data->administrativeGender,
                    'registration_source' => $data->registrationSource->value,
                    'phone' => $data->phone,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

                $member = Member::query()->findOrFail($memberId);
                $this->assets->recordInTransaction($member, $data->identityDocument, $context, $approved);
                $this->assets->recordInTransaction($member, $data->profilePhoto, $context, $approved);
                $this->externalIdentifiers($memberId, $data->externalIdentifiers);
                $this->guardians($memberId, $data->guardianMemberIds, $adult, $context);

                $result = new MemberRegistrationResult(
                    memberId: $memberId,
                    userId: $userId,
                    medicalRecordNumber: $medicalRecordNumber,
                    accountStatus: $accountStatus,
                    identityStatus: $identityStatus,
                );

                $now = $this->clock->now();
                $this->audit->append(AuditEvent::fromContext(
                    $context,
                    action: 'member.registration',
                    source: 'member',
                    outcome: 'success',
                    occurredAt: $now,
                    targetType: Member::class,
                    targetId: $memberId,
                    metadata: [
                        'registration_source' => $data->registrationSource->value,
                        'account_status' => $accountStatus,
                        'identity_status' => $identityStatus,
                        'dependent' => ! $adult,
                    ],
                ));

                DB::table('member_operations')
                    ->where('operation_type', 'registration')
                    ->where('operation_id', $data->operationId)
                    ->update([
                        'status' => 'handled',
                        'result' => json_encode([
                            'member_id' => $result->memberId,
                            'user_id' => $result->userId,
                            'medical_record_number' => $result->medicalRecordNumber,
                            'account_status' => $result->accountStatus,
                            'identity_status' => $result->identityStatus,
                        ], JSON_THROW_ON_ERROR),
                        'updated_at' => $now,
                    ]);

                return $result;
            });
        } catch (MemberIdentityException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new MemberIdentityException('Member registration could not be completed.', previous: $exception);
        } catch (JsonException $exception) {
            throw new MemberIdentityException('Member registration result could not be recorded.', previous: $exception);
        } catch (Throwable $exception) {
            throw new MemberIdentityException('Member registration could not be completed.', previous: $exception);
        }
    }

    private function assertRegistrationAuthorization(MemberRegistrationData $data, AuthenticatedContext $context): void
    {
        if ($data->registrationSource === RegistrationSource::Administrator || ! $this->isAdult($data->birthDate)) {
            if (! $this->authorization->isAdministrator($context)) {
                throw new MemberIdentityException('Administrator authorization is required for this registration.');
            }
        }

        if ($data->identityDocument->type === VerificationAssetType::ProfilePhoto || $data->profilePhoto->type !== VerificationAssetType::ProfilePhoto) {
            throw new MemberIdentityException('Registration requires one identity document and one profile photograph.');
        }

        if ($data->kk === null && ! $this->isAdult($data->birthDate)) {
            throw new MemberIdentityException('Child registration requires a family card.');
        }

        if (! $this->isAdult($data->birthDate) && $data->guardianMemberIds === []) {
            throw new MemberIdentityException('Child registration requires a verified guardian.');
        }

        $expected = $this->isAdult($data->birthDate) ? VerificationAssetType::Ktp : VerificationAssetType::Kia;
        if ($data->identityDocument->type !== $expected) {
            throw new MemberIdentityException('The identity document does not match the standard age path.');
        }
    }

    private function familyId(?string $kk): ?string
    {
        if ($kk === null) {
            return null;
        }

        $protected = $this->identifiers->protect($kk);
        $family = DB::table('families')->where('family_card_lookup_digest', $protected['lookup_digest'])->lockForUpdate()->first();
        if ($family !== null) {
            return $family->id;
        }

        $id = (string) Str::uuid();
        DB::table('families')->insert([
            'id' => $id,
            'encrypted_family_card_number' => $protected['encrypted_display'],
            'family_card_lookup_digest' => $protected['lookup_digest'],
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        return $id;
    }

    /** @param list<string> $guardianIds */
    private function guardians(string $memberId, array $guardianIds, bool $adult, AuthenticatedContext $context): void
    {
        if ($adult && $guardianIds !== []) {
            throw new MemberIdentityException('Adult registration cannot create dependent guardian links.');
        }

        foreach ($guardianIds as $guardianId) {
            if ($guardianId === $memberId) {
                throw new MemberIdentityException('A Member cannot be their own guardian.');
            }

            $guardian = DB::table('members')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->where('members.id', $guardianId)
                ->where('members.identity_status', IdentityStatus::Verified->value)
                ->where('users.account_status', 'active')
                ->where('users.login_enabled', true)
                ->where('users.must_change_password', false)
                ->select('members.id')
                ->first();

            if ($guardian === null) {
                throw new MemberIdentityException('Each guardian must be an active verified Member account.');
            }

            DB::table('member_guardians')->insert([
                'id' => (string) Str::uuid(),
                'child_member_id' => $memberId,
                'guardian_member_id' => $guardianId,
                'status' => 'verified',
                'verified_by_user_id' => (string) $context->actorId,
                'starts_at' => $this->clock->now(),
                'ends_at' => null,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        }
    }

    /** @param list<array{namespace: string, value: string}> $identifiers */
    private function externalIdentifiers(string $memberId, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if (! is_array($identifier) || trim((string) ($identifier['namespace'] ?? '')) === '' || trim((string) ($identifier['value'] ?? '')) === '') {
                throw new MemberIdentityException('External identifiers require a namespace and value.');
            }

            DB::table('member_external_identifiers')->insert([
                'id' => (string) Str::uuid(),
                'member_id' => $memberId,
                'namespace' => trim($identifier['namespace']),
                'value' => trim($identifier['value']),
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        }
    }

    private function payloadHash(MemberRegistrationData $data): string
    {
        return hash('sha256', json_encode([
            'operation_id' => $data->operationId,
            'email' => $data->email,
            'name' => $data->name,
            'birth_date' => $data->birthDate->format('Y-m-d'),
            'gender' => $data->administrativeGender,
            'nik' => $data->nik,
            'kk' => $data->kk,
            'phone' => $data->phone,
            'source' => $data->registrationSource->value,
            'identity_asset' => [$data->identityDocument->type->value, $data->identityDocument->object->checksum],
            'profile_asset' => [$data->profilePhoto->type->value, $data->profilePhoto->object->checksum],
            'guardians' => $data->guardianMemberIds,
            'external_identifiers' => $data->externalIdentifiers,
        ], JSON_THROW_ON_ERROR));
    }

    private function email(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new MemberIdentityException('The email address is invalid.');
        }

        return $email;
    }

    private function isAdult(\DateTimeInterface $birthDate): bool
    {
        return $birthDate <= $this->clock->now()->modify('-17 years')->setTime(0, 0);
    }
}
