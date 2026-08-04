<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;

final readonly class MemberProfileService
{
    public function __construct(
        private MemberContextResolver $members,
        private AuditStore $audit,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
    ) {}

    /** @param array{email: ?string, phone: ?string, current_address: ?string, emergency_contact_name: ?string, emergency_contact_relationship: ?string, emergency_contact_phone: ?string} $attributes */
    public function update(string $userId, array $attributes): int
    {
        return DB::transaction(function () use ($userId, $attributes): int {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            $members = Member::query()->where('user_id', $userId)->lockForUpdate()->get();

            if ($user === null || $members->count() !== 1) {
                throw new MemberIdentityException('Member access is unavailable.');
            }

            /** @var Member $member */
            $member = $members->first();
            $changedFields = [];

            if ($user->email !== $attributes['email']) {
                $user->forceFill(['email' => $attributes['email']])->save();
                $changedFields[] = 'email';
            }

            $memberAttributes = [
                'phone' => $attributes['phone'],
                'current_address' => $attributes['current_address'],
                'emergency_contact_name' => $attributes['emergency_contact_name'],
                'emergency_contact_relationship' => $attributes['emergency_contact_relationship'],
                'emergency_contact_phone' => $attributes['emergency_contact_phone'],
            ];

            foreach ($memberAttributes as $field => $value) {
                if ($member->{$field} !== $value) {
                    $changedFields[] = $field;
                }
            }

            $member->forceFill($memberAttributes)->save();
            $member->refresh();
            $percentage = $this->members->completionPercentage($member);

            $this->audit->append(AuditEvent::fromContext(
                $this->context->current()->forPurpose('member.profile.update'),
                action: 'member.profile-update',
                source: 'member',
                outcome: 'success',
                occurredAt: $this->clock->now(),
                targetType: Member::class,
                targetId: (string) $member->getKey(),
                metadata: [
                    'changed_fields' => array_values(array_unique($changedFields)),
                    'completion_percentage' => $percentage,
                ],
            ));

            return $percentage;
        });
    }
}
