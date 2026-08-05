<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ServiceOfferings\Pages;

use App\Modules\Member\Application\Services\Mvp03OfferingService;
use App\Modules\Member\Filament\Resources\ServiceOfferings\ServiceOfferingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateServiceOffering extends CreateRecord
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(Mvp03OfferingService::class)->create($data);
    }
}
