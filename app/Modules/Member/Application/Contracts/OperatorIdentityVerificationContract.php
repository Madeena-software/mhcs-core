<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorIdentityVerificationContract
{
    /** @return array<string, mixed> */
    public function lookupByNik(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $nik,
        string $at,
    ): array;

    /** @return array<string, mixed> */
    public function currentView(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        bool $includePrevious = false,
    ): array;

    /** @return list<array<string, mixed>> */
    public function revealPreviousPhotos(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $reason,
    ): array;

    /** @return array{contents: string, format: string} */
    public function retrieveAsset(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $memberId,
        string $assetId,
    ): array;
}
