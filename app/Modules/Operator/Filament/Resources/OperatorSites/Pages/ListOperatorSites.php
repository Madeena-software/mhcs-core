<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSites\Pages;

use App\Modules\Operator\Filament\Resources\OperatorSites\OperatorSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorSites extends ListRecords
{
    protected static string $resource = OperatorSiteResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorSiteResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return OperatorSiteResource::canCreate() ? [CreateAction::make()->url(OperatorSiteResource::getUrl('create'))] : [];
    }
}
