<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\Pages;

use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\OperatorXrayProtocolMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorXrayProtocolMappings extends ListRecords
{
    protected static string $resource = OperatorXrayProtocolMappingResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorXrayProtocolMappingResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return OperatorXrayProtocolMappingResource::canCreate() ? [CreateAction::make()->url(OperatorXrayProtocolMappingResource::getUrl('create'))] : [];
    }
}
