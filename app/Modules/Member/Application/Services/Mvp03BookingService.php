<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Enums\BookingStatus;
use App\Modules\Member\Domain\Enums\PointEntryType;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp03BookingService
{
    /** @var list<string> */
    private const ACTIVE_STATUSES = [
        'pending_payment',
        'confirmed',
        'arrived',
        'checked_in',
        'in_progress',
        'postponed',
    ];

    public function __construct(
        private MemberContextResolver $members,
        private Mvp03PointService $points,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
    ) {}

    /** @return list<string> */
    public static function capacityStatuses(): array
    {
        return self::ACTIVE_STATUSES;
    }

    /** @return array<string, mixed> */
    public function create(string $memberId, string $scheduleId, ?string $idempotencyKey = null, ?string $pointCostAssertion = null): array
    {
        $idempotencyKey = $idempotencyKey === null || trim($idempotencyKey) === '' ? (string) Str::uuid() : trim($idempotencyKey);
        if (strlen($idempotencyKey) > 191) {
            throw new Mvp03Exception('The booking request identity is invalid.');
        }
        $payload = [
            'member_id' => $memberId,
            'schedule_id' => $scheduleId,
            'point_cost_assertion' => $pointCostAssertion === null ? null : trim($pointCostAssertion),
        ];

        return $this->idempotency->run($idempotencyKey, 'member.booking.create', $payload, function () use ($memberId, $scheduleId, $pointCostAssertion): array {
            return DB::transaction(function () use ($memberId, $scheduleId, $pointCostAssertion): array {
                $member = DB::table('members')->where('id', $memberId)->lockForUpdate()->first();
                if ($member === null) {
                    throw new Mvp03Exception('Member access is unavailable.');
                }
                $user = DB::table('users')->where('id', $member->user_id)->first();
                if ($user === null || $user->account_status !== 'active' || ! $user->login_enabled || $user->must_change_password) {
                    throw new Mvp03Exception('Member booking access is unavailable.');
                }
                $memberModel = Member::query()->findOrFail($memberId);
                if (! $this->members->isEligibleAdult($memberModel) || $member->identity_status !== 'verified' || ! $this->members->isComplete($memberModel)) {
                    throw new Mvp03Exception('Member booking eligibility is incomplete.');
                }

                if (DB::table('bookings')->where('member_id', $memberId)->whereIn('status', self::ACTIVE_STATUSES)->exists()) {
                    throw new Mvp03Exception('A Member can have only one active booking.');
                }

                $schedule = DB::table('shift_schedules')->where('id', $scheduleId)->lockForUpdate()->first();
                if ($schedule === null || $schedule->status !== 'open') {
                    throw new Mvp03Exception('The selected schedule is unavailable.');
                }
                $now = $this->clock->now();
                $startsAt = new DateTimeImmutable((string) $schedule->starts_at, new DateTimeZone('UTC'));
                $endsAt = new DateTimeImmutable((string) $schedule->ends_at, new DateTimeZone('UTC'));
                if ($startsAt <= $now || $endsAt <= $startsAt) {
                    throw new Mvp03Exception('The selected schedule is no longer bookable.');
                }

                $site = DB::table('examination_site_refs')->where('id', $schedule->examination_site_id)->lockForUpdate()->first();
                $service = DB::table('service_offerings')->where('id', $schedule->service_offering_id)->lockForUpdate()->first();
                if ($site === null || ! $site->active || $service === null || ! $service->active) {
                    throw new Mvp03Exception('The selected schedule is no longer available.');
                }
                $confirmed = DB::table('bookings')->where('shift_schedule_id', $scheduleId)->whereIn('status', self::ACTIVE_STATUSES)->count();
                if ($confirmed >= (int) $schedule->quota) {
                    throw new Mvp03Exception('The selected schedule is full.');
                }

                $cost = PointAmount::fromString((string) $service->point_price);
                if ($pointCostAssertion !== null && trim($pointCostAssertion) !== '' && PointAmount::fromString($pointCostAssertion)->compare($cost) !== 0) {
                    throw new Mvp03Exception('The displayed point price is outdated.');
                }
                $rates = DB::table('point_exchange_rates')->where('status', 'active')->lockForUpdate()->get();
                if ($rates->count() !== 1) {
                    throw new Mvp03Exception('The active point rate is unavailable.');
                }
                $rate = $rates->first();
                $balance = $this->points->personalBalance($memberId);
                if ($balance->compare($cost) < 0) {
                    throw new Mvp03Exception('Saldo Madeena Points pribadi tidak mencukupi.');
                }

                $bookingId = (string) Str::uuid();
                $orderId = (string) Str::uuid();
                DB::table('bookings')->insert([
                    'id' => $bookingId,
                    'member_id' => $memberId,
                    'shift_schedule_id' => $scheduleId,
                    'service_offering_id' => $service->id,
                    'examination_site_id_snapshot' => $site->id,
                    'booking_type' => 'b2c',
                    'funding_source' => 'personal',
                    'status' => BookingStatus::Confirmed->value,
                    'service_code_snapshot' => $service->code,
                    'point_cost_snapshot' => (string) $cost,
                    'point_exchange_rate_id' => $rate->id,
                    'includes_ai_snapshot' => (bool) $service->includes_ai,
                    'includes_doctor_snapshot' => (bool) $service->includes_doctor,
                    'site_code_snapshot' => $site->code,
                    'site_name_snapshot' => $site->display_name,
                    'site_timezone_snapshot' => $site->timezone,
                    'created_at' => $now,
                    'confirmed_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('point_ledger_entries')->insert([
                    'id' => (string) Str::uuid(),
                    'member_id' => $memberId,
                    'booking_id' => $bookingId,
                    'funding_source' => 'personal',
                    'entry_type' => PointEntryType::Charge->value,
                    'point_delta' => '-'.(string) $cost,
                    'source_reference' => 'booking:'.$bookingId.':personal-charge',
                    'reverses_id' => null,
                    'created_at' => $now,
                ]);
                DB::table('local_imaging_orders')->insert([
                    'id' => $orderId,
                    'booking_id' => $bookingId,
                    'member_id' => $memberId,
                    'shift_schedule_id' => $scheduleId,
                    'examination_site_id' => $site->id,
                    'service_code_snapshot' => $service->code,
                    'status' => 'authored',
                    'authored_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $afterCount = $confirmed + 1;
                $eligible = $afterCount >= 5 && $schedule->eligible_at === null;
                if ($eligible) {
                    DB::table('shift_schedules')->where('id', $scheduleId)->update(['eligible_at' => $now, 'updated_at' => $now]);
                }

                $context = $this->context->current();
                $this->audit->append(AuditEvent::fromContext($context, 'member.booking.confirmed', 'member', 'success', $now, Booking::class, $bookingId, metadata: ['schedule_id' => $scheduleId, 'site_id' => $site->id, 'service_code' => $service->code, 'booking_type' => 'b2c', 'funding_source' => 'personal']));
                $this->audit->append(AuditEvent::fromContext($context, 'member.point-charge', 'member', 'success', $now, 'point-ledger-entry', $bookingId, metadata: ['booking_id' => $bookingId, 'entry_type' => 'charge', 'point_cost' => (string) $cost, 'funding_source' => 'personal']));
                $this->audit->append(AuditEvent::fromContext($context, 'member.imaging-order.create', 'member', 'success', $now, 'local-imaging-order', $orderId, metadata: ['booking_id' => $bookingId, 'service_code' => $service->code]));

                $this->outbox->record(new VersionedDomainEvent(LocalId::fromString((string) Str::uuid()), 'booking.confirmed', 1, $now, [
                    'booking_id' => $bookingId,
                    'member_id' => $memberId,
                    'schedule_id' => $scheduleId,
                    'site_id' => $site->id,
                    'order_id' => $orderId,
                    'booking_type' => 'b2c',
                    'funding_source' => 'personal',
                ], LocalId::fromString($bookingId), $context->operationId));

                if ($eligible) {
                    $this->audit->append(AuditEvent::fromContext($context, 'member.schedule-eligible', 'member', 'success', $now, 'shift-schedule', $scheduleId, metadata: ['confirmed_count' => $afterCount, 'quota' => (int) $schedule->quota]));
                    $this->outbox->record(new VersionedDomainEvent(LocalId::fromString((string) Str::uuid()), 'shift_eligible', 1, $now, [
                        'schedule_id' => $scheduleId,
                        'site_reference_id' => $site->id,
                        'starts_at' => $schedule->starts_at,
                        'ends_at' => $schedule->ends_at,
                        'confirmed_count' => $afterCount,
                        'quota' => (int) $schedule->quota,
                        'event_version' => 1,
                    ], LocalId::fromString($scheduleId), $context->operationId));
                }

                return [
                    'booking_id' => $bookingId,
                    'order_id' => $orderId,
                    'schedule_id' => $scheduleId,
                    'site_code' => $site->code,
                    'status' => BookingStatus::Confirmed->value,
                    'point_cost' => (string) $cost,
                    'remaining_personal_points' => (string) $balance->subtract($cost),
                ];
            });
        })->result;
    }
}
