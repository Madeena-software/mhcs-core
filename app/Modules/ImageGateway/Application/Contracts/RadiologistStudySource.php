<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

interface RadiologistStudySource
{
    /** Manager-authorized verified scope; returns no original bytes or patient fields. */
    public function handoffForStudy(string $reference): ?string;

    public function assignment(string $handoff): array;

    public function inspectClinical(string $workspace, int $generation): array;
}
