<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Doctor\Application\Contracts\RadiologistAccess;
use App\Modules\Operator\Application\Contracts\RadiologistHandoff;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use DomainException;
use Illuminate\Support\Facades\DB;

final class RadiologistHandoffRead implements RadiologistHandoff
{
    public function matches(string $case, string $handoff, string $binding): bool
    {
        return DB::table('operator_radiographer_handoffs as h')->join('operator_radiographer_workspaces as w', 'w.id', '=', 'h.workspace_id')->where('h.id', $handoff)->where('w.booking_id', $case)->where('w.active_handoff', $handoff)->where('w.binding', $binding)->where('h.binding', $binding)->whereColumn('w.cycle', 'h.cycle')->exists();
    }

    public function forStudy(string $study): ?string
    {
        app(RadiologistAccess::class)->authorizeManager();
        $w = DB::table('operator_radiographer_workspaces')->where('study_id', $study)->lockForUpdate()->first();
        if (! $w) {
            return null;
        }
        app(RadiologistAccess::class)->authorizeManager($w->site_id);

        return $w->active_handoff;
    }

    public function protocol(string $workspace, int $generation): ?object
    {
        return app(RadiologistAccess::class)->run($workspace, 'protocol-read', fn ($w) => DB::table('operator_workspace_protocols')->where('study_id', $w->study_id)->orderByDesc('revision')->lockForUpdate()->first(), $generation);
    }

    public function selection(string $handoff): object
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('unavailable');
        }
        $access = app(RadiologistAccess::class);
        $access->authorizeManager();
        $h = DB::table('operator_radiographer_handoffs')->where('id', $handoff)->first();
        $w = $h ? DB::table('operator_radiographer_workspaces')->where('id', $h->workspace_id)->lockForUpdate()->first() : null;
        if (! $w || $w->active_handoff !== $handoff || $h->cycle !== $w->cycle || $w->binding !== $h->binding) {
            throw new DomainException('unavailable');
        }
        $access->authorizeManager($w->site_id);
        $a = DB::table('operator_queue_admissions')->where('id', $w->admission_id)->first();
        $site = DB::table('operator_sites')->where('id', $w->site_id)->lockForUpdate()->first();
        if (! $a || ! $site || ! $site->active || $a->operator_site_id !== $w->site_id || $a->stage !== 'xray') {
            throw new DomainException('unavailable');
        }

        $snapshot = json_decode($h->snapshot, true, flags: JSON_THROW_ON_ERROR);
        app(AuditStore::class)->append(AuditEvent::fromContext(app(AuthenticatedContextProvider::class)->current()->forPurpose('radiologist.workspace'), 'radiologist-workspace.handoff-read', 'operator', 'success', app(Clock::class)->now(), targetType: 'handoff', targetId: $handoff));

        return (object) ['study_id' => $w->study_id, 'booking_id' => $w->booking_id, 'admission_id' => $w->admission_id, 'site_id' => $w->site_id, 'member_schedule_id' => $a->member_schedule_id, 'binding' => $h->binding, 'handoff_id' => $h->id, 'protocol' => DB::table('operator_workspace_protocols')->where('study_id', $w->study_id)->orderByDesc('revision')->lockForUpdate()->first(), 'snapshot' => ['protocol' => $snapshot['protocol'] ?? null, 'actual' => array_intersect_key($snapshot['actual'] ?? [], array_flip(['modality']))]];
    }
}
