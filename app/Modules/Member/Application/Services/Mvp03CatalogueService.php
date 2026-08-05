<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Models\ServiceOffering;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Shared\Time\Clock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class Mvp03CatalogueService
{
    public function __construct(private Clock $clock) {}

    public function offerings(): Collection
    {
        return ServiceOffering::query()->where('active', true)->orderBy('name')->get();
    }

    public function offering(string $id): ?ServiceOffering
    {
        return ServiceOffering::query()->whereKey($id)->where('active', true)->first();
    }

    public function schedules(?string $serviceId = null): Collection
    {
        $schedules = ShiftSchedule::query()
            ->with(['site', 'service'])
            ->where('status', 'open')
            ->where('starts_at', '>', $this->clock->now()->format('Y-m-d H:i:s'))
            ->whereHas('site', fn ($query) => $query->where('active', true))
            ->whereHas('service', fn ($query) => $query->where('active', true))
            ->when($serviceId !== null, fn ($query) => $query->where('service_offering_id', $serviceId))
            ->orderBy('starts_at')
            ->get();

        $schedules->each(function (ShiftSchedule $schedule): void {
            $confirmed = DB::table('bookings')
                ->where('shift_schedule_id', $schedule->getKey())
                ->whereIn('status', Mvp03BookingService::capacityStatuses())
                ->count();
            $schedule->setAttribute('confirmed_count', $confirmed);
            $schedule->setAttribute('remaining_capacity', max(0, $schedule->quota - $confirmed));
            $schedule->setAttribute('threshold_reached', $confirmed >= 5 || $schedule->eligible_at !== null);
        });

        return $schedules;
    }
}
