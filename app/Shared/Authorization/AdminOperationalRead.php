<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdminOperationalRead
{
    public function authorize(callable $permission): AuthenticatedContext
    {
        $user = Auth::user();
        $fresh = $user instanceof User ? User::query()->find($user->getAuthIdentifier()) : null;
        if (! $fresh || ! app(AdminPanelAccessService::class)->canAccess($fresh, 'admin')) {
            throw new \RuntimeException('access_unconfirmed');
        }
        $context = $permission();
        if ((string) $context->actorId !== (string) $fresh->getAuthIdentifier()) {
            throw new \RuntimeException('access_unconfirmed');
        }

        return $context;
    }

    public function allowed(callable $permission): bool
    {
        try {
            $this->authorize($permission);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function read(callable $permission, callable $query, string $action, string $source, string $section, ?string $target = null, array $metadata = []): array
    {
        $empty = ['state' => 'denied', 'values' => null, 'observed_at' => null];
        try {
            $context = $this->authorize($permission);
        } catch (Throwable) {
            try {
                $context = app(AuthenticatedContextProvider::class)->current();
                if ($context->actorId !== null) {
                    app(AuditStore::class)->append(AuditEvent::fromContext($context, $action, $source, 'rejected', app(Clock::class)->now(), $action === 'member.admin-schedule-capacity.read' ? 'schedule-directory' : 'facility-session-selection', reason: 'access_unconfirmed', metadata: ['request_kind' => $metadata['request_kind'] ?? 'initial', 'section_states' => [$section => 'denied']]));
                }
            } catch (Throwable) {
            }

            return $empty;
        }
        if (DB::transactionLevel() !== 0) {
            return [...$empty, 'state' => 'unavailable'];
        }
        try {
            $phase = 'source';
            $result = DB::transaction(function () use ($permission, $query, $action, $source, $section, &$target, &$metadata, &$phase) {
                $values = $query();
                if ($action === 'member.admin-session-board.read' && $section === 'schedules' && is_array($values) && isset($values['facility']['reference'], $values['metadata'])) {
                    $target = $values['facility']['reference'];
                    $metadata = $values['metadata'];
                }
                $context = $this->authorize($permission);
                $time = app(Clock::class)->now();
                $phase = 'audit';
                app(AuditStore::class)->append(AuditEvent::fromContext($context, $action, $source, 'success', $time, $action === 'member.admin-schedule-capacity.read' ? 'schedule-directory' : 'facility-session-selection', $target, metadata: [...$metadata, 'section_states' => [$section => 'available']]));

                return ['state' => 'available', 'values' => $values, 'observed_at' => $time->format(DATE_ATOM)];
            });
            $this->authorize($permission);

            return $result;
        } catch (Throwable $exception) {
            try {
                if (DB::transactionLevel() === 0 && DB::connection()->getPdo()->inTransaction()) {
                    DB::connection()->getPdo()->rollBack();
                }
            } catch (Throwable) {
            }
            $state = $this->allowed($permission) ? 'unavailable' : 'denied';
            $reason = $state === 'denied' ? 'access_unconfirmed' : ($phase === 'audit' ? 'audit_unavailable' : (in_array($exception->getMessage(), ['invalid_selection', 'integrity_unavailable'], true) ? $exception->getMessage() : 'source_unavailable'));
            if ($state === 'denied') {
                $target = null;
                $metadata = ['request_kind' => $metadata['request_kind'] ?? 'initial'];
            }
            try {
                app(AuditStore::class)->append(AuditEvent::fromContext($context, $action, $source, $state === 'denied' || $reason === 'invalid_selection' ? 'rejected' : 'failure', app(Clock::class)->now(), $action === 'member.admin-schedule-capacity.read' ? 'schedule-directory' : 'facility-session-selection', $target, reason: $reason, metadata: [...$metadata, 'section_states' => [$section => $state]]));
            } catch (Throwable) {
            }

            return [...$empty, 'state' => $state];
        }
    }
}
