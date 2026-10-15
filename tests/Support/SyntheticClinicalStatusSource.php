<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use Illuminate\Support\Facades\DB;

/** Synthetic reporting fixture only; never bind this adapter outside isolated tests. */
final class SyntheticClinicalStatusSource implements ClinicalStatusSource
{
    public function readWhileLocked(string $case, string $binding, string $handoff): ?array
    {
        $row = DB::table('synthetic_clinical_status')->where('case_id', $case)->lockForUpdate()->first();
        if (! $row || DB::transactionLevel() < 1) {
            return null;
        }

        return ['case_id' => $case, 'binding' => $row->binding, 'handoff_id' => $row->handoff_id, 'status' => $row->status,
            'verified' => true, 'current' => true, 'coordinated' => true, 'complete_sources' => true,
            'sources' => ['synthetic-reporting-only'], 'source_reference' => 'SYNTHETIC-NOT-CLINICAL-AUTHORITY',
            'revision' => (string) $row->revision, 'observed_at' => now()->toAtomString()];
    }
}
