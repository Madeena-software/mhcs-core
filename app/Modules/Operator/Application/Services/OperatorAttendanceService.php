<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Operator\Domain\OperatorException;

final readonly class OperatorAttendanceService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
    ) {}

    /** @return list<array<string, mixed>> */
    public function query(string $scheduleId, string $at): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        if (! $this->assignments->isAssigned((string) $portal['profile']->getKey(), $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('attendance_denied', 'The requested attendance list is unavailable.');
        }

        return $this->memberAttendance->query(
            $portal['context']->forPurpose(OperatorAuthorization::ATTENDANCE_READ),
            $site->operator_site_id,
            $scheduleId,
            $at,
        );
    }
}
