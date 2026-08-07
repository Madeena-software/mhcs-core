<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface TrustedOperatorIdentityVerificationContextResolver
{
    /**
     * @return array{
     *     case_id: string,
     *     arrival_id: string,
     *     booking_id: string,
     *     schedule_id: string,
     *     member_id: string,
     *     operator_site_id: string,
     *     operator_site_local_id: string,
     *     operator_profile_id: string,
     *     prior_photos_revealed: bool,
     * }|null
     */
    public function resolve(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array;

    public function resolveForConsent(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array;

    public function resolveForCheckIn(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array;
}
