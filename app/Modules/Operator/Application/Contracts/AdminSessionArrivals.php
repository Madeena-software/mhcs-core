<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface AdminSessionArrivals
{
    public function authorize(): AuthenticatedContext;

    public function mapping(array $reference): string;

    public function read(string $facility, string $operatorSite, array $sessions, array $metadata): array;
}
