<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

interface RadiographerStudySource
{
    /** Opaque displayed study selector; no patient or storage data. */
    public function selection(string $reference): ?object;

    /** Independently authorizes and audits before returning verified original bytes. */
    public function inspect(string $workspace, int $generation): array;
}
