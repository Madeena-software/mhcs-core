<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\Pages;

use App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\OperatorShiftAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorShiftAssignments extends ListRecords
{
    protected static string $resource = OperatorShiftAssignmentResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorShiftAssignmentResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return OperatorShiftAssignmentResource::canCreate() ? [CreateAction::make()->url(OperatorShiftAssignmentResource::getUrl('create'))] : [];
    }
}
