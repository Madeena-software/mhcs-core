<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Shared\Time\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PublicQueueDisplayController extends Controller
{
    public function __construct(private readonly Clock $clock) {}

    public function show(string $site): View
    {
        $this->site($site);

        return view('lcd.queue', ['siteId' => $site]);
    }

    public function queue(string $site): JsonResponse
    {
        $this->site($site);

        return response()->json([
            'current' => $this->called($site),
            'recent_calls' => $this->recentCalls($site),
        ]);
    }

    /** @return list<array{ticket_number: string, destination: string}> */
    private function called(string $site): array
    {
        $schedules = $this->activeScheduleIds($site);
        if ($schedules === []) {
            return [];
        }

        return DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->where('admissions.operator_site_id', $site)
            ->whereIn('admissions.member_schedule_id', $schedules)
            ->where('admissions.state', 'called')
            ->whereIn('admissions.stage', ['basic_examination', 'xray'])
            ->orderBy('admissions.updated_at')
            ->orderBy('admissions.id')
            ->limit(3)
            ->get(['tickets.ticket_number', 'admissions.stage'])
            ->map(fn (object $row): array => [
                'ticket_number' => (string) $row->ticket_number,
                'destination' => $this->destination((string) $row->stage),
            ])
            ->all();
    }

    /** @return list<array{ticket_number: string, destination: string}> */
    private function recentCalls(string $site): array
    {
        $schedules = $this->activeScheduleIds($site);
        if ($schedules === []) {
            return [];
        }

        return DB::table('operator_queue_admission_history as history')
            ->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'history.operator_queue_admission_id')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->where('admissions.operator_site_id', $site)
            ->whereIn('admissions.member_schedule_id', $schedules)
            ->where('history.event_type', 'called')
            ->whereIn('admissions.stage', ['basic_examination', 'xray'])
            ->orderByDesc('history.occurred_at')
            ->orderByDesc('history.id')
            ->limit(5)
            ->get(['tickets.ticket_number', 'admissions.stage'])
            ->map(fn (object $row): array => [
                'ticket_number' => (string) $row->ticket_number,
                'destination' => $this->destination((string) $row->stage),
            ])
            ->all();
    }

    /** @return list<string> */
    private function activeScheduleIds(string $site): array
    {
        $now = $this->clock->now();

        return DB::table('operator_sites')
            ->join('examination_site_refs', 'examination_site_refs.operator_site_id', '=', 'operator_sites.operator_site_id')
            ->join('shift_schedules', 'shift_schedules.examination_site_id', '=', 'examination_site_refs.id')
            ->where('operator_sites.id', $site)
            ->where('operator_sites.active', true)
            ->where('examination_site_refs.active', true)
            ->where('shift_schedules.status', 'open')
            ->where('shift_schedules.starts_at', '<=', $now)
            ->where('shift_schedules.ends_at', '>', $now)
            ->pluck('shift_schedules.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    private function site(string $site): void
    {
        if (! Str::isUuid($site) || ! DB::table('operator_sites')->where('id', $site)->where('active', true)->exists()) {
            abort(404);
        }
    }

    private function destination(string $stage): string
    {
        return $stage === 'xray' ? __('Radiography session') : __('Basic examination');
    }
}
