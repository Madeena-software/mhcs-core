<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorEligibleShifts\Pages;

use App\Modules\Operator\Filament\Resources\OperatorEligibleShifts\OperatorEligibleShiftResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOperatorEligibleShift extends ViewRecord
{
    protected static string $resource = OperatorEligibleShiftResource::class;
}
