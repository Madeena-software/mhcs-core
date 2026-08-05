<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ServiceOfferings\Pages;

use App\Modules\Member\Application\Services\Mvp03OfferingService;
use App\Modules\Member\Domain\Models\ServiceOffering;
use App\Modules\Member\Filament\Resources\ServiceOfferings\ServiceOfferingResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditServiceOffering extends EditRecord
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(Mvp03OfferingService::class)->update($record, $data);
    }
}
