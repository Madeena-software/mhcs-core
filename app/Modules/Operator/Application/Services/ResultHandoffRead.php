<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Application\Contracts\ResultHandoff;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ResultHandoffRead implements ResultHandoff
{
    public function subject(string $case, string $handoff, string $binding, string $site, string $study): array
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('result_unavailable');
        }
        $h = DB::table('operator_radiographer_handoffs')->where('id', $handoff)->first();
        $w = $h ? DB::table('operator_radiographer_workspaces')->where('id', $h->workspace_id)->lockForUpdate()->first() : null;
        if (! $w || $w->booking_id !== $case || $w->study_id !== $study || $w->site_id !== $site
            || $w->active_handoff !== $handoff || $w->cycle !== $h->cycle || $w->binding !== $binding || $h->binding !== $binding) {
            throw new DomainException('result_unavailable');
        }
        $snapshot = json_decode($h->snapshot, true, flags: JSON_THROW_ON_ERROR);
        if (($snapshot['case'] ?? null) !== $case || ($snapshot['site'] ?? null) !== $site || ($snapshot['binding'] ?? null) !== $binding) {
            throw new DomainException('result_unavailable');
        }
        $subject = array_intersect_key($snapshot['actual'] ?? [], array_flip(['patientId', 'patientName', 'birthDate', 'sex']));
        if (count($subject) !== 4 || count(array_filter($subject, fn ($v) => is_string($v) && $v !== '')) !== 4) {
            throw new DomainException('result_unavailable');
        }

        return $subject;
    }
}
