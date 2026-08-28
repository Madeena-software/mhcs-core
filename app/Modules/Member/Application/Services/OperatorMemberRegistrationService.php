<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorMemberRegistrationContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorMemberRegistrationService implements OperatorMemberRegistrationContract
{
    public function __construct(
        private TrustedOperatorSiteContextResolver $trustedSite,
        private MedicalRecordNumberGenerator $mrn,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function registerWalkIn(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $name,
        ?string $email,
        ?string $phone,
        string $operationId,
    ): array {
        $this->assertAccess($context, $operatorSiteId);
        $name = $this->name($name);
        $email = $this->email($email);
        $phone = $this->phone($phone);
        $operationId = trim($operationId);
        if ($operationId === '' || strlen($operationId) > 191) {
            throw new MemberIdentityException('The registration operation identity is invalid.');
        }
        if ($email === null && $phone === null) {
            throw new MemberIdentityException('Provide an email address or phone number for the walk-in Member.');
        }

        $payload = [
            'operator_site_id' => trim($operatorSiteId),
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];

        try {
            $outcome = $this->idempotency->run($operationId, 'member.operator-walk-in-registration', $payload, function () use ($context, $operatorSiteId, $name, $email, $phone, $operationId, $payload): array {
                return DB::transaction(function () use ($context, $operatorSiteId, $name, $email, $phone, $operationId, $payload): array {
                    $operationHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
                    DB::table('member_operations')->insert([
                        'id' => (string) Str::uuid(),
                        'operation_type' => 'operator_walk_in_registration',
                        'operation_id' => $operationId,
                        'payload_hash' => $operationHash,
                        'status' => 'pending',
                        'result' => null,
                        'created_at' => $this->clock->now(),
                        'updated_at' => $this->clock->now(),
                    ]);

                    if ($email !== null && DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->exists()) {
                        throw new MemberIdentityException('A Member already uses this email. Search and select the existing Member.');
                    }

                    $now = $this->clock->now();
                    $userId = (string) Str::uuid();
                    $memberId = (string) Str::uuid();
                    $medicalRecordNumber = $this->mrn->generate();
                    $password = bin2hex(random_bytes(32));

                    DB::table('users')->insert([
                        'id' => $userId,
                        'email' => $email,
                        'email_verified_at' => null,
                        'password' => Hash::make($password),
                        'remember_token' => null,
                        'account_status' => 'pending_activation',
                        'login_enabled' => false,
                        'must_change_password' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('members')->insert([
                        'id' => $memberId,
                        'user_id' => $userId,
                        'family_id' => null,
                        'medical_record_number' => $medicalRecordNumber,
                        'identity_status' => IdentityStatus::PendingVerification->value,
                        'identity_document_type' => null,
                        'encrypted_nik' => null,
                        'nik_lookup_digest' => null,
                        'name' => $name,
                        'birth_date' => null,
                        'administrative_gender' => null,
                        'registration_source' => RegistrationSource::WalkIn->value,
                        'phone' => $phone,
                        'current_address' => null,
                        'emergency_contact_name' => null,
                        'emergency_contact_relationship' => null,
                        'emergency_contact_phone' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $result = [
                        'member_id' => $memberId,
                        'user_id' => $userId,
                        'medical_record_number' => $medicalRecordNumber,
                        'account_status' => 'pending_activation',
                        'identity_status' => IdentityStatus::PendingVerification->value,
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'member.operator-walk-in.registered',
                        'member',
                        'success',
                        $now,
                        Member::class,
                        $memberId,
                        metadata: [
                            'operator_assisted' => true,
                            'operator_site_id' => trim($operatorSiteId),
                            'registration_source' => RegistrationSource::WalkIn->value,
                            'identity_status' => IdentityStatus::PendingVerification->value,
                            'login_enabled' => false,
                        ],
                    ));
                    DB::table('member_operations')
                        ->where('operation_type', 'operator_walk_in_registration')
                        ->where('operation_id', $operationId)
                        ->update([
                            'status' => 'handled',
                            'result' => json_encode($result, JSON_THROW_ON_ERROR),
                            'updated_at' => $now,
                        ]);

                    return $result;
                });
            });

            $result = is_array($outcome->result) ? $outcome->result : [];
            $result['replayed'] = $outcome->status === 'replayed';

            return $result;
        } catch (IdempotencyConflict $exception) {
            throw $exception;
        } catch (MemberIdentityException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'email')) {
                throw new MemberIdentityException('A Member already uses this email. Search and select the existing Member.', previous: $exception);
            }

            throw new MemberIdentityException('Member registration could not be completed.', previous: $exception);
        } catch (Throwable $exception) {
            throw new MemberIdentityException('Member registration could not be completed.', previous: $exception);
        }
    }

    public function searchMembers(AuthenticatedContext $context, string $operatorSiteId, string $query): array
    {
        $this->assertAccess($context, $operatorSiteId);
        $query = trim($query);
        if (mb_strlen($query, 'UTF-8') < 2 || mb_strlen($query, 'UTF-8') > 100) {
            throw new MemberIdentityException('Provide at least two characters to search for a Member.');
        }
        $like = '%'.strtr($query, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';

        return DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where(function ($builder) use ($like, $query): void {
                $builder
                    ->where('members.name', 'like', $like)
                    ->orWhere('members.medical_record_number', 'like', $like)
                    ->orWhereRaw('LOWER(users.email) LIKE LOWER(?)', [$like])
                    ->orWhere('members.phone', 'like', $like)
                    ->orWhere('members.medical_record_number', '=', $query);
            })
            ->select(['members.id as member_id', 'members.name as member_name', 'members.medical_record_number', 'users.email', 'members.phone'])
            ->orderBy('members.name')
            ->limit(20)
            ->get()
            ->map(static fn (object $member): array => [
                'member_id' => (string) $member->member_id,
                'member_name' => (string) $member->member_name,
                'medical_record_number' => (string) $member->medical_record_number,
                'email' => $member->email === null ? null : (string) $member->email,
                'phone' => $member->phone === null ? null : (string) $member->phone,
            ])
            ->all();
    }

    private function assertAccess(AuthenticatedContext $context, string $operatorSiteId): void
    {
        if (
            $context->actorId === null
            || $context->operationId === null
            || ! DB::table('users')
                ->where('id', (string) $context->actorId)
                ->where('account_status', 'active')
                ->where('login_enabled', true)
                ->where('must_change_password', false)
                ->exists()
            || ! $this->trustedSite->matches($context, trim($operatorSiteId), 'operator.shift.manage')
        ) {
            throw new MemberIdentityException('Trusted Operator front-desk authorization is required.');
        }
    }

    private function name(string $value): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > 255 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
            throw new MemberIdentityException('Member name is invalid.');
        }

        return $value;
    }

    private function email(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = mb_strtolower(trim($value), 'UTF-8');
        if (strlen($value) > 255 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new MemberIdentityException('Member email is invalid.');
        }

        return $value;
    }

    private function phone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (strlen($value) > 64 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
            throw new MemberIdentityException('Member phone is invalid.');
        }

        return $value;
    }
}
