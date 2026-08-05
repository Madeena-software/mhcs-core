<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Filament\Panel;

final readonly class AdminPanelAccessService
{
    public function __construct(
        private AuthorizationClaimResolver $claims,
        private AuthenticatedContextProvider $context,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function canAccess(User $user, Panel|string $panel): bool
    {
        $panelId = $panel instanceof Panel ? $panel->getId() : $panel;
        $permissions = $this->accessPermissions();

        if ($panelId !== 'admin' || $permissions === []) {
            return false;
        }

        if (
            $user->account_status !== 'active'
            || ! ($user->login_enabled ?? false)
            || $user->must_change_password
        ) {
            return false;
        }

        return in_array('administrator', $this->claims->roles($user), true)
            && array_intersect($permissions, $this->claims->permissions($user)) !== [];
    }

    public function recordDenied(User $user, string $reason = 'panel_access_denied'): void
    {
        $context = $this->context->current();

        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: 'admin.panel-access',
            source: 'auth',
            outcome: 'rejected',
            occurredAt: $this->clock->now(),
            targetType: User::class,
            targetId: (string) $user->getAuthIdentifier(),
            metadata: ['reason_code' => $reason],
        ));
    }

    /** @return list<string> */
    private function accessPermissions(): array
    {
        $permissions = config('mhcs.admin_panel.access_permissions');

        if (! is_array($permissions) || $permissions === []) {
            return [];
        }

        foreach ($permissions as $permission) {
            if (! is_string($permission) || trim($permission) === '' || trim($permission) !== $permission || str_contains($permission, '*')) {
                return [];
            }
        }

        return array_values(array_unique($permissions));
    }
}
