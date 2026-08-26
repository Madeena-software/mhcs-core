<?php

declare(strict_types=1);

namespace App\Shared\Validation;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Authorization\AuthorizationException;
use App\Shared\Authorization\AuthorizationGuard;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

final readonly class NonclinicalValidationAccountProvisioningService
{
    public const OPERATOR_SECRET_NAME = 'MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD';

    private const PURPOSE = 'production.validation-context.account-provision';

    private const OPERATOR_ROLE = 'operator';

    /** @var list<string> */
    private const OPERATOR_PERMISSIONS = [
        'operator.arrival.record',
        'operator.attendance.read',
        'operator.identity.verify',
        'operator.portal.access',
    ];

    public function __construct(
        private AuthorizationGuard $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @return array{replayed: bool, member_state: string, operator_state: string, member_user_id: string, operator_user_id: string} */
    public function provision(string $operatorSecret): array
    {
        $context = $this->trustedContext();
        if (trim($operatorSecret) === '') {
            throw new AuthorizationException('A validation Operator secret is required.');
        }

        try {
            return DB::transaction(function () use ($context, $operatorSecret): array {
                $member = $this->fixedUser($this->memberEmail());
                $operator = $this->fixedUser($this->operatorEmail());

                if ($member !== null || $operator !== null) {
                    if ($member === null || $operator === null) {
                        throw new AuthorizationException('The validation account state is partial.');
                    }

                    $this->assertExistingMember($member);
                    $this->assertExistingOperator($operator, $operatorSecret);

                    return [
                        'replayed' => true,
                        'member_state' => 'EXISTING_VALID',
                        'operator_state' => 'EXISTING_VALID',
                        'member_user_id' => (string) $member->getKey(),
                        'operator_user_id' => (string) $operator->getKey(),
                    ];
                }

                $this->assertNoOwnershipMarkers();

                $now = $this->clock->now();
                $memberId = (string) Str::uuid();
                $operatorId = (string) Str::uuid();
                $this->insertUser($memberId, $this->memberEmail(), Hash::make(bin2hex(random_bytes(32))), $now);
                $this->insertUser($operatorId, $this->operatorEmail(), Hash::make($operatorSecret), $now);

                foreach (self::OPERATOR_PERMISSIONS as $permission) {
                    DB::table('authorization_permission_assignments')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $operatorId,
                        'permission' => $permission,
                        'assigned_by_user_id' => null,
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                DB::table('authorization_role_assignments')->insert([
                    'id' => (string) Str::uuid(),
                    'user_id' => $operatorId,
                    'role' => self::OPERATOR_ROLE,
                    'assigned_by_user_id' => null,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->audit->append($this->provisionedEvent($context, $memberId, 'member', $now));
                $this->audit->append($this->provisionedEvent($context, $operatorId, 'operator', $now));

                return [
                    'replayed' => false,
                    'member_state' => 'CREATED',
                    'operator_state' => 'CREATED',
                    'member_user_id' => $memberId,
                    'operator_user_id' => $operatorId,
                ];
            });
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AuthorizationException('The validation account state could not be safely provisioned.', previous: $exception);
        }
    }

    private function trustedContext(): AuthenticatedContext
    {
        $context = $this->authorization->current(self::PURPOSE);
        if (! in_array('system', $context->roles, true)) {
            throw new AuthorizationException('A trusted system context is required.');
        }

        return $context;
    }

    private function memberEmail(): string
    {
        return 'mhcs-'.NonclinicalValidationContext::KEY.'-member@invalid';
    }

    private function operatorEmail(): string
    {
        return 'mhcs-'.NonclinicalValidationContext::KEY.'-operator@invalid';
    }

    private function fixedUser(string $email): ?User
    {
        $users = User::query()->where('email', $email)->lockForUpdate()->get();
        if ($users->count() > 1) {
            throw new AuthorizationException('The validation account state is duplicated.');
        }

        return $users->first();
    }

    private function assertExistingMember(User $user): void
    {
        $this->assertAccount($user);
        $this->assertPasswordHash($user);
        $this->assertOwnership($user, 'member');

        if ($this->activeClaims('authorization_role_assignments', 'role', (string) $user->getKey()) !== []
            || $this->activeClaims('authorization_permission_assignments', 'permission', (string) $user->getKey()) !== []) {
            throw new AuthorizationException('The validation Member grants are inconsistent.');
        }
    }

    private function assertExistingOperator(User $user, string $secret): void
    {
        $this->assertAccount($user);
        $this->assertPasswordHash($user);
        $this->assertOwnership($user, 'operator');

        if (! Hash::check($secret, (string) $user->password)) {
            throw new AuthorizationException('The validation Operator secret is invalid.');
        }
        if ($this->activeClaims('authorization_role_assignments', 'role', (string) $user->getKey()) !== [self::OPERATOR_ROLE]
            || $this->activeClaims('authorization_permission_assignments', 'permission', (string) $user->getKey()) !== self::OPERATOR_PERMISSIONS) {
            throw new AuthorizationException('The validation Operator grants are inconsistent.');
        }
    }

    private function assertAccount(User $user): void
    {
        if ($user->account_status !== 'active' || ! $user->login_enabled || $user->must_change_password) {
            throw new AuthorizationException('The validation account authentication state is inconsistent.');
        }
    }

    private function assertPasswordHash(User $user): void
    {
        $hash = (string) $user->getRawOriginal('password');
        if ($hash === '' || ! is_string(Hash::info($hash)['algo'] ?? null)) {
            throw new AuthorizationException('The validation account credential state is inconsistent.');
        }
    }

    /** @return list<string> */
    private function activeClaims(string $table, string $column, string $userId): array
    {
        return DB::table($table)
            ->where('user_id', $userId)
            ->where('active', true)
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    private function assertOwnership(User $user, string $principalType): void
    {
        $events = DB::table('audit_events')
            ->where('target_type', User::class)
            ->where('target_id', (string) $user->getKey())
            ->where('action', 'production.validation-context.'.$principalType.'-account.provisioned')
            ->where('outcome', 'success')
            ->get();
        if ($events->count() !== 1) {
            throw new AuthorizationException('Validation principal ownership cannot be proven.');
        }

        $metadata = json_decode((string) $events->first()->metadata, true);
        if ($metadata !== [
            'validation_context' => NonclinicalValidationContext::KEY,
            'nonclinical' => true,
            'principal_type' => $principalType,
        ]) {
            throw new AuthorizationException('Validation principal ownership is inconsistent.');
        }
    }

    private function assertNoOwnershipMarkers(): void
    {
        $actions = [
            'production.validation-context.member-account.provisioned',
            'production.validation-context.operator-account.provisioned',
        ];
        if (DB::table('audit_events')->whereIn('action', $actions)->where('outcome', 'success')->exists()) {
            throw new AuthorizationException('Validation principal ownership is inconsistent.');
        }
    }

    private function insertUser(string $id, string $email, string $password, \DateTimeImmutable $now): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'email' => $email,
            'email_verified_at' => null,
            'password' => $password,
            'remember_token' => null,
            'account_status' => 'active',
            'login_enabled' => true,
            'must_change_password' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function provisionedEvent(AuthenticatedContext $context, string $userId, string $principalType, \DateTimeImmutable $now): AuditEvent
    {
        return AuditEvent::fromContext(
            $context,
            'production.validation-context.'.$principalType.'-account.provisioned',
            'validation-account',
            'success',
            $now,
            User::class,
            $userId,
            metadata: [
                'validation_context' => NonclinicalValidationContext::KEY,
                'nonclinical' => true,
                'principal_type' => $principalType,
            ],
        );
    }
}
