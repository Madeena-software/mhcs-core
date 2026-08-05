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
            throw new OperatorException('active_site_denied', 'That site is not authorized for this Operator.');
        }

        $this->assertNoUnresolvedWork((string) $portal['profile']->getKey());
        $previous = session()->get('operator.active_site_id');
        session()->put('operator.active_site_id', (string) $site->getKey());
        session()->forget(['operator.active_schedule_id', 'operator.work_context']);
        $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.active-site.'.($previous === null ? 'select' : 'switch'), 'operator', 'success', $this->clock->now(), OperatorSite::class, (string) $site->getKey(), metadata: ['previous_site_id' => is_string($previous) ? $previous : null, 'operator_site_id' => $site->operator_site_id]));

        return $site;
    }

    private function assertNoUnresolvedWork(string $profileId): void
    {
        if (DB::table('operator_arrivals')->where('operator_profile_id', $profileId)->where('status', 'pending')->exists()) {
            throw new OperatorException('active_site_blocked', 'Site switching is blocked while arrival work is unresolved.');
        }
        if (DB::table('operator_shift_assignments')->where('operator_profile_id', $profileId)->where('status', 'pending')->exists()) {
            throw new OperatorException('active_site_blocked', 'Site switching is blocked while assignment work is unresolved.');
        }
    }
}
