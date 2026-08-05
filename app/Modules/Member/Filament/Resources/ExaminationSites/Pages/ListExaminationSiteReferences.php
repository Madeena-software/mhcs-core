<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ExaminationSites\Pages;

use App\Modules\Member\Filament\Resources\ExaminationSites\ExaminationSiteReferenceResource;
use Filament\Resources\Pages\ListRecords;

final class ListExaminationSiteReferences extends ListRecords
{
    protected static string $resource = ExaminationSiteReferenceResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(ExaminationSiteReferenceResource::canViewAny(), 403);
    }
}
