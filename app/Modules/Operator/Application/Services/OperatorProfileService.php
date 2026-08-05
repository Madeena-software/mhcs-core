<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Models\User;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperatorProfileService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OperatorProfile
    {
        $context = $this->authorization->profileManage();
        $userId = $this->id($attributes['user_id'] ?? null);
        $user = User::query()->whereKey($userId)->first();
        if ($user === null || ! $user->canAuthenticate()) {
            throw new OperatorException('profile_invalid', 'The shared User is unavailable for Operator access.');
        }
        if (OperatorProfile::query()->where('user_id', $userId)->exists()) {
            throw new OperatorException('profile_conflict', 'The User already has an Operator profile.');
        }

        return DB::transaction(function () use ($attributes, $userId, $context): OperatorProfile {
            $id = (string) Str::uuid();
            $now = $this->clock->now();
            $profile = OperatorProfile::query()->create([
                'id' => $id,
                'user_id' => $userId,
                'display_name' => $this->optionalString($attributes['display_name'] ?? null),
                'employee_code' => $this->optionalString($attributes['employee_code'] ?? null),
                'active' => true,
            ]);
            $this->reconcileClaims($userId, (string) $context->actorId, $now);
            $this->audit->append(AuditEvent::fromContext($context, 'operator.profile.create', 'operator', 'success', $now, OperatorProfile::class, $id, metadata: ['user_id' => $userId]));

            return $profile;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(OperatorProfile $profile, array $attributes): OperatorProfile
    {
        $context = $this->authorization->profileManage();

        return DB::transaction(function () use ($profile, $attributes, $context): OperatorProfile {
            $record = OperatorProfile::query()->whereKey($profile->getKey())->lockForUpdate()->first();
            if ($record === null) {
                throw new OperatorException('profile_unavailable', 'The Operator profile is unavailable.');
            }
            $active = array_key_exists('active', $attributes) ? $attributes['active'] : $record->active;
            if (! is_bool($active) && ! in_array($active, [0, 1, '0', '1'], true)) {
                throw new OperatorException('profile_invalid', 'Operator profile status is invalid.');
            }
            $record->forceFill([
                'display_name' => array_key_exists('display_name', $attributes) ? $this->optionalString($attributes['display_name']) : $record->display_name,
                'employee_code' => array_key_exists('employee_code', $attributes) ? $this->optionalString($attributes['employee_code']) : $record->employee_code,
                'active' => (bool) $active,
            ])->save();
            $this->audit->append(AuditEvent::fromContext($context, 'operator.profile.'.($record->active ? 'activate' : 'suspend'), 'operator', 'success', $this->clock->now(), OperatorProfile::class, (string) $record->getKey(), metadata: ['active' => $record->active]));

            return $record->refresh();
        });
    }

    public function setActive(OperatorProfile $profile, bool $active): OperatorProfile
    {
        return $this->update($profile, ['active' => $active]);
    }

    private function id(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || preg_match('/\A[0-9a-f-]{36}\z/i', $value) !== 1) {
            throw new OperatorException('profile_invalid', 'An existing shared User is required.');
        }

        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (! is_string($value) || strlen($value) > 191) {
            throw new OperatorException('profile_invalid', 'Operator profile text is invalid.');
        }

        return trim($value);
    }

    private function reconcileClaims(string $userId, string $assignedByUserId, \DateTimeImmutable $now): void
    {
        $permissions = [
            OperatorAuthorization::PORTAL_ACCESS,
            OperatorAuthorization::SITE_READ,
            OperatorAuthorization::ASSIGNMENT_READ,
            OperatorAuthorization::SHIFT_READ,
            OperatorAuthorization::ATTENDANCE_READ,
            OperatorAuthorization::ARRIVAL_RECORD,
            OperatorAuthorization::AUDIT_READ,
        ];

        DB::table('authorization_role_assignments')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'role' => OperatorAuthorization::ROLE,
            'assigned_by_user_id' => $assignedByUserId,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('authorization_role_assignments')->where('user_id', $userId)->where('role', OperatorAuthorization::ROLE)->update(['active' => true, 'updated_at' => $now]);
        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'permission' => $permission,
                'assigned_by_user_id' => $assignedByUserId,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('authorization_permission_assignments')->where('user_id', $userId)->where('permission', $permission)->update(['active' => true, 'updated_at' => $now]);
        }
    }
}
