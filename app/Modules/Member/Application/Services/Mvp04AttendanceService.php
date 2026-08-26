<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorIdentityVerificationContextResolver;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Member\Domain\Enums\BookingStatus;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class Mvp04AttendanceService implements OperatorAttendanceContract
{
    public function __construct(
        private AuditStore $audit,
        private OutboxStore $outbox,
        private ProtectedIdentifierService $identifiers,
        private TrustedOperatorSiteContextResolver $trustedSite,
        private TrustedOperatorIdentityVerificationContextResolver $trustedCase,
        private Clock $clock,
        private MemberContextResolver $members,
    ) {}

    public function isExactNonclinicalValidationMember(string $memberId): bool
    {
        return $this->members->isExactNonclinicalValidationMember($memberId);
    }

    /** @return list<string> */
    public function participatingBookingStatuses(): array
    {
        return Mvp03BookingService::participatingStatuses();
    }

    /** @return list<array<string, mixed>> */
    public function query(AuthenticatedContext $context, string $operatorSiteId, string $scheduleId, string $at): array
    {
        $this->assertOperatorContext($context, $operatorSiteId, 'operator.attendance.read');
        $atUtc = $this->instant($at);
        $site = $this->site($operatorSiteId);
        $schedule = DB::table('shift_schedules')->where('id', $scheduleId)->first();
        if ($schedule === null || (string) $schedule->examination_site_id !== (string) $site->id) {
            throw new Mvp03Exception('The requested attendance schedule is unavailable.');
        }
        if (! $this->inWindow($atUtc, $schedule->starts_at, $schedule->ends_at, allowBeforeStart: app()->environment(['local', 'testing']))) {
            throw new Mvp03Exception('Attendance time is outside the schedule window.');
        }

        $rows = $this->eligibleBookingQuery($site, $this->participatingBookingStatuses())
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('service_offerings', 'service_offerings.id', '=', 'bookings.service_offering_id')
            ->where('bookings.shift_schedule_id', $scheduleId)
            ->select([
                'bookings.id as booking_id',
                'bookings.shift_schedule_id as schedule_id',
                'bookings.status as booking_status',
                'members.id as member_id',
                'members.name as member_name',
                'members.medical_record_number as medical_record_number',
                'members.encrypted_nik as encrypted_nik',
                'service_offerings.code as service_code',
                'service_offerings.name as service_name',
                'bookings.includes_ai_snapshot as includes_ai',
                'bookings.includes_doctor_snapshot as includes_doctor',
            ])
            ->orderBy('members.name')
            ->get();

        $result = $rows->map(function (object $row) use ($schedule, $site): array {
            $isNonclinical = $row->encrypted_nik === null && $this->isExactNonclinicalValidationMember((string) $row->member_id);
            $nextAction = match ((string) $row->booking_status) {
                BookingStatus::Confirmed->value => 'Record physical arrival',
                BookingStatus::Arrived->value => 'Continue identity verification',
                BookingStatus::CheckedIn->value, BookingStatus::InProgress->value => 'Open basic-examination queue',
                BookingStatus::Completed->value => 'Visit complete',
                default => 'Review booking status',
            };

            return [
                'booking_id' => (string) $row->booking_id,
                'schedule_id' => (string) $row->schedule_id,
                'schedule_display_reference' => (string) $schedule->display_reference,
                'member_id' => (string) $row->member_id,
                'member_name' => (string) $row->member_name,
                'medical_record_number' => (string) $row->medical_record_number,
                'nik' => $row->encrypted_nik === null ? null : $this->identifiers->display((string) $row->encrypted_nik),
                'masked_nik' => $this->maskedIdentifier($row->encrypted_nik),
                'identity_status' => $isNonclinical ? 'nonclinical_validation' : 'verified',
                'site' => (string) $site->display_name,
                'schedule_starts_at' => (string) $schedule->starts_at,
                'schedule_ends_at' => (string) $schedule->ends_at,
                'service_code' => (string) $row->service_code,
                'service_name' => (string) $row->service_name,
                'booking_status' => (string) $row->booking_status,
                'next_action' => $nextAction,
                'arrival_state' => 'pending_arrival',
                'includes_ai' => (bool) $row->includes_ai,
                'includes_doctor' => (bool) $row->includes_doctor,
            ];
        })->all();

        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.attendance.access',
            'member',
            'success',
            $this->clock->now(),
            'shift-schedule',
            $scheduleId,
            metadata: ['operator_site_id' => $operatorSiteId, 'at_utc' => $atUtc->format(DATE_ATOM), 'result_count' => count($result)],
        ));

        return $result;
    }

    /** @return array<string, mixed> */
    public function resolveBookingForArrival(AuthenticatedContext $context, string $operatorSiteId, string $bookingId, string $occurrenceAt): array
    {
        $this->assertOperatorContext($context, $operatorSiteId, 'operator.arrival.record');
        $occurrence = $this->instant($occurrenceAt);
        $site = $this->site($operatorSiteId);
        $row = $this->eligibleBookingQuery($site)
            ->where('bookings.id', $bookingId)
            ->select(['bookings.*', 'shift_schedules.starts_at as schedule_starts_at', 'shift_schedules.ends_at as schedule_ends_at', 'shift_schedules.examination_site_id as schedule_site_id'])
            ->first();
        if ($row === null || (string) $row->schedule_site_id !== (string) $site->id) {
            throw new Mvp03Exception('The requested arrival is unavailable.');
        }
        if ((string) $row->status !== BookingStatus::Confirmed->value) {
            throw new Mvp03Exception('Only a confirmed booking can record arrival.');
        }
        if (! $this->inWindow($occurrence, $row->schedule_starts_at, $row->schedule_ends_at, allowBeforeStart: app()->environment(['local', 'testing']))) {
            throw new Mvp03Exception('Arrival time is outside the schedule window.');
        }

        return $this->arrivalTarget($row, $site, $operatorSiteId, $occurrence);
    }

    /** @return array<string, mixed>|null */
    public function safeArrivalSummary(string $bookingId): ?array
    {
        $row = DB::table('bookings')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
            ->join('service_offerings', 'service_offerings.id', '=', 'bookings.service_offering_id')
            ->where('bookings.id', $bookingId)
            ->select(['bookings.id as booking_id', 'bookings.status as booking_status', 'bookings.shift_schedule_id as schedule_id', 'members.name as member_name', 'members.medical_record_number as medical_record_number', 'shift_schedules.starts_at as schedule_starts_at', 'shift_schedules.ends_at as schedule_ends_at', 'service_offerings.code as service_code', 'service_offerings.name as service_name'])
            ->first();
        if ($row === null) {
            return null;
        }

        return [
            'booking_id' => (string) $row->booking_id,
            'booking_status' => (string) $row->booking_status,
            'schedule_id' => (string) $row->schedule_id,
            'member_name' => (string) $row->member_name,
            'medical_record_number' => (string) $row->medical_record_number,
            'schedule_starts_at' => (string) $row->schedule_starts_at,
            'schedule_ends_at' => (string) $row->schedule_ends_at,
            'service_code' => (string) $row->service_code,
            'service_name' => (string) $row->service_name,
        ];
    }

    /** @return array{booking_id: string, schedule_id: string, status: string} */
    public function transitionConfirmedToArrived(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $bookingId,
        string $occurrenceAt,
        string $recordedAt,
        string $operationId,
    ): array {
        $this->assertOperatorContext($context, $operatorSiteId, 'operator.arrival.record');
        $occurrence = $this->instant($occurrenceAt);
        $recorded = $this->instant($recordedAt, allowMissingOffset: true);
        $site = $this->site($operatorSiteId);
        $booking = $this->eligibleBookingQuery($site)
            ->where('bookings.id', $bookingId)
            ->select(['bookings.*', 'shift_schedules.starts_at as schedule_starts_at', 'shift_schedules.ends_at as schedule_ends_at', 'shift_schedules.examination_site_id as schedule_site_id'])
            ->lockForUpdate()
            ->first();
        if ($booking === null || (string) $booking->schedule_site_id !== (string) $site->id || ! $this->inWindow($occurrence, $booking->schedule_starts_at, $booking->schedule_ends_at, allowBeforeStart: app()->environment(['local', 'testing']))) {
            throw new Mvp03Exception('The booking is no longer eligible for arrival.');
        }
        $target = $this->arrivalTarget($booking, $site, $operatorSiteId, $occurrence);

        DB::table('bookings')->where('id', $bookingId)->update(['status' => BookingStatus::Arrived->value, 'updated_at' => $recorded]);
        DB::table('booking_status_events')->insert([
            'id' => (string) Str::uuid(),
            'booking_id' => $bookingId,
            'source_service' => 'operator',
            'source_operator_id' => $context->actorId === null ? null : (string) $context->actorId,
            'event_type' => BookingStatus::Arrived->value,
            'occurred_at' => $occurrence,
            'received_at' => $recorded,
            'idempotency_key' => 'operator-arrival:'.$operationId,
            'created_at' => $recorded,
            'updated_at' => $recorded,
        ]);
        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.booking.arrived',
            'member',
            'success',
            $occurrence,
            Booking::class,
            $bookingId,
            metadata: ['schedule_id' => $target['schedule_id'], 'operator_site_id' => $operatorSiteId, 'recorded_at_utc' => $recorded->format(DATE_ATOM)],
        ));
        $this->outbox->record(new VersionedDomainEvent(
            LocalId::fromString((string) Str::uuid()),
            'operator.member-arrived',
            1,
            $occurrence,
            ['booking_id' => $bookingId, 'schedule_id' => $target['schedule_id'], 'operator_site_id' => $operatorSiteId, 'occurrence_at' => $occurrence->format(DATE_ATOM), 'recorded_at' => $recorded->format(DATE_ATOM)],
            LocalId::fromString($bookingId),
            $context->operationId,
        ));

        return ['booking_id' => $bookingId, 'schedule_id' => $target['schedule_id'], 'status' => BookingStatus::Arrived->value];
    }

    /** @return array{booking_id: string, schedule_id: string, status: string} */
    public function transitionArrivedToCheckedIn(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $recordedAt,
        string $operationId,
    ): array {
        if ($context->purpose !== 'operator.check-in.issue' || $context->actorId === null || $context->operationId === null) {
            throw new Mvp03Exception('A trusted Operator check-in context is required.');
        }
        $recorded = $this->instant($recordedAt, allowMissingOffset: true);
        $assertion = $this->trustedCase->resolveForCheckIn($context, $operatorSiteId, $scheduleId, $bookingId, $caseId);
        if ($assertion === null) {
            throw new Mvp03Exception('The trusted check-in context is unavailable.');
        }

        $case = DB::table('operator_identity_verifications')
            ->where('id', $caseId)
            ->lockForUpdate()
            ->first();
        $booking = DB::table('bookings')
            ->where('id', $bookingId)
            ->where('shift_schedule_id', $scheduleId)
            ->where('status', BookingStatus::Arrived->value)
            ->lockForUpdate()
            ->first();
        $schedule = $booking === null ? null : DB::table('shift_schedules')->where('id', $scheduleId)->lockForUpdate()->first();
        $site = $booking === null ? null : DB::table('examination_site_refs')
            ->where('id', $booking->examination_site_id_snapshot)
            ->where('operator_site_id', $operatorSiteId)
            ->where('active', true)
            ->lockForUpdate()
            ->first();
        $member = $booking === null ? null : DB::table('members')->where('id', $booking->member_id)->lockForUpdate()->first();
        $consent = $booking === null ? null : DB::table('examination_consents')
            ->where('booking_id', $bookingId)
            ->where('member_id', $booking->member_id)
            ->where('examination_site_id', $booking->examination_site_id_snapshot)
            ->where('operator_site_id', $operatorSiteId)
            ->where('form_name', 'Informed Consent')
            ->where('form_version', 'V1')
            ->where('signer_type', 'member')
            ->where('signature_confirmed', true)
            ->where('status', 'confirmed')
            ->lockForUpdate()
            ->first();

        $isNonclinical = $case?->state === 'nonclinical_validation';
        $validIdentityPath = $isNonclinical
            ? $member !== null && $this->members->isExactNonclinicalValidationMember((string) $member->id) && $consent === null
            : $case?->state === 'matched' && $consent !== null;

        if (
            $case === null
            || ! $validIdentityPath
            || (string) $case->member_schedule_id !== $scheduleId
            || (string) $case->booking_id !== $bookingId
            || (string) $case->operator_profile_id !== $assertion['operator_profile_id']
            || $booking === null
            || (string) $booking->member_id !== $assertion['member_id']
            || $schedule === null
            || (string) $schedule->examination_site_id !== (string) $booking->examination_site_id_snapshot
            || $site === null
            || $member === null
        ) {
            throw new Mvp03Exception('The verified, consent-confirmed booking is unavailable.');
        }

        DB::table('bookings')->where('id', $bookingId)->update([
            'status' => BookingStatus::CheckedIn->value,
            'updated_at' => $recorded,
        ]);
        DB::table('booking_status_events')->insert([
            'id' => (string) Str::uuid(),
            'booking_id' => $bookingId,
            'source_service' => 'member',
            'source_operator_id' => (string) $context->actorId,
            'event_type' => BookingStatus::CheckedIn->value,
            'occurred_at' => $recorded,
            'received_at' => $recorded,
            'idempotency_key' => 'operator-check-in:'.$operationId,
            'created_at' => $recorded,
            'updated_at' => $recorded,
        ]);
        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.booking.checked-in',
            'member',
            'success',
            $recorded,
            Booking::class,
            $bookingId,
            metadata: [
                'case_id' => $caseId,
                'schedule_id' => $scheduleId,
                'examination_site_id' => (string) $site->id,
                'operator_site_id' => $operatorSiteId,
                'operator_id' => $assertion['operator_profile_id'],
                'recorded_at_utc' => $recorded->format(DATE_ATOM),
            ],
        ));
        $this->outbox->record(new VersionedDomainEvent(
            LocalId::fromString((string) Str::uuid()),
            'member.booking-checked-in',
            1,
            $recorded,
            [
                'booking_id' => $bookingId,
                'schedule_id' => $scheduleId,
                'examination_site_id' => (string) $site->id,
                'operator_site_id' => $operatorSiteId,
                'operator_id' => $assertion['operator_profile_id'],
                'case_id' => $caseId,
                'checked_in_at' => $recorded->format(DATE_ATOM),
            ],
            LocalId::fromString($bookingId),
            $context->operationId,
        ));

        return ['booking_id' => $bookingId, 'schedule_id' => $scheduleId, 'status' => BookingStatus::CheckedIn->value];
    }

    private function site(string $operatorSiteId): object
    {
        $site = DB::table('examination_site_refs')->where('operator_site_id', $operatorSiteId)->where('active', true)->first();
        if ($site === null) {
            throw new Mvp03Exception('The Operator site is unavailable.');
        }

        return $site;
    }

    /** @param list<string>|null $statuses */
    private function eligibleBookingQuery(object $site, ?array $statuses = null): Builder
    {
        return DB::table('bookings')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
            ->where('shift_schedules.examination_site_id', $site->id)
            ->where('bookings.examination_site_id_snapshot', $site->id)
            ->whereIn('bookings.status', $statuses ?? [BookingStatus::Confirmed->value])
            ->where('bookings.funding_source', 'personal')
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('point_ledger_entries')
                    ->whereColumn('point_ledger_entries.booking_id', 'bookings.id')
                    ->where('point_ledger_entries.entry_type', 'charge')
                    ->where('point_ledger_entries.point_delta', '<', 0);
            });
    }

    /** @return array<string, mixed> */
    private function arrivalTarget(object $row, object $site, string $operatorSiteId, DateTimeImmutable $occurrence): array
    {
        return [
            'booking_id' => (string) $row->id,
            'schedule_id' => (string) $row->shift_schedule_id,
            'site_id' => (string) $site->id,
            'operator_site_id' => $operatorSiteId,
            'occurrence_at' => $occurrence->format('Y-m-d H:i:s'),
            'schedule_starts_at' => (string) $row->schedule_starts_at,
            'schedule_ends_at' => (string) $row->schedule_ends_at,
        ];
    }

    private function instant(string $value, bool $allowMissingOffset = false): DateTimeImmutable
    {
        $value = trim($value);
        if (! $allowMissingOffset && preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', $value) !== 1) {
            throw new Mvp03Exception('Occurrence time requires an explicit offset.');
        }
        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new Mvp03Exception('Occurrence time is invalid.', previous: $exception);
        }
    }

    private function inWindow(DateTimeImmutable $at, string $startsAt, string $endsAt, bool $allowBeforeStart = false): bool
    {
        $start = new DateTimeImmutable($startsAt, new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($endsAt, new DateTimeZone('UTC'));

        return ($allowBeforeStart || $at >= $start) && $at < $end;
    }

    private function maskedIdentifier(?string $encrypted): ?string
    {
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }
        try {
            $value = $this->identifiers->display($encrypted);
        } catch (Throwable) {
            return null;
        }
        if (strlen($value) < 5) {
            return null;
        }

        return str_repeat('*', strlen($value) - 4).substr($value, -4);
    }

    private function assertOperatorContext(AuthenticatedContext $context, string $operatorSiteId, string $permission): void
    {
        if (
            $context->actorId === null
            || $context->operationId === null
            || ! in_array('operator', $context->roles, true)
            || ! in_array($permission, $context->permissions, true)
            || ! $this->trustedSite->matches($context, $operatorSiteId, $permission)
        ) {
            throw new Mvp03Exception('A trusted Operator attendance context is required.');
        }
    }
}
