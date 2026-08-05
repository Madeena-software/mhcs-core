<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\Pages;

use App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\OperatorSiteAssignmentResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOperatorSiteAssignment extends ViewRecord
{
    protected static string $resource = OperatorSiteAssignmentResource::class;
}
