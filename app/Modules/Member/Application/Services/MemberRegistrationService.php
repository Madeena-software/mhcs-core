<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\MemberRegistrationResult;
use App\Modules\Member\Application\Data\NonclinicalValidationMemberRegistrationData;
use App\Modules\Member\Application\Data\PrestigeUploadDiagnosticMemberRegistrationData;
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
use App\Shared\Validation\NonclinicalValidationContext;
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
        if ($data->registrationSource === RegistrationSource::NonclinicalValidation) {
            throw new MemberIdentityException('Nonclinical validation requires its dedicated registration boundary.');
        }

        $adult = $this->isAdult($data->birthDate);
        $context = $this->authorization->registration($data->registrationSource, $adult);
        $this->assertRegistrationAuthorization($data, $context, $adult);
        $approved = $this->authorization->hasAdministratorPermission($context, MemberAuthorization::IDENTITY_VERIFICATION_PERMISSION);
        $payloadHash = $this->payloadHash($data, (string) $context->actorId);

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
                $accountStatus = $adult && $approved ? 'active' : 'pending_activation';
                $identityStatus = $approved ? IdentityStatus::Verified->value : IdentityStatus::PendingVerification->value;

                if ($data->registrationSource === RegistrationSource::Online) {
                    $existingUser = User::query()->whereKey((string) $context->actorId)->lockForUpdate()->first();
                    if ($existingUser === null || DB::table('members')->where('user_id', $existingUser->id)->exists()) {
                        throw new MemberIdentityException('Online registration requires an existing unbound account.');
                    }

                    $userId = (string) $existingUser->id;
                    $existingUser->forceFill([
                        'account_status' => $accountStatus,
                        'login_enabled' => $adult && $approved,
                        'must_change_password' => $existingUser->must_change_password,
                    ])->save();
                } else {
                    $password = $data->password ?? bin2hex(random_bytes(32));

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
                }

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
                $this->assets->recordForRegistration($member, $data->identityDocument, $context);
                $this->assets->recordForRegistration($member, $data->profilePhoto, $context);
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

    public function registerNonclinicalValidation(NonclinicalValidationMemberRegistrationData $data): MemberRegistrationResult
    {
        return $this->registerFixedNonclinicalValidation($data);
    }

    public function registerPrestigeUploadDiagnostic(PrestigeUploadDiagnosticMemberRegistrationData $data): MemberRegistrationResult
    {
        return $this->registerFixedNonclinicalValidation($data);
    }

    private function registerFixedNonclinicalValidation(NonclinicalValidationMemberRegistrationData|PrestigeUploadDiagnosticMemberRegistrationData $data): MemberRegistrationResult
    {
        $legacy = $data->contextKey === NonclinicalValidationContext::KEY
            && $data->markerNamespace === NonclinicalValidationContext::MARKER_NAMESPACE
            && $data->markerValue === NonclinicalValidationContext::KEY;
        $prestige = $data->contextKey === NonclinicalValidationContext::PRESTIGE_KEY
            && $data->markerNamespace === NonclinicalValidationContext::PRESTIGE_MARKER_NAMESPACE
            && in_array($data->markerValue, ['gbsuparta', 'ipang'], true)
            && $data->displayName === $data->markerValue;
        if (! $legacy && ! $prestige) {
            throw new MemberIdentityException('The fixed nonclinical validation context is not supported.');
        }
        $context = $this->authorization->context('member.nonclinical-validation');
        if (! in_array('system', $context->roles, true)) {
            throw new MemberIdentityException('Nonclinical validation registration requires a trusted system context.');
        }

        $payloadHash = hash('sha256', json_encode([
            'context' => $data->contextKey,
            'operation_id' => $data->operationId,
            'user_id' => $data->userId,
            'marker_namespace' => $data->markerNamespace,
            'marker_value' => $data->markerValue,
            'display_name' => $data->displayName,
        ], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($data, $context, $payloadHash): MemberRegistrationResult {
                $operation = DB::table('member_operations')
                    ->where('operation_type', 'nonclinical_validation_registration')
                    ->where('operation_id', $data->operationId)
                    ->lockForUpdate()
                    ->first();

                if ($operation !== null) {
                    if (! hash_equals($operation->payload_hash, $payloadHash)) {
                        throw new MemberIdentityException('The validation registration operation was reused with different data.');
                    }

                    if ($operation->status === 'handled' && $operation->result !== null) {
                        $result = json_decode($operation->result, true, 512, JSON_THROW_ON_ERROR);
                        $this->assertNonclinicalMemberState($result['member_id'], $data->userId, $data->markerNamespace, $data->markerValue);

                        return new MemberRegistrationResult(
                            memberId: $result['member_id'],
                            userId: $result['user_id'],
                            medicalRecordNumber: $result['medical_record_number'],
                            accountStatus: $result['account_status'],
                            identityStatus: $result['identity_status'],
                            replayed: true,
                        );
                    }

                    throw new MemberIdentityException('The validation registration operation is already in progress.');
                }

                $user = DB::table('users')->where('id', $data->userId)->lockForUpdate()->first();
                if ($user === null || $user->account_status !== 'active' || ! (bool) $user->login_enabled || (bool) $user->must_change_password) {
                    throw new MemberIdentityException('Nonclinical validation requires an active authenticated account.');
                }
                if (DB::table('members')->where('user_id', $data->userId)->exists()) {
                    throw new MemberIdentityException('The validation account is already linked to a Member.');
                }
                if (DB::table('member_external_identifiers')->where('namespace', $data->markerNamespace)->where('value', $data->markerValue)->exists()) {
                    throw new MemberIdentityException('The validation marker already belongs to another Member.');
                }

                DB::table('member_operations')->insert([
                    'id' => (string) Str::uuid(),
                    'operation_type' => 'nonclinical_validation_registration',
                    'operation_id' => $data->operationId,
                    'payload_hash' => $payloadHash,
                    'status' => 'pending',
                    'result' => null,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);

                $memberId = (string) Str::uuid();
                $medicalRecordNumber = $this->mrn->generate();
                $now = $this->clock->now();
                DB::table('members')->insert([
                    'id' => $memberId,
                    'user_id' => $data->userId,
                    'family_id' => null,
                    'medical_record_number' => $medicalRecordNumber,
                    'identity_status' => IdentityStatus::NonclinicalValidation->value,
                    'identity_document_type' => null,
                    'encrypted_nik' => null,
                    'nik_lookup_digest' => null,
                    'name' => $data->displayName,
                    'birth_date' => '1985-08-04',
                    'administrative_gender' => 'nonclinical',
                    'registration_source' => RegistrationSource::NonclinicalValidation->value,
                    'phone' => null,
                    'current_address' => 'Nonclinical validation',
                    'emergency_contact_name' => 'Nonclinical validation',
                    'emergency_contact_relationship' => 'Nonclinical validation',
                    'emergency_contact_phone' => 'Nonclinical validation',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('member_external_identifiers')->insert([
                    'id' => (string) Str::uuid(),
                    'member_id' => $memberId,
                    'namespace' => $data->markerNamespace,
                    'value' => $data->markerValue,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->assertNonclinicalMemberState($memberId, $data->userId, $data->markerNamespace, $data->markerValue);
                $result = new MemberRegistrationResult(
                    memberId: $memberId,
                    userId: $data->userId,
                    medicalRecordNumber: $medicalRecordNumber,
                    accountStatus: (string) $user->account_status,
                    identityStatus: IdentityStatus::NonclinicalValidation->value,
                );
                $this->audit->append(AuditEvent::fromContext(
                    $context,
                    action: 'member.nonclinical-validation.registered',
                    source: 'member',
                    outcome: 'success',
                    occurredAt: $now,
                    targetType: Member::class,
                    targetId: $memberId,
                    metadata: ['validation_context' => $data->contextKey, 'nonclinical' => true],
                ));
                DB::table('member_operations')
                    ->where('operation_type', 'nonclinical_validation_registration')
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
            throw new MemberIdentityException('Nonclinical validation registration could not be completed.', previous: $exception);
        } catch (JsonException $exception) {
            throw new MemberIdentityException('Nonclinical validation registration result could not be recorded.', previous: $exception);
        } catch (Throwable $exception) {
            throw new MemberIdentityException('Nonclinical validation registration could not be completed.', previous: $exception);
        }
    }

    private function assertRegistrationAuthorization(MemberRegistrationData $data, AuthenticatedContext $context, bool $adult): void
    {
        if ($data->identityDocument->type === VerificationAssetType::ProfilePhoto || $data->profilePhoto->type !== VerificationAssetType::ProfilePhoto) {
            throw new MemberIdentityException('Registration requires one identity document and one profile photograph.');
        }

        if ($data->kk === null && ! $adult) {
            throw new MemberIdentityException('Child registration requires a family card.');
        }

        if (! $adult && $data->guardianMemberIds === []) {
            throw new MemberIdentityException('Child registration requires a verified guardian.');
        }

        if (! $adult && ! $this->authorization->hasAdministratorPermission($context, MemberAuthorization::REGISTRATION_PERMISSION)) {
            throw new MemberIdentityException('Child registration requires authorized administrator assistance.');
        }

        $expected = $adult ? VerificationAssetType::Ktp : VerificationAssetType::Kia;
        if ($data->identityDocument->type !== $expected) {
            throw new MemberIdentityException('The identity document does not match the standard age path.');
        }
    }

    private function assertNonclinicalMemberState(string $memberId, string $userId, string $markerNamespace = NonclinicalValidationContext::MARKER_NAMESPACE, string $markerValue = NonclinicalValidationContext::KEY): void
    {
        $member = DB::table('members')->where('id', $memberId)->first();
        $markerCount = DB::table('member_external_identifiers')
            ->where('namespace', $markerNamespace)
            ->where('value', $markerValue)
            ->count();

        if (
            $member === null
            || (string) $member->user_id !== $userId
            || $member->identity_status !== IdentityStatus::NonclinicalValidation->value
            || $member->registration_source !== RegistrationSource::NonclinicalValidation->value
            || $member->identity_document_type !== null
            || $member->encrypted_nik !== null
            || $member->nik_lookup_digest !== null
            || $markerCount !== 1
            || ! DB::table('member_external_identifiers')->where('member_id', $memberId)->where('namespace', $markerNamespace)->where('value', $markerValue)->exists()
            || DB::table('member_verification_assets')->where('member_id', $memberId)->exists()
        ) {
            throw new MemberIdentityException('The nonclinical validation Member state is inconsistent.');
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

    private function payloadHash(MemberRegistrationData $data, string $actorId): string
    {
        return hash('sha256', json_encode([
            'operation_id' => $data->operationId,
            'actor_id' => $actorId,
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
