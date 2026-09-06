<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface ShiftScheduleClosureHandler
{
    public function onShiftClosed(string $shiftScheduleId): void;
}
