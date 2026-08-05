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
            ->where('operator_arrivals.operator_site_id', $site->getKey())
            ->where('operator_arrivals.status', 'recorded')
            ->select(['operator_arrivals.*', 'operator_profiles.display_name as operator_name'])
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
            ];
        })->all();
    }
}
