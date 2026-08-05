<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ShiftSchedules\Pages;

use App\Modules\Member\Application\Services\Mvp03ScheduleService;
use App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditShiftSchedule extends EditRecord
{
    protected static string $resource = ShiftScheduleResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(Mvp03ScheduleService::class)->update($record, $data);
    }
}
