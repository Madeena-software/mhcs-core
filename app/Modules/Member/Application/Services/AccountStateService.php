<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;

final readonly class AccountStateService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function suspend(string $userId, string $reason = ''): void
    {
        $this->transition($userId, 'suspended', $reason);
    }

    public function restore(string $userId, string $reason = ''): void
    {
        $this->transition($userId, 'active', $reason);
    }

    private function transition(string $userId, string $target, string $reason): void
    {
        $context = $this->authorization->administrator('member.account-state');

        DB::transaction(function () use ($userId, $target, $reason, $context): void {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($user === null) {
                throw new MemberIdentityException('The account was not found.');
            }

            $previous = (string) $user->account_status;
            if ($previous === $target) {
                return;
            }

            if (! in_array([$previous, $target], [['active', 'suspended'], ['suspended', 'active']], true)) {
                throw new MemberIdentityException('The account state transition is not allowed.');
            }

            $user->forceFill(['account_status' => $target])->save();
            $now = $this->clock->now();
            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.account-state',
                source: 'member',
                outcome: $target,
                occurredAt: $now,
                targetType: User::class,
                targetId: $userId,
                reason: $reason === '' ? null : $reason,
                previousStateDigest: hash('sha256', $previous),
                newStateDigest: hash('sha256', $target),
                metadata: ['login_access_changed' => true],
            ));
        });
    }
}
