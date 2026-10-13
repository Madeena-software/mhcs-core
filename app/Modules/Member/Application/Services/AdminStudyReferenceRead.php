<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\AdminStudyReferences;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Operator\Application\Contracts\AdminSessionArrivals;
use App\Modules\Operator\Application\Contracts\AdminStudyIntakes;
use App\Shared\Authorization\AdminOperationalRead;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class AdminStudyReferenceRead implements AdminStudyReferences
{
    public function read(string $facility, string $date, string $session, array $tuples = [], string $kind = 'initial'): array
    {
        return app(AdminOperationalRead::class)->read(function () {
            app(MemberAuthorization::class)->scheduleRead();
            app(MemberAuthorization::class)->bookingRead();

            return app(AdminStudyIntakes::class)->authorize();
        }, function () use ($facility, $date, $session, $tuples, $kind) {
            if (count($tuples) > 25) {
                throw new \RuntimeException('invalid_selection');
            }
            $site = DB::table('examination_site_refs')->where('id', $facility)->first();
            if (! $site || ! in_array($site->timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
                throw new \RuntimeException('invalid_selection');
            }
            $zone = new DateTimeZone($site->timezone);
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
            $calendar = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
            if (! $start || ! $calendar || $start->format('Y-m-d') !== $date) {
                throw new \RuntimeException('invalid_selection');
            }
            $nextDate = $calendar->modify('+1 day')->format('Y-m-d');
            $end = DateTimeImmutable::createFromFormat('!Y-m-d', $nextDate, $zone);
            if (! $end || $end <= $start || $end->format('Y-m-d') !== $nextDate) {
                throw new \RuntimeException('invalid_selection');
            }
            $organization = DB::table('operator_organization_refs')->where('id', $site->operator_organization_ref_id)->first();
            if (! $organization) {
                throw new \RuntimeException('integrity_unavailable');
            }
            $operatorSite = app(AdminSessionArrivals::class)->mapping(['stable' => $site->operator_site_id, 'organization' => $organization->operator_organization_id, 'timezone' => $site->timezone, 'version' => $site->source_version, 'active' => $site->active]);
            $utc = new DateTimeZone('UTC');
            $schedule = ShiftSchedule::query()->with('service:id,name')->where('id', $session)->where('examination_site_id', $facility)->where('starts_at', '>=', $start->setTimezone($utc)->format('Y-m-d H:i:s'))->where('starts_at', '<', $end->setTimezone($utc)->format('Y-m-d H:i:s'))->first();
            if (! $schedule || ! $schedule->service || ! $schedule->display_reference || $schedule->ends_at <= $schedule->starts_at || ! in_array($schedule->status, ['open', 'closed'], true)) {
                throw new \RuntimeException('invalid_selection');
            }
            $bookings = DB::table('bookings')->whereIn('id', array_column($tuples, 'booking_id'))->get(['id', 'member_id', 'shift_schedule_id', 'examination_site_id_snapshot'])->keyBy('id');
            $members = [];
            foreach ($tuples as $tuple) {
                $booking = $bookings->get($tuple['booking_id'] ?? '');
                if (! $booking || $booking->shift_schedule_id !== $session || $booking->examination_site_id_snapshot !== $facility || ($tuple['member_schedule_id'] ?? null) !== $session) {
                    throw new \RuntimeException('integrity_unavailable');
                }
                $members[$booking->id] = $booking->member_id;
            }

            return ['facility' => ['reference' => $site->id, 'label' => $site->display_name, 'timezone' => $site->timezone, 'active' => (bool) $site->active], 'session' => ['reference' => $schedule->display_reference, 'service_label' => $schedule->service->name, 'start' => $schedule->starts_at->setTimezone($zone)->format('Y-m-d H:i P'), 'end' => $schedule->ends_at->setTimezone($zone)->format('Y-m-d H:i P')], 'operator_site' => $operatorSite, 'members' => $members, 'metadata' => ['request_kind' => $kind, 'operational_date' => $date, 'timezone' => $site->timezone, 'session_reference' => $schedule->display_reference]];
        }, 'member.admin-study-references.read', 'member', 'study_links', metadata: ['request_kind' => $kind]);
    }
}
