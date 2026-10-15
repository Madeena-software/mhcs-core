<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Application\Contracts\PendingRadiographerHandoff;
use Illuminate\Support\Facades\DB;

final class PendingRadiographerHandoffRead implements PendingRadiographerHandoff
{
    public function isCurrent(string $handoff, string $binding): bool
    {
        if (DB::transactionLevel() < 1) {
            return false;
        }
        $h = DB::table('operator_radiographer_handoffs')->where('id', $handoff)->first();
        $w = $h ? DB::table('operator_radiographer_workspaces')->where('id', $h->workspace_id)->lockForUpdate()->first() : null;

        return $w && $w->active_handoff === $handoff && $w->cycle === $h->cycle && $w->binding === $binding && $h->binding === $binding;
    }
}
