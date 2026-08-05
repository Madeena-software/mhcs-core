<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Models\ExaminationSiteReference;
use App\Modules\Member\Domain\Models\ServiceOffering;
use App\Modules\Member\Domain\Models\ShiftSchedule;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp03ScheduleService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ShiftSchedule
    {
        $context = $this->authorization->scheduleManage();
        $siteId = $this->id($attributes['examination_site_id'] ?? null);
        $serviceId = $this->id($attributes['service_offering_id'] ?? null);
        $times = $this->times($attributes['starts_at'] ?? null, $attributes['ends_at'] ?? null);
        $quota = $this->quota($attributes['quota'] ?? null);

        return DB::transaction(function () use ($siteId, $serviceId, $times, $quota, $context): ShiftSchedule {
            $site = ExaminationSiteReference::query()->whereKey($siteId)->lockForUpdate()->first();
            $service = ServiceOffering::query()->whereKey($serviceId)->first();
            if ($site === null || ! $site->active || $service === null || ! $service->active) {
                throw new Mvp03Exception('The selected site or service is unavailable.');
            }
            $this->assertFuture($times['starts_at']);
            $this->assertNoOverlap($siteId, $times['starts_at'], $times['ends_at']);

            $id = (string) Str::uuid();
            ShiftSchedule::query()->create([
                'id' => $id,
                'examination_site_id' => $siteId,
                'service_offering_id' => $serviceId,
                'starts_at' => $times['starts_at'],
                'ends_at' => $times['ends_at'],
                'quota' => $quota,
                'status' => 'open',
                'eligible_at' => null,
            ]);
            $schedule = ShiftSchedule::query()->findOrFail($id);
            $this->audit($context, 'member.schedule.create', $id, ['site_id' => $siteId, 'quota' => $quota]);

            return $schedule;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(ShiftSchedule $schedule, array $attributes): ShiftSchedule
    {
        $context = $this->authorization->scheduleManage();
        $siteId = $this->id($attributes['examination_site_id'] ?? $schedule->examination_site_id);
        $serviceId = $this->id($attributes['service_offering_id'] ?? $schedule->service_offering_id);
        $times = $this->times($attributes['starts_at'] ?? (string) $schedule->starts_at, $attributes['ends_at'] ?? (string) $schedule->ends_at);
        $quota = $this->quota($attributes['quota'] ?? $schedule->quota);
        $status = $attributes['status'] ?? $schedule->status;
        if (! is_string($status) || ! in_array($status, ['open', 'closed'], true)) {
            throw new Mvp03Exception('Schedule status is invalid.');
        }

        return DB::transaction(function () use ($schedule, $siteId, $serviceId, $times, $quota, $status, $context): ShiftSchedule {
            $record = ShiftSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->first();
            if ($record === null) {
                throw new Mvp03Exception('Schedule is unavailable.');
            }
            $hasBookings = DB::table('bookings')->where('shift_schedule_id', $record->getKey())->exists();
            if ($hasBookings && ($siteId !== $record->examination_site_id || $serviceId !== $record->service_offering_id)) {
                throw new Mvp03Exception('A booked schedule cannot change its site or service.');
            }
            $site = ExaminationSiteReference::query()->whereKey($siteId)->lockForUpdate()->first();
            $service = ServiceOffering::query()->whereKey($serviceId)->first();
            if ($site === null || ! $site->active || $service === null || ! $service->active) {
                throw new Mvp03Exception('The selected site or service is unavailable.');
            }
            if ($status === 'open') {
                $this->assertFuture($times['starts_at']);
                $this->assertNoOverlap($siteId, $times['starts_at'], $times['ends_at'], (string) $record->getKey());
            }

            $record->forceFill([
                'examination_site_id' => $siteId,
                'service_offering_id' => $serviceId,
                'starts_at' => $times['starts_at'],
                'ends_at' => $times['ends_at'],
                'quota' => $quota,
                'status' => $status,
            ])->save();
            $this->audit($context, 'member.schedule.update', (string) $record->getKey(), ['site_id' => $siteId, 'quota' => $quota, 'status' => $status]);

            return $record->refresh();
        });
    }

    /** @return array{starts_at: string, ends_at: string} */
    private function times(mixed $start, mixed $end): array
    {
        $startsAt = $this->instant($start);
        $endsAt = $this->instant($end);
        if ($endsAt <= $startsAt) {
            throw new Mvp03Exception('Schedule end must be after its start.');
        }

        return ['starts_at' => $startsAt, 'ends_at' => $endsAt];
    }

    private function instant(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', $value) !== 1) {
            throw new Mvp03Exception('Schedule times require an explicit offset.');
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            throw new Mvp03Exception('Schedule time is invalid.', previous: $exception);
        }
    }

    private function assertFuture(string $start): void
    {
        if (new DateTimeImmutable($start, new DateTimeZone('UTC')) <= $this->clock->now()) {
            throw new Mvp03Exception('A new open schedule must be in the future.');
        }
    }

    private function assertNoOverlap(string $siteId, string $start, string $end, ?string $ignore = null): void
    {
        $query = DB::table('shift_schedules')
            ->where('examination_site_id', $siteId)
            ->where('status', 'open')
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
        if ($ignore !== null) {
            $query->where('id', '<>', $ignore);
        }
        if ($query->exists()) {
            throw new Mvp03Exception('Active schedules at one site cannot overlap.');
        }
    }

    private function quota(mixed $value): int
    {
        if ((is_string($value) && preg_match('/\A[0-9]+\z/', trim($value)) !== 1) || (! is_string($value) && ! is_int($value))) {
            throw new Mvp03Exception('Schedule quota must be an integer from 5 through 20.');
        }
        $quota = (int) $value;
        if ($quota < 5 || $quota > 20) {
            throw new Mvp03Exception('Schedule quota must be an integer from 5 through 20.');
        }

        return $quota;
    }

    private function id(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || preg_match('/\A[0-9a-f-]{36}\z/i', $value) !== 1) {
            throw new Mvp03Exception('A persisted schedule reference is required.');
        }

        return $value;
    }

    private function audit(AuthenticatedContext $context, string $action, string $targetId, array $metadata): void
    {
        $this->audit->append(AuditEvent::fromContext($context, $action, 'member', 'success', $this->clock->now(), ShiftSchedule::class, $targetId, metadata: $metadata));
    }
}
