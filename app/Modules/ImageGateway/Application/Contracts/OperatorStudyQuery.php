<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorStudyQuery
{
    /** @return list<array{study_id: string, booking_id: string, format: string, rows: ?int, columns: ?int, accepted_at: string}> */
    public function studies(AuthenticatedContext $context, string $profileId, string $siteId, string $operatorSiteId): array;
}
