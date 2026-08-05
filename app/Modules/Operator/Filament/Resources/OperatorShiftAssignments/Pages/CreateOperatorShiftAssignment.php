<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\Pages;

use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Modules\Operator\Filament\Resources\OperatorShiftAssignments\OperatorShiftAssignmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateOperatorShiftAssignment extends CreateRecord
{
    protected static string $resource = OperatorShiftAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(OperatorShiftAssignmentService::class)->assign((string) $data['operator_eligible_shift_id'], (string) $data['operator_profile_id']);
    }
}
