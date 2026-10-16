<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Infrastructure;

use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Modules\Operator\Application\Contracts\RadiologistHandoff;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;

final class WorkspaceClinicalStatusSource implements ClinicalStatusSource
{
    public function readWhileLocked(string $case, string $binding, string $handoff): ?array
    {
        if (DB::transactionLevel() < 1 || ! app(RadiologistHandoff::class)->matches($case, $handoff, $binding)) {
            return null;
        }
        $guard = DB::table('doctor_clinical_guards')->where('case_id', $case)->lockForUpdate()->first();
        $w = DB::table('doctor_workspaces')->where('booking_id', $case)->where('handoff_id', $handoff)->where('binding', $binding)->lockForUpdate()->first();
        if (! $guard || ! $w || ! in_array($w->clinical_status, ['not_finalized', 'finalized'], true)
            || ! DB::table('doctor_workspace_history')->where('workspace_id', $w->id)->where('action', 'issue')->exists()) {
            return null;
        }
        $final = DB::table('doctor_finalizations')->where('case_id', $case)->first();
        if (($w->clinical_status === 'finalized') !== ($final !== null)
            || (! $final && $w->final_id !== null)
            || ($final && ($w->final_id !== $final->id || $final->workspace_id !== $w->id || $final->binding !== $binding || $final->handoff_id !== $handoff))) {
            return null;
        }
        if ($final) {
            $report = DB::table('doctor_report_versions')->where('id', $final->report_id)->first();
            if (! $report || $report->workspace_id !== $w->id || $w->draft_id !== $report->id || $report->author_id !== $final->author_id || $final->finalizer_id !== $report->author_id
                || $report->binding !== $binding || $report->handoff_id !== $handoff || $report->digest !== $final->digest
                || ! hash_equals($final->digest, hash('sha256', json_encode([$report->findings, $report->conclusion, $report->recommendations], JSON_THROW_ON_ERROR)))) {
                return null;
            }
        }

        // Internal consistency cannot establish coverage of external or historical reporting sources.
        return ['case_id' => $case, 'binding' => $binding, 'handoff_id' => $handoff, 'status' => $w->clinical_status,
            'verified' => true, 'current' => true, 'coordinated' => true, 'complete_sources' => false, 'sources' => ['doctor-workspace-v1'],
            'source_reference' => $w->id, 'revision' => $w->revision + 1, 'observed_at' => app(Clock::class)->now()->format(DATE_ATOM)];
    }
}
