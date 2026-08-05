<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorEligibleShifts\Pages;

use App\Modules\Operator\Filament\Resources\OperatorEligibleShifts\OperatorEligibleShiftResource;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorEligibleShifts extends ListRecords
{
    protected static string $resource = OperatorEligibleShiftResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorEligibleShiftResource::canViewAny(), 403);
    }
}
