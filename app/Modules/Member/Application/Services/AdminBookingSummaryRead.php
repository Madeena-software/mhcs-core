<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Enums\BookingStatus;
use App\Shared\Authorization\AdminOperationalRead;
use Illuminate\Support\Facades\DB;

final class AdminBookingSummaryRead
{
    public function read(array $schedules, ?string $facility = null, array $metadata = [], bool $resource = false): array
    {
        return app(AdminOperationalRead::class)->read(function () {
            app(AdminOperationalRead::class)->authorize(fn () => app(MemberAuthorization::class)->scheduleRead());

            return app(MemberAuthorization::class)->bookingRead();
        }, function () use ($schedules, $facility) {
            $ids = array_keys($schedules);
            $verified = DB::table('shift_schedules')->whereIn('id', $ids)->when($facility, fn ($q) => $q->where('examination_site_id', $facility))->pluck('id')->all();
            if (count($verified) !== count($ids)) {
                throw new \RuntimeException('integrity_unavailable');
            }
            $values = [];
            foreach ($schedules as $id => $quota) {
                $values[$id] = ['total' => 0, 'by_status' => array_fill_keys(array_column(BookingStatus::cases(), 'value'), 0), 'reserved' => 0, 'remaining' => $quota];
            }
            $rows = DB::table('bookings')->whereIn('shift_schedule_id', $ids)->select('shift_schedule_id', 'status')->selectRaw('COUNT(*) as aggregate')->groupBy('shift_schedule_id', 'status')->get();
            foreach ($rows as $row) {
                if (! array_key_exists($row->status, $values[$row->shift_schedule_id]['by_status'])) {
                    throw new \RuntimeException('integrity_unavailable');
                }
                $count = (int) $row->aggregate;
                $values[$row->shift_schedule_id]['by_status'][$row->status] = $count;
                $values[$row->shift_schedule_id]['total'] += $count;
                if (in_array($row->status, Mvp03BookingService::capacityStatuses(), true)) {
                    $values[$row->shift_schedule_id]['reserved'] += $count;
                }
            }
            foreach ($values as &$value) {
                $value['remaining'] = max(0, $value['remaining'] - $value['reserved']);
            }

            return $values;
        }, $resource ? 'member.admin-schedule-capacity.read' : 'member.admin-session-board.read', 'member', 'bookings', $facility, $metadata);
    }
}
