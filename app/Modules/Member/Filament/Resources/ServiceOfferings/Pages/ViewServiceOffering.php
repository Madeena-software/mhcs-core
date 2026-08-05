<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ServiceOfferings\Pages;

use App\Modules\Member\Filament\Resources\ServiceOfferings\ServiceOfferingResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewServiceOffering extends ViewRecord
{
    protected static string $resource = ServiceOfferingResource::class;
}
