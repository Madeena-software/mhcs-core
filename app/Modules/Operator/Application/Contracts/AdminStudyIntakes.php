<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface AdminStudyIntakes
{
    public function authorize(): AuthenticatedContext;

    /** Internal owner tuples only; every call resolves persisted scope again. */
    public function read(string $facility, string $date, string $session, int $page, string $kind = 'initial'): array;
}
