<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use Illuminate\Support\Facades\DB;

final readonly class OperatorWorklistService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorAttendanceContract $memberAttendance,
    ) {}

    /** @return list<array<string, mixed>> */
    public function current(): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $rows = DB::table('operator_arrivals')
            ->join('operator_profiles', 'operator_profiles.id', '=', 'operator_arrivals.operator_profile_id')
            ->leftJoin('operator_identity_verifications', 'operator_identity_verifications.arrival_id', '=', 'operator_arrivals.id')
            ->where('operator_arrivals.operator_site_id', $site->getKey())
            ->where('operator_arrivals.status', 'recorded')
            ->select(['operator_arrivals.*', 'operator_profiles.display_name as operator_name'])
            ->addSelect(['operator_identity_verifications.id as verification_case_id', 'operator_identity_verifications.state as verification_state', 'operator_identity_verifications.operator_profile_id as verification_operator_profile_id'])
            ->orderByDesc('operator_arrivals.occurrence_at')
            ->get();

        return $rows->map(function (object $row): array {
            $member = $this->memberAttendance->safeArrivalSummary((string) $row->booking_id) ?? [];

            return [
                'arrival_id' => (string) $row->id,
                'booking_id' => (string) $row->booking_id,
                'member_name' => $member['member_name'] ?? 'Member tidak tersedia',
                'medical_record_number' => $member['medical_record_number'] ?? null,
                'operator_name' => $row->operator_name ?: 'Operator',
                'occurrence_at' => (string) $row->occurrence_at,
                'recorded_at' => (string) $row->recorded_at,
                'status' => 'pending_verification',
                'verification_case_id' => $row->verification_case_id === null ? null : (string) $row->verification_case_id,
                'verification_state' => $row->verification_state === null ? 'unclaimed' : (string) $row->verification_state,
                'verification_operator_profile_id' => $row->verification_operator_profile_id === null ? null : (string) $row->verification_operator_profile_id,
            ];
        })->all();
    }

    /** @return list<array<string, string>> */
    public function basicExamination(): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();

        return DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('operator_sites as sites', 'sites.id', '=', 'admissions.operator_site_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('admissions.operator_site_id', $site->getKey())
            ->where('member_sites.operator_site_id', $site->operator_site_id)
            ->where('admissions.queue_class', 'advance')
            ->where('admissions.stage', 'basic_examination')
            ->where('admissions.state', 'waiting')
            ->whereExists(function ($query) use ($profileId): void {
                $query->selectRaw('1')
                    ->from('operator_shift_assignments as assignments')
                    ->join('operator_eligible_shifts as eligible', 'eligible.id', '=', 'assignments.operator_eligible_shift_id')
                    ->whereColumn('eligible.member_schedule_id', 'admissions.member_schedule_id')
                    ->whereColumn('eligible.operator_site_id', 'sites.operator_site_id')
                    ->where('assignments.operator_profile_id', $profileId)
                    ->where('assignments.status', 'active')
                    ->where('eligible.sync_status', 'eligible');
            })
            ->select([
                'tickets.ticket_number',
                'sites.display_name as site_name',
                'schedules.starts_at as schedule_starts_at',
                'schedules.ends_at as schedule_ends_at',
                'admissions.stage',
                'admissions.state',
                'admissions.ready_at',
            ])
            ->orderBy('admissions.ready_at')
            ->orderBy('admissions.id')
            ->get()
            ->map(static fn (object $row): array => [
                'ticket_number' => (string) $row->ticket_number,
                'site_name' => (string) $row->site_name,
                'schedule_starts_at' => (string) $row->schedule_starts_at,
                'schedule_ends_at' => (string) $row->schedule_ends_at,
                'stage' => (string) $row->stage,
                'state' => (string) $row->state,
                'ready_at' => (string) $row->ready_at,
            ])->all();
    }
}
