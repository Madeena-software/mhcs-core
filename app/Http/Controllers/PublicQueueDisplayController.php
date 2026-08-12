<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PublicQueueDisplayController extends Controller
{
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
        return DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->where('admissions.operator_site_id', $site)
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
        return DB::table('operator_queue_admission_history as history')
            ->join('operator_queue_admissions as admissions', 'admissions.id', '=', 'history.operator_queue_admission_id')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->where('admissions.operator_site_id', $site)
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
