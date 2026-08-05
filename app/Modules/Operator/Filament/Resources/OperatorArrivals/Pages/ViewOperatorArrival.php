<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorArrivals\Pages;

use App\Modules\Operator\Filament\Resources\OperatorArrivals\OperatorArrivalResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOperatorArrival extends ViewRecord
{
    protected static string $resource = OperatorArrivalResource::class;
}
