<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Operator\Application\Contracts\AdminSessionArrivals;
use App\Shared\Authorization\AdminOperationalRead;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final class AdminSessionBoardRead
{
    public function facilities(string $kind = 'initial'): array
    {
        return app(AdminOperationalRead::class)->read(fn () => app(MemberAuthorization::class)->scheduleRead(), fn () => DB::table('examination_site_refs')->orderBy('display_name')->orderBy('id')->get(['id', 'display_name', 'active'])->map(fn ($s) => ['reference' => $s->id, 'label' => $s->display_name, 'active' => (bool) $s->active])->all(), 'member.admin-session-board.read', 'member', 'schedules', metadata: ['request_kind' => $kind]);
    }

    public function read(string $facility, string $date, int $page, string $kind): array
    {
        return app(AdminOperationalRead::class)->read(fn () => app(MemberAuthorization::class)->scheduleRead(), function () use ($facility, $date, $page, $kind) {
            $site = DB::table('examination_site_refs')->where('id', $facility)->first();
            if (! $site || ! in_array($site->timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
                throw new \RuntimeException('invalid_selection');
            }
            $zone = new DateTimeZone($site->timezone);
            $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
            if (! $start || $start->format('Y-m-d') !== $date) {
                throw new \RuntimeException('invalid_selection');
            }
            $calendar = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
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
            $range = [$start->setTimezone($utc)->format('Y-m-d H:i:s'), $end->setTimezone($utc)->format('Y-m-d H:i:s')];
            $rows = ShiftSchedule::query()->with('service:id,name')->where('examination_site_id', $facility)->where('starts_at', '>=', $range[0])->where('starts_at', '<', $range[1])->orderBy('starts_at')->orderBy('id')->offset((max(1, $page) - 1) * 25)->limit(26)->get();
            $more = $rows->count() > 25;
            $sessions = [];
            foreach ($rows->take(25) as $s) {
                if (! in_array($s->status, ['open', 'closed'], true) || ! $s->service) {
                    throw new \RuntimeException('integrity_unavailable');
                }
                $sessions[$s->id] = ['reference' => $s->display_reference, 'service_label' => $s->service->name, 'start' => $s->starts_at->setTimezone($zone)->format('Y-m-d H:i P'), 'end' => $s->ends_at->setTimezone($zone)->format('Y-m-d H:i P'), 'status' => $s->status, 'quota' => $s->quota];
            }

            return ['facility' => ['reference' => $site->id, 'label' => $site->display_name, 'active' => (bool) $site->active, 'timezone' => $site->timezone], 'sessions' => $sessions, 'operator_site' => $operatorSite, 'range' => $range, 'more' => $more, 'metadata' => ['request_kind' => $kind, 'operational_date' => $date, 'timezone' => $site->timezone]];
        }, 'member.admin-session-board.read', 'member', 'schedules', null, ['request_kind' => $kind]);
    }
}
