<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorArrivals\Pages;

use App\Modules\Operator\Filament\Resources\OperatorArrivals\OperatorArrivalResource;
use Filament\Resources\Pages\ListRecords;

final class ListOperatorArrivals extends ListRecords
{
    protected static string $resource = OperatorArrivalResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(OperatorArrivalResource::canViewAny(), 403);
    }
}
