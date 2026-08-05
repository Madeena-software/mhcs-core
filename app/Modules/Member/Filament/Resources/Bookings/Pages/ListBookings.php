<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Bookings\Pages;

use App\Modules\Member\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;

final class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(BookingResource::canViewAny(), 403);
    }
}
