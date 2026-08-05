<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorProfiles\Pages;

use App\Modules\Operator\Application\Services\OperatorProfileService;
use App\Modules\Operator\Filament\Resources\OperatorProfiles\OperatorProfileResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditOperatorProfile extends EditRecord
{
    protected static string $resource = OperatorProfileResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(OperatorProfileService::class)->update($record, $data);
    }
}
