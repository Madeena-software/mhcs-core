<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\ImageGateway\Application\Contracts\OperatorStudyQuery;
use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Operator\Domain\OperatorException;

final readonly class OperatorAttendanceService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
        private OperatorStudyQuery $studies,
    ) {}

    /** @return list<array<string, mixed>> */
    public function query(string $scheduleId, string $at): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        if (! $this->assignments->isAssigned((string) $portal['profile']->getKey(), $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('attendance_denied', 'The requested attendance list is unavailable.');
        }

        $rows = $this->memberAttendance->query(
            $portal['context']->forPurpose(OperatorAuthorization::ATTENDANCE_READ),
            $site->operator_site_id,
            $scheduleId,
            $at,
        );
        $studiesByBooking = [];
        foreach ($this->studies->studies(
            $portal['context']->forPurpose('image-gateway.study.read'),
            (string) $portal['profile']->getKey(),
            (string) $site->getKey(),
            $site->operator_site_id,
        ) as $study) {
            $studiesByBooking[$study['booking_id']][] = $study['study_id'];
        }

        foreach ($rows as &$row) {
            $studyIds = $studiesByBooking[$row['booking_id']] ?? [];
            $row['returned_study_count'] = count($studyIds);
            $row['returned_study_id'] = count($studyIds) === 1 ? $studyIds[0] : null;
            if (count($studyIds) === 1) {
                $row['next_action'] = 'Open DICOM study';
            } elseif (count($studyIds) > 1) {
                $row['next_action'] = 'DICOM results worklist';
            }
        }
        unset($row);

        return $rows;
    }
}
