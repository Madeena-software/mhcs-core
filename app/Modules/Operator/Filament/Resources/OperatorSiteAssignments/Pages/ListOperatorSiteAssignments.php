<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\Pages;

use App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\OperatorSiteAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorSiteAssignments extends ListRecords
{
    protected static string $resource = OperatorSiteAssignmentResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorSiteAssignmentResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return OperatorSiteAssignmentResource::canCreate() ? [CreateAction::make()->url(OperatorSiteAssignmentResource::getUrl('create'))] : [];
    }
}
