<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\ShiftSchedules\Pages;

use App\Modules\Member\Application\Services\AdminBookingSummaryRead;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Member\Filament\Resources\ShiftSchedules\ShiftScheduleResource;
use App\Shared\Authorization\AdminOperationalRead;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListShiftSchedules extends ListRecords
{
    protected static string $resource = ShiftScheduleResource::class;

    private ?array $capacity = null;

    private string $capacityState = 'unavailable';

    public function capacityFor(ShiftSchedule $record): ?int
    {
        $gate = app(AdminOperationalRead::class);
        if (! $gate->allowed(fn () => app(MemberAuthorization::class)->bookingRead())
            || ! $gate->allowed(fn () => app(MemberAuthorization::class)->scheduleRead())) {
            $this->capacity = null;
            $this->capacityState = 'denied';

            return null;
        }
        if ($this->capacity === null) {
            $records = $this->getTableRecords();
            $quotas = [];
            foreach ($records as $schedule) {
                $quotas[(string) $schedule->getKey()] = $schedule->quota;
            }
            $result = app(AdminBookingSummaryRead::class)->read($quotas, metadata: ['request_kind' => 'resource'], resource: true);
            $this->capacityState = $result['state'];
            $this->capacity = $result['state'] === 'available' ? $result['values'] : [];
        }

        return $this->capacity[$record->getKey()]['reserved'] ?? null;
    }

    public function capacityState(): string
    {
        $gate = app(AdminOperationalRead::class);
        if (! $gate->allowed(fn () => app(MemberAuthorization::class)->bookingRead())
            || ! $gate->allowed(fn () => app(MemberAuthorization::class)->scheduleRead())) {
            $this->capacity = null;

            return $this->capacityState = 'denied';
        }

        return $this->capacityState;
    }

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
