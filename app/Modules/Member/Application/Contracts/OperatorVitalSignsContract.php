<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorVitalSignsContract
{
    /**
     * @param  array{
     *     systolic_bp_value: ?string,
     *     systolic_bp_missing_reason: ?string,
     *     diastolic_bp_value: ?string,
     *     diastolic_bp_missing_reason: ?string,
     *     temperature_value: ?string,
     *     temperature_missing_reason: ?string,
     *     height_value: ?string,
     *     height_missing_reason: ?string,
     *     weight_value: ?string,
     *     weight_missing_reason: ?string,
     *     bmi_missing_reason: ?string
     * }  $data
     * @return array{assessment_id: string, member_id: string, booking_id: string, schedule_id: string}
     */
    public function record(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $memberId,
        string $bookingId,
        string $scheduleId,
        array $data,
        string $occurredAt,
    ): array;
}
