<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

interface AdminStudyWorklist
{
    /** Returns only the approved public operational projection. */
    public function read(string $facility, string $date, string $session, int $page, string $kind = 'initial'): array;
}
