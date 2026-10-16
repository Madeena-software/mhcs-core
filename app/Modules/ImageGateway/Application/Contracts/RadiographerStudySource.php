<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

interface RadiographerStudySource
{
    /** Opaque selector; unlocked discovery must be rechecked with locking before use. */
    public function selection(string $reference, bool $lock = true): ?object;

    /** Independently authorizes and audits before returning verified original bytes. */
    public function inspect(string $workspace, int $generation): array;
}
