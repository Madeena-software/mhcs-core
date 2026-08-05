<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorProfiles\Pages;

use App\Modules\Operator\Application\Services\OperatorProfileService;
use App\Modules\Operator\Filament\Resources\OperatorProfiles\OperatorProfileResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateOperatorProfile extends CreateRecord
{
    protected static string $resource = OperatorProfileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(OperatorProfileService::class)->create($data);
    }
}
