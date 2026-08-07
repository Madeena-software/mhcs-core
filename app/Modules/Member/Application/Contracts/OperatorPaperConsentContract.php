<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;
use Illuminate\Http\UploadedFile;

interface OperatorPaperConsentContract
{
    public const PURPOSE = 'operator.paper-consent.confirm';

    /** @return array<string, mixed>|null */
    public function view(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): ?array;

    /** @return array<string, mixed> */
    public function confirm(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $formName,
        string $formVersion,
        string $signerType,
        bool $signatureConfirmed,
        string $signedAt,
        string $idempotencyId,
        ?UploadedFile $scan = null,
    ): array;
}
