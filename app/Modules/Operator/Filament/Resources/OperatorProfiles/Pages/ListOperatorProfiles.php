<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorProfiles\Pages;

use App\Modules\Operator\Filament\Resources\OperatorProfiles\OperatorProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorProfiles extends ListRecords
{
    protected static string $resource = OperatorProfileResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorProfileResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return OperatorProfileResource::canCreate() ? [CreateAction::make()->url(OperatorProfileResource::getUrl('create'))] : [];
    }
}
