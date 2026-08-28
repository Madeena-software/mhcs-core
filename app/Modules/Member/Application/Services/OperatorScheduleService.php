<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorScheduleContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorScheduleService implements OperatorScheduleContract
{
    public function __construct(
        private TrustedOperatorSiteContextResolver $trustedSite,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function schedules(AuthenticatedContext $context, string $operatorSiteId): array
    {
        $site = $this->site($context, $operatorSiteId);
        $schedules = DB::table('shift_schedules as schedules')
            ->join('service_offerings as services', 'services.id', '=', 'schedules.service_offering_id')
            ->where('schedules.examination_site_id', $site->id)
            ->where('services.active', true)
            ->select([
                'schedules.id',
                'schedules.display_reference',
                'schedules.starts_at',
                'schedules.ends_at',
                'schedules.quota',
                'schedules.status',
                'services.id as service_id',
                'services.code as service_code',
                'services.name as service_name',
            ])
            ->selectSub(
                DB::table('bookings')
                    ->selectRaw('count(*)')
                    ->whereColumn('bookings.shift_schedule_id', 'schedules.id')
                    ->whereIn('bookings.status', Mvp03BookingService::capacityStatuses()),
                'participant_count',
            )
            ->orderByDesc('schedules.starts_at')
            ->get()
            ->map(fn (object $schedule): array => $this->scheduleResult($schedule))
            ->all();

        return ['site' => $this->siteResult($site), 'schedules' => $schedules];
    }

    public function createForm(AuthenticatedContext $context, string $operatorSiteId): array
    {
        $site = $this->site($context, $operatorSiteId);
        $services = DB::table('service_offerings')
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'point_price', 'includes_ai', 'includes_doctor'])
            ->map(static fn (object $service): array => [
                'id' => (string) $service->id,
                'code' => (string) $service->code,
                'name' => (string) $service->name,
                'point_price' => (string) $service->point_price,
                'includes_ai' => (bool) $service->includes_ai,
                'includes_doctor' => (bool) $service->includes_doctor,
            ])
            ->all();

        return ['site' => $this->siteResult($site), 'services' => $services];
    }

    public function createSchedule(AuthenticatedContext $context, string $operatorSiteId, array $attributes): array
    {
        $site = $this->site($context, $operatorSiteId);
        $serviceId = $this->uuid($attributes['service_offering_id'] ?? null, 'A service offering is required.');
        $times = $this->times($attributes['starts_at'] ?? null, $attributes['ends_at'] ?? null, (string) $site->timezone);
        $quota = $this->quota($attributes['quota'] ?? null);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($context, $site, $serviceId, $times, $quota): array {
                    $siteRef = DB::table('examination_site_refs')
                        ->where('id', $site->id)
                        ->where('operator_site_id', $site->operator_site_id)
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                    $service = DB::table('service_offerings')
                        ->where('id', $serviceId)
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                    if ($siteRef === null || $service === null) {
                        throw new Mvp03Exception('The selected service is unavailable at the active site.');
                    }
                    if (DB::table('shift_schedules')
                        ->where('examination_site_id', $siteRef->id)
                        ->where('status', 'open')
                        ->where('starts_at', '<', $times['ends_at'])
                        ->where('ends_at', '>', $times['starts_at'])
                        ->exists()) {
                        throw new Mvp03Exception('Active schedules at one site cannot overlap.');
                    }

                    $id = (string) Str::uuid();
                    $displayReference = 'JAD-'.Str::upper(Str::random(8));
                    $now = $this->clock->now();
                    DB::table('shift_schedules')->insert([
                        'id' => $id,
                        'display_reference' => $displayReference,
                        'examination_site_id' => $siteRef->id,
                        'service_offering_id' => $service->id,
                        'starts_at' => $times['starts_at'],
                        'ends_at' => $times['ends_at'],
                        'quota' => $quota,
                        'status' => 'open',
                        'eligible_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.front-desk.schedule.create',
                        'member',
                        'success',
                        $now,
                        'shift-schedule',
                        $id,
                        metadata: [
                            'operator_assisted' => true,
                            'operator_site_id' => $site->operator_site_id,
                            'service_offering_id' => $service->id,
                            'quota' => $quota,
                        ],
                    ));

                    return [
                        'id' => $id,
                        'display_reference' => $displayReference,
                        'site_id' => (string) $siteRef->id,
                        'operator_site_id' => (string) $site->operator_site_id,
                        'service_id' => (string) $service->id,
                        'service_code' => (string) $service->code,
                        'service_name' => (string) $service->name,
                        'starts_at' => $times['starts_at'],
                        'ends_at' => $times['ends_at'],
                        'quota' => $quota,
                        'status' => 'open',
                        'participant_count' => 0,
                    ];
                });
            } catch (QueryException $exception) {
                $message = strtolower($exception->getMessage());
                if ($attempt === 4 || ! str_contains($message, 'display_reference') || (! str_contains($message, 'unique') && ! str_contains($message, 'duplicate'))) {
                    throw $exception;
                }
            }
        }

        throw new Mvp03Exception('A schedule display reference could not be assigned.');
    }

    public function showSchedule(AuthenticatedContext $context, string $operatorSiteId, string $scheduleId, ?string $query = null): array
    {
        $site = $this->site($context, $operatorSiteId);
        $schedule = DB::table('shift_schedules as schedules')
            ->join('service_offerings as services', 'services.id', '=', 'schedules.service_offering_id')
            ->where('schedules.id', trim($scheduleId))
            ->where('schedules.examination_site_id', $site->id)
            ->where('services.active', true)
            ->select([
                'schedules.id',
                'schedules.display_reference',
                'schedules.starts_at',
                'schedules.ends_at',
                'schedules.quota',
                'schedules.status',
                'services.id as service_id',
                'services.code as service_code',
                'services.name as service_name',
            ])
            ->selectSub(
                DB::table('bookings')
                    ->selectRaw('count(*)')
                    ->whereColumn('bookings.shift_schedule_id', 'schedules.id')
                    ->whereIn('bookings.status', Mvp03BookingService::capacityStatuses()),
                'participant_count',
            )
            ->first();
        if ($schedule === null) {
            throw new Mvp03Exception('The requested schedule is unavailable.');
        }

        $participants = DB::table('bookings')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->where('bookings.shift_schedule_id', $schedule->id)
            ->select([
                'members.id as member_id',
                'members.name as member_name',
                'members.medical_record_number',
                'bookings.status as booking_status',
            ])
            ->orderBy('members.name')
            ->get()
            ->map(static fn (object $participant): array => [
                'member_id' => (string) $participant->member_id,
                'member_name' => (string) $participant->member_name,
                'medical_record_number' => (string) $participant->medical_record_number,
                'booking_status' => (string) $participant->booking_status,
            ])
            ->all();

        return [
            'site' => $this->siteResult($site),
            'schedule' => $this->scheduleResult($schedule),
            'participants' => $participants,
            'search' => $query === null ? '' : trim($query),
        ];
    }

    private function site(AuthenticatedContext $context, string $operatorSiteId): object
    {
        $operatorSiteId = trim($operatorSiteId);
        if ($operatorSiteId === '' || ! $this->trustedSite->matches($context, $operatorSiteId, 'operator.shift.manage')) {
            throw new Mvp03Exception('The active Operator site is unavailable.');
        }

        $site = DB::table('examination_site_refs')
            ->where('operator_site_id', $operatorSiteId)
            ->where('active', true)
            ->first();
        if ($site === null) {
            throw new Mvp03Exception('The active examination site is unavailable.');
        }

        return $site;
    }

    /** @return array<string, mixed> */
    private function siteResult(object $site): array
    {
        return [
            'id' => (string) $site->id,
            'operator_site_id' => (string) $site->operator_site_id,
            'code' => (string) $site->code,
            'display_name' => (string) $site->display_name,
            'timezone' => (string) $site->timezone,
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleResult(object $schedule): array
    {
        return [
            'id' => (string) $schedule->id,
            'display_reference' => (string) $schedule->display_reference,
            'service_id' => (string) $schedule->service_id,
            'service_code' => (string) $schedule->service_code,
            'service_name' => (string) $schedule->service_name,
            'starts_at' => (string) $schedule->starts_at,
            'ends_at' => (string) $schedule->ends_at,
            'quota' => (int) $schedule->quota,
            'participant_count' => (int) $schedule->participant_count,
            'status' => (string) $schedule->status,
        ];
    }

    /** @return array{starts_at: string, ends_at: string} */
    private function times(mixed $start, mixed $end, string $timezone): array
    {
        $startsAt = $this->instant($start, $timezone);
        $endsAt = $this->instant($end, $timezone);
        if ($endsAt <= $startsAt) {
            throw new Mvp03Exception('Schedule end must be after its start.');
        }
        if ($endsAt <= $this->clock->now()) {
            throw new Mvp03Exception('Schedule end must be in the future.');
        }

        return [
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
        ];
    }

    private function instant(mixed $value, string $timezone): DateTimeImmutable
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            throw new Mvp03Exception('Schedule start and end are required.');
        }
        try {
            $zone = preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', $value) === 1
                ? new DateTimeZone('UTC')
                : new DateTimeZone($timezone);

            return (new DateTimeImmutable($value, $zone))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new Mvp03Exception('Schedule time is invalid.', previous: $exception);
        }
    }

    private function quota(mixed $value): int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/\A[0-9]+\z/', trim($value)) === 1)) {
            throw new Mvp03Exception('Schedule quota must be an integer from 1 through 500.');
        }
        $quota = (int) $value;
        if ($quota < 1 || $quota > 500) {
            throw new Mvp03Exception('Schedule quota must be an integer from 1 through 500.');
        }

        return $quota;
    }

    private function uuid(mixed $value, string $message): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (! Str::isUuid($value)) {
            throw new Mvp03Exception($message);
        }

        return $value;
    }
}
