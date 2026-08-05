<?php

declare(strict_types=1);

namespace App\Modules\Operator\Filament\Resources\OperatorSites\Pages;

use App\Modules\Operator\Application\Services\OperatorSiteService;
use App\Modules\Operator\Filament\Resources\OperatorSites\OperatorSiteResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditOperatorSite extends EditRecord
{
    protected static string $resource = OperatorSiteResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(OperatorSiteService::class)->update($record, $data);
    }
}
