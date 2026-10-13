<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface ArrivalReferenceIntegrity
{
    public function validate(string $facility, array $sessionIds, array $tuples, array $metadata): array;
}
