<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface OperatorSiteReferenceSynchronizer
{
    /** @return array{organization_id: string, site_id: string} */
    public function synchronize(
        string $organizationId,
        string $organizationName,
        string $siteId,
        string $code,
        string $name,
        string $timezone,
        bool $active,
        string $sourceVersion,
    ): array;
}
