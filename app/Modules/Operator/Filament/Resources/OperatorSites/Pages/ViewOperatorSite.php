<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSites\Pages;

use App\Modules\Operator\Filament\Resources\OperatorSites\OperatorSiteResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOperatorSite extends ViewRecord
{
    protected static string $resource = OperatorSiteResource::class;
}
