<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\Pages;

use App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\OperatorShiftAssignmentResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOperatorShiftAssignment extends ViewRecord
{
    protected static string $resource = OperatorShiftAssignmentResource::class;
}
