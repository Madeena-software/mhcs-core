<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSites\Pages;

use App\Modules\Operator\Application\Services\OperatorSiteService;
use App\Modules\Operator\Filament\Resources\OperatorSites\OperatorSiteResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateOperatorSite extends CreateRecord
{
    protected static string $resource = OperatorSiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(OperatorSiteService::class)->create($data);
    }
}
