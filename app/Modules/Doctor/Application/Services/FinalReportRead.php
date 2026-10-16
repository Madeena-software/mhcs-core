<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Services;

use App\Models\User;
use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use App\Modules\Doctor\Application\Contracts\FinalReport;
use App\Modules\Operator\Application\Contracts\ResultHandoff;
use App\Shared\Time\Clock;
use DomainException;
use Illuminate\Support\Facades\DB;

final class FinalReportRead implements FinalReport
{
    public function locate(string $case): array
    {
        $w = DB::table('doctor_workspaces')->where('booking_id', $case)->get();
        if ($w->count() !== 1) {
            throw new DomainException('result_unavailable');
        }
        $w = $w->first();

        return ['case' => $case, 'site' => $w->site_id, 'clinician' => $w->user_id, 'report' => $w->final_id ? DB::table('doctor_finalizations')->where('id', $w->final_id)->value('report_id') : null];
    }

    public function readWhileLocked(string $case, string $report): array
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('result_unavailable');
        }
        $w = DB::table('doctor_workspaces')->where('booking_id', $case)->first();
        if (! $w) {
            throw new DomainException('result_unavailable');
        }
        $subject = app(ResultHandoff::class)->subject($case, $w->handoff_id, $w->binding, $w->site_id, $w->study_id);
        $guard = DB::table('doctor_clinical_guards')->where('case_id', $case)->lockForUpdate()->first();
        $w = DB::table('doctor_workspaces')->where('id', $w->id)->lockForUpdate()->first();
        $f = DB::table('doctor_finalizations')->where('case_id', $case)->lockForUpdate()->first();
        $r = DB::table('doctor_report_versions')->where('id', $report)->lockForUpdate()->first();
        $author = User::query()->whereKey($w->user_id)->first();
        $e = DB::table('doctor_eligibility')->where('user_id', $w->user_id)->where('site_id', $w->site_id)->where('service_id', $w->service_id)->where('modality', $w->modality)->orderByDesc('revision')->lockForUpdate()->first();
        $now = app(Clock::class)->now();
        if (! $guard || $guard->status !== 'finalized' || $w->clinical_status !== 'finalized' || $w->quality !== 'usable' || ! $f || ! $r || ! $author
            || $f->id !== $w->final_id || $f->workspace_id !== $w->id || $r->workspace_id !== $w->id || $r->id !== $w->draft_id || $f->report_id !== $report
            || $r->generation !== $w->generation || $f->revision !== $r->revision || $w->revision !== $r->revision
            || $f->author_id !== $w->user_id || $r->author_id !== $f->author_id || $f->finalizer_id !== $f->author_id
            || $f->handoff_id !== $w->handoff_id || $r->handoff_id !== $w->handoff_id || $f->binding !== $w->binding || $r->binding !== $w->binding
            || $f->digest !== $r->digest || ! hash_equals($r->digest, hash('sha256', json_encode([$r->findings, $r->conclusion, $r->recommendations], JSON_THROW_ON_ERROR)))
            || ! $e || $e->id !== $w->eligibility_id || $e->status !== 'verified' || ! $e->verifier_id || ! $e->evidence_reference
            || new \DateTimeImmutable($e->verified_at) > $now || new \DateTimeImmutable($e->valid_until) <= $now
            || new \DateTimeImmutable($f->finalized_at) > $now
            || ! DB::table('doctor_workspace_history')->where('workspace_id', $w->id)->where('action', 'finalize')->where('actor_id', $f->finalizer_id)->exists()) {
            throw new DomainException('result_unavailable');
        }
        app(ClinicalSourceInventory::class)->assertClosed($case, $w->handoff_id, $w->binding, $report);

        return ['subject' => $subject, 'case' => $case, 'site' => $w->site_id, 'schedule' => $w->member_schedule_id, 'service' => $w->service_id,
            'report' => $r->id, 'finalization' => $f->id, 'digest' => $f->digest, 'binding' => $w->binding, 'handoff' => $w->handoff_id,
            'findings' => $r->findings, 'conclusion' => $r->conclusion, 'recommendations' => $r->recommendations,
            'clinician' => $author->email, 'finalized_at' => $f->finalized_at];
    }
}
