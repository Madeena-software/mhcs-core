<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAssistedBookingContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Member\Domain\Enums\BookingStatus;
use App\Modules\Member\Domain\Funding\FundingSource;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\LocalImagingOrder;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorAssistedBookingService implements OperatorAssistedBookingContract
{
    public function __construct(
        private TrustedOperatorSiteContextResolver $trustedSite,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    public function bookForOperator(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $memberId,
        string $operationId,
    ): array {
        $operatorSiteId = trim($operatorSiteId);
        $scheduleId = trim($scheduleId);
        $memberId = trim($memberId);
        $operationId = trim($operationId);
        $this->assertAccess($context, $operatorSiteId);
        if (! Str::isUuid($scheduleId) || ! Str::isUuid($memberId) || $operationId === '' || strlen($operationId) > 150) {
            throw new Mvp03Exception('The assisted booking request is invalid.');
        }

        $payload = [
            'operator_site_id' => $operatorSiteId,
            'schedule_id' => $scheduleId,
            'member_id' => $memberId,
        ];

        try {
            $outcome = $this->idempotency->run($operationId, 'member.operator-assisted-booking', $payload, function () use ($context, $operatorSiteId, $scheduleId, $memberId, $operationId): array {
                return DB::transaction(function () use ($context, $operatorSiteId, $scheduleId, $memberId, $operationId): array {
                    $member = DB::table('members')->where('id', $memberId)->lockForUpdate()->first();
                    if ($member === null || ! DB::table('users')->where('id', $member->user_id)->exists()) {
                        throw new Mvp03Exception('The selected Member is unavailable.');
                    }
                    if (DB::table('bookings')->where('member_id', $memberId)->where('shift_schedule_id', $scheduleId)->exists()) {
                        throw new Mvp03Exception('This Member is already registered for the selected schedule.');
                    }
                    if (DB::table('bookings')->where('member_id', $memberId)->whereIn('status', Mvp03BookingService::capacityStatuses())->exists()) {
                        throw new Mvp03Exception('This Member already has an active booking.');
                    }

                    $schedule = DB::table('shift_schedules')->where('id', $scheduleId)->where('status', 'open')->lockForUpdate()->first();
                    if ($schedule === null) {
                        throw new Mvp03Exception('The selected schedule is unavailable.');
                    }
                    $now = $this->clock->now();
                    $startsAt = $this->instant((string) $schedule->starts_at);
                    $endsAt = $this->instant((string) $schedule->ends_at);
                    if ($endsAt <= $startsAt || $endsAt <= $now) {
                        throw new Mvp03Exception('The selected schedule has ended.');
                    }

                    $site = DB::table('examination_site_refs')
                        ->where('id', $schedule->examination_site_id)
                        ->where('operator_site_id', $operatorSiteId)
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                    $service = DB::table('service_offerings')
                        ->where('id', $schedule->service_offering_id)
                        ->where('active', true)
                        ->lockForUpdate()
                        ->first();
                    if ($site === null || $service === null) {
                        throw new Mvp03Exception('The selected schedule service or site is unavailable.');
                    }

                    $capacity = DB::table('bookings')
                        ->where('shift_schedule_id', $scheduleId)
                        ->whereIn('status', Mvp03BookingService::capacityStatuses())
                        ->count();
                    if ($capacity >= (int) $schedule->quota) {
                        throw new Mvp03Exception('The selected schedule is full.');
                    }
                    try {
                        $pointCost = PointAmount::fromString((string) $service->point_price);
                    } catch (Throwable $exception) {
                        throw new Mvp03Exception('The selected service price is invalid.', previous: $exception);
                    }
                    $rates = DB::table('point_exchange_rates')->where('status', 'active')->lockForUpdate()->get();
                    if ($rates->count() !== 1) {
                        throw new Mvp03Exception('Exactly one active point exchange rate is required.');
                    }
                    $rate = $rates->first();
                    $bookingId = (string) Str::uuid();
                    $orderId = (string) Str::uuid();
                    $confirmed = BookingStatus::Confirmed->value;

                    DB::table('bookings')->insert([
                        'id' => $bookingId,
                        'member_id' => $memberId,
                        'shift_schedule_id' => $scheduleId,
                        'service_offering_id' => $service->id,
                        'examination_site_id_snapshot' => $site->id,
                        'booking_type' => 'b2b',
                        'funding_source' => FundingSource::BusinessReserved->value,
                        'status' => $confirmed,
                        'service_code_snapshot' => $service->code,
                        'point_cost_snapshot' => (string) $pointCost,
                        'point_exchange_rate_id' => $rate->id,
                        'includes_ai_snapshot' => (bool) $service->includes_ai,
                        'includes_doctor_snapshot' => (bool) $service->includes_doctor,
                        'site_code_snapshot' => $site->code,
                        'site_name_snapshot' => $site->display_name,
                        'site_timezone_snapshot' => $site->timezone,
                        'operator_assisted_hotfix' => true,
                        'created_at' => $now,
                        'confirmed_at' => $now,
                        'updated_at' => $now,
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
                    DB::table('booking_status_events')->insert([
                        'id' => (string) Str::uuid(),
                        'booking_id' => $bookingId,
                        'source_service' => 'operator',
                        'source_operator_id' => $context->actorId === null ? null : (string) $context->actorId,
                        'event_type' => $confirmed,
                        'occurred_at' => $now,
                        'received_at' => $now,
                        'idempotency_key' => 'operator-assisted-booking:'.$operationId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    if ($schedule->eligible_at === null) {
                        DB::table('shift_schedules')->where('id', $scheduleId)->update(['eligible_at' => $now, 'updated_at' => $now]);
                    }

                    $metadata = [
                        'operator_assisted' => true,
                        'operator_assisted_reason' => 'Operator-assisted front-desk booking',
                        'commercial_reconciliation' => 'deferred',
                        'operator_site_id' => $operatorSiteId,
                        'schedule_id' => $scheduleId,
                        'booking_type' => 'b2b',
                        'funding_source' => FundingSource::BusinessReserved->value,
                    ];
                    $this->audit->append(AuditEvent::fromContext($context, 'member.operator-assisted-booking.confirmed', 'member', 'success', $now, Booking::class, $bookingId, metadata: $metadata));
                    $this->audit->append(AuditEvent::fromContext($context, 'member.imaging-order.create', 'member', 'success', $now, LocalImagingOrder::class, $orderId, metadata: ['booking_id' => $bookingId, 'operator_assisted' => true, 'commercial_reconciliation' => 'deferred']));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.booking.confirmed',
                        1,
                        $now,
                        [
                            'booking_id' => $bookingId,
                            'member_id' => $memberId,
                            'schedule_id' => $scheduleId,
                            'operator_site_id' => $operatorSiteId,
                            'site_id' => $site->id,
                            'order_id' => $orderId,
                            'booking_type' => 'b2b',
                            'funding_source' => FundingSource::BusinessReserved->value,
                            'operator_assisted' => true,
                            'commercial_reconciliation' => 'deferred',
                        ],
                        LocalId::fromString($bookingId),
                        $context->operationId,
                    ));

                    return [
                        'booking_id' => $bookingId,
                        'order_id' => $orderId,
                        'schedule_id' => $scheduleId,
                        'member_id' => $memberId,
                        'medical_record_number' => (string) $member->medical_record_number,
                        'site_code' => (string) $site->code,
                        'status' => $confirmed,
                        'booking_type' => 'b2b',
                        'funding_source' => FundingSource::BusinessReserved->value,
                        'point_cost_snapshot' => (string) $pointCost,
                        'operator_assisted' => true,
                        'commercial_reconciliation' => 'deferred',
                    ];
                });
            });
            $result = is_array($outcome->result) ? $outcome->result : [];
            $result['replayed'] = $outcome->status === 'replayed';

            return $result;
        } catch (IdempotencyConflict $exception) {
            throw $exception;
        } catch (Mvp03Exception $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new Mvp03Exception('The assisted booking could not be completed.', previous: $exception);
        } catch (Throwable $exception) {
            throw new Mvp03Exception('The assisted booking could not be completed.', previous: $exception);
        }
    }

    private function assertAccess(AuthenticatedContext $context, string $operatorSiteId): void
    {
        if (
            $context->actorId === null
            || $context->operationId === null
            || ! DB::table('users')
                ->where('id', (string) $context->actorId)
                ->where('account_status', 'active')
                ->where('login_enabled', true)
                ->where('must_change_password', false)
                ->exists()
            || ! $this->trustedSite->matches($context, $operatorSiteId, 'operator.shift.manage')
        ) {
            throw new Mvp03Exception('Trusted Operator front-desk authorization is required.');
        }
    }

    private function instant(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new Mvp03Exception('The selected schedule time is invalid.', previous: $exception);
        }
    }
}
