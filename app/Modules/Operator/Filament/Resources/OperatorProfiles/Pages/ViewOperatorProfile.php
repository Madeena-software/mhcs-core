<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorProfiles\Pages;

use App\Modules\Operator\Filament\Resources\OperatorProfiles\OperatorProfileResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOperatorProfile extends ViewRecord
{
    protected static string $resource = OperatorProfileResource::class;
}
