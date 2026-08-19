<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorAttendanceContract
{
    /** @return list<string> */
    public function participatingBookingStatuses(): array;

    /** @return list<array<string, mixed>> */
    public function query(AuthenticatedContext $context, string $operatorSiteId, string $scheduleId, string $at): array;

    /** @return array<string, mixed> */
    public function resolveBookingForArrival(AuthenticatedContext $context, string $operatorSiteId, string $bookingId, string $occurrenceAt): array;

    /** @return array<string, mixed>|null */
    public function safeArrivalSummary(string $bookingId): ?array;

    /** @return array{booking_id: string, schedule_id: string, status: string} */
    public function transitionConfirmedToArrived(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $bookingId,
        string $occurrenceAt,
        string $recordedAt,
        string $operationId,
    ): array;

    /** @return array{booking_id: string, schedule_id: string, status: string} */
    public function transitionArrivedToCheckedIn(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $recordedAt,
        string $operationId,
    ): array;
}
