<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\Pages;

use App\Modules\Operator\Application\Services\OperatorSiteAssignmentService;
use App\Modules\Operator\Filament\Resources\OperatorSiteAssignments\OperatorSiteAssignmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateOperatorSiteAssignment extends CreateRecord
{
    protected static string $resource = OperatorSiteAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(OperatorSiteAssignmentService::class)->assign((string) $data['operator_profile_id'], (string) $data['operator_site_id']);
    }
}
