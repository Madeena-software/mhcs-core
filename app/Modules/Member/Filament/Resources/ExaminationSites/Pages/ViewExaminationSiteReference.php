<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ExaminationSites\Pages;

use App\Modules\Member\Filament\Resources\ExaminationSites\ExaminationSiteReferenceResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewExaminationSiteReference extends ViewRecord
{
    protected static string $resource = ExaminationSiteReferenceResource::class;
}
