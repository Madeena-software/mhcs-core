<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\ArrivalReferenceIntegrity;
use App\Modules\Operator\Application\Contracts\AdminSessionArrivals;
use App\Shared\Authorization\AdminOperationalRead;
use Illuminate\Support\Facades\DB;

final class AdminArrivalReferenceIntegrity implements ArrivalReferenceIntegrity
{
    public function validate(string $facility, array $sessionIds, array $tuples, array $metadata): array
    {
        return app(AdminOperationalRead::class)->read(function () {
            app(AdminOperationalRead::class)->authorize(fn () => app(MemberAuthorization::class)->scheduleRead());

            return app(AdminSessionArrivals::class)->authorize();
        }, function () use ($facility, $sessionIds, $tuples) {
            $valid = DB::table('shift_schedules')->where('examination_site_id', $facility)->whereIn('id', $sessionIds)->pluck('id')->all();
            if (count($valid) !== count($sessionIds)) {
                throw new \RuntimeException('integrity_unavailable');
            }
            $rows = DB::table('bookings')->whereIn('id', array_column($tuples, 'booking_id'))->select('id', 'shift_schedule_id', 'examination_site_id_snapshot')->get()->keyBy('id');
            foreach ($tuples as $tuple) {
                $row = $rows->get($tuple['booking_id']);
                if (! $row || $row->shift_schedule_id !== $tuple['member_schedule_id'] || $row->examination_site_id_snapshot !== $facility || ! in_array($row->shift_schedule_id, $valid, true)) {
                    throw new \RuntimeException('integrity_unavailable');
                }
            }

            return true;
        }, 'member.admin-arrival-references.read', 'member', 'arrival_links', $facility, $metadata);
    }
}
