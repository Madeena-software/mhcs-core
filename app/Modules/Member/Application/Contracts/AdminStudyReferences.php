<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface AdminStudyReferences
{
    /** Internal owner tuples only; never serialize booking/member IDs to a client. */
    public function read(string $facility, string $date, string $session, array $tuples = [], string $kind = 'initial'): array;
}
