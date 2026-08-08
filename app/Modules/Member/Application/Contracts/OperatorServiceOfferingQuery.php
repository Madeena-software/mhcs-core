<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface OperatorServiceOfferingQuery
{
    /** @return list<array{id: string, code: string}> */
    public function active(): array;

    /** @return array{id: string, code: string}|null */
    public function findCurrent(string $serviceOfferingId): ?array;
}
