<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;

final readonly class OperatorActiveSiteService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
        private OperatorArrivalConfirmationService $confirmations,
    ) {}

    /** @return list<OperatorSite> */
    public function assignedSites(): array
    {
        $portal = $this->authorization->portal();

        return OperatorSite::query()
            ->join('operator_site_assignments', 'operator_site_assignments.operator_site_id', '=', 'operator_sites.id')
            ->where('operator_site_assignments.operator_profile_id', $portal['profile']->getKey())
            ->where('operator_site_assignments.active', true)
            ->where('operator_sites.active', true)
            ->select('operator_sites.*')
            ->orderBy('operator_sites.display_name')
            ->get()
            ->all();
    }

    public function select(string $siteId): OperatorSite
    {
        $portal = $this->authorization->portal();
        $siteId = trim($siteId);
        if (preg_match('/\A[0-9a-f-]{36}\z/i', $siteId) !== 1) {
            $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.active-site.select', 'operator', 'failure', $this->clock->now(), reason: 'active_site_invalid'));
            throw new OperatorException('active_site_invalid', 'An authorized active site is required.');
        }
        $site = DB::transaction(function () use ($siteId, $portal): ?OperatorSite {
            return OperatorSite::query()
                ->join('operator_site_assignments', 'operator_site_assignments.operator_site_id', '=', 'operator_sites.id')
                ->where('operator_sites.id', $siteId)
                ->where('operator_site_assignments.operator_profile_id', $portal['profile']->getKey())
                ->where('operator_site_assignments.active', true)
                ->where('operator_sites.active', true)
                ->select('operator_sites.*')
                ->lockForUpdate()
                ->first();
        });
        if ($site === null) {
            $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.active-site.select', 'operator', 'failure', $this->clock->now(), OperatorSite::class, $siteId, reason: 'active_site_denied'));
            throw new OperatorException('active_site_denied', 'That site is not authorized for this Operator.');
        }

        $previous = session()->get('operator.active_site_id');
        if (is_string($previous) && $previous !== (string) $site->getKey()) {
            try {
                $currentSite = $this->authorization->portalSite($portal);
                if ((string) $currentSite->getKey() !== $previous) {
                    throw new OperatorException('active_site_required', 'Select an authorized active site before continuing.');
                }
                $this->assertNoUnresolvedWork((string) $portal['profile']->getKey(), (string) $currentSite->getKey());
            } catch (OperatorException $exception) {
                $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.active-site.switch', 'operator', 'failure', $this->clock->now(), OperatorSite::class, (string) $site->getKey(), reason: $exception->category, metadata: ['previous_site_id' => $previous, 'operator_site_id' => $site->operator_site_id]));
                throw $exception;
            }
        }
        $this->confirmations->inspect((string) $portal['profile']->getKey(), (string) $site->getKey());
        session()->put('operator.active_site_id', (string) $site->getKey());
        session()->forget(['operator.active_schedule_id', 'operator.work_context']);
        $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.active-site.'.($previous === null ? 'select' : 'switch'), 'operator', 'success', $this->clock->now(), OperatorSite::class, (string) $site->getKey(), metadata: ['previous_site_id' => is_string($previous) ? $previous : null, 'operator_site_id' => $site->operator_site_id]));

        return $site;
    }

    private function assertNoUnresolvedWork(string $profileId, string $currentSiteId): void
    {
        if ($this->confirmations->inspect($profileId, $currentSiteId)['status'] === 'active') {
            throw new OperatorException('active_site_blocked', 'Site switching is blocked while arrival work is unresolved.');
        }
    }
}
