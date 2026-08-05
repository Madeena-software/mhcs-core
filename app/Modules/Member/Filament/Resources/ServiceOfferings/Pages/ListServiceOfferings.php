<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ServiceOfferings\Pages;

use App\Modules\Member\Filament\Resources\ServiceOfferings\ServiceOfferingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListServiceOfferings extends ListRecords
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(ServiceOfferingResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return ServiceOfferingResource::canCreate()
            ? [CreateAction::make()->url(ServiceOfferingResource::getUrl('create'))]
            : [];
    }
}
