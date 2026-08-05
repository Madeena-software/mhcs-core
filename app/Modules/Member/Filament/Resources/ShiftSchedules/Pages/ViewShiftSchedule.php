<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ShiftSchedules\Pages;

use App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewShiftSchedule extends ViewRecord
{
    protected static string $resource = ShiftScheduleResource::class;
}
