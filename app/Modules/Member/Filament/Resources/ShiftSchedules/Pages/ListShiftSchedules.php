<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ShiftSchedules\Pages;

use App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListShiftSchedules extends ListRecords
{
    protected static string $resource = ShiftScheduleResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(ShiftScheduleResource::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return ShiftScheduleResource::canCreate()
            ? [CreateAction::make()->url(ShiftScheduleResource::getUrl('create'))]
            : [];
    }
}
