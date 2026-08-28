<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAssistedBookingContract;
use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Contracts\OperatorMemberRegistrationContract;
use App\Modules\Member\Application\Contracts\OperatorScheduleContract;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;

final readonly class OperatorFrontDeskService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorScheduleContract $schedules,
        private OperatorMemberRegistrationContract $members,
        private OperatorAssistedBookingContract $bookings,
        private OperatorAttendanceContract $attendance,
        private EligibleShiftIntakeService $eligibility,
        private OperatorShiftAssignmentService $assignments,
        private IdempotencyStore $idempotency,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function schedules(): array
    {
        [$context, $site] = $this->frontDeskSite();

        return $this->schedules->schedules($context, (string) $site->operator_site_id);
    }

    /** @return array<string, mixed> */
    public function scheduleForm(): array
    {
        [$context, $site] = $this->frontDeskSite();

        return $this->schedules->createForm($context, (string) $site->operator_site_id);
    }

    public function assertActiveSite(): void
    {
        $this->frontDeskSite();
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function createSchedule(array $attributes): array
    {
        [$context, $site] = $this->frontDeskSite();

        return $this->schedules->createSchedule($context, (string) $site->operator_site_id, $attributes);
    }

    /** @return array<string, mixed> */
    public function schedule(string $scheduleId, ?string $query = null): array
    {
        [$context, $site] = $this->frontDeskSite();

        $result = $this->schedules->showSchedule($context, (string) $site->operator_site_id, $scheduleId, $query);
        $result['member_results'] = $query === null || trim($query) === ''
            ? []
            : $this->members->searchMembers($context->forPurpose('operator.front-desk.member-search'), (string) $site->operator_site_id, $query);

        return $result;
    }

    /** @return array<string, mixed> */
    public function registerMember(string $name, ?string $email, ?string $phone, string $operationId): array
    {
        [$context, $site] = $this->frontDeskSite();

        return $this->members->registerWalkIn(
            $context->forPurpose('operator.front-desk.registration'),
            (string) $site->operator_site_id,
            $name,
            $email,
            $phone,
            $operationId,
        );
    }

    /** @return list<array<string, mixed>> */
    public function searchMembers(string $query): array
    {
        [$context, $site] = $this->frontDeskSite();

        return $this->members->searchMembers(
            $context->forPurpose('operator.front-desk.member-search'),
            (string) $site->operator_site_id,
            $query,
        );
    }

    /** @return array<string, mixed> */
    public function bookMember(string $scheduleId, string $memberId, string $operationId): array
    {
        [$context, $site] = $this->frontDeskSite();
        $operatorSiteId = (string) $site->operator_site_id;
        $payload = [
            'operator_site_id' => $operatorSiteId,
            'schedule_id' => trim($scheduleId),
            'member_id' => trim($memberId),
        ];

        $outcome = $this->idempotency->run($operationId, 'operator.front-desk.booking', $payload, function () use ($context, $operatorSiteId, $scheduleId, $memberId, $operationId): array {
            $booking = $this->bookings->bookForOperator(
                $context->forPurpose('operator.front-desk.booking'),
                $operatorSiteId,
                $scheduleId,
                $memberId,
                $operationId,
            );
            $eligibility = $this->activateEligibility($operatorSiteId, $scheduleId);

            return [
                ...$booking,
                'eligible_shift_id' => $eligibility['eligible_shift_id'],
                'confirmed_count' => $eligibility['confirmed_count'],
                'assigned_operator_count' => $eligibility['assigned_operator_count'],
            ];
        });
        $result = is_array($outcome->result) ? $outcome->result : [];
        $result['replayed'] = $outcome->status === 'replayed';

        return $result;
    }

    /** @return array{0: AuthenticatedContext, 1: object} */
    private function frontDeskSite(): array
    {
        $portal = $this->authorization->frontDesk();
        $site = $this->authorization->portalSite($portal);

        return [$portal['context'], $site];
    }

    /** @return array{eligible_shift_id: string, confirmed_count: int, assigned_operator_count: int} */
    private function activateEligibility(string $operatorSiteId, string $scheduleId): array
    {
        return DB::transaction(function () use ($operatorSiteId, $scheduleId): array {
            $schedule = DB::table('shift_schedules')
                ->where('id', trim($scheduleId))
                ->lockForUpdate()
                ->first();
            if ($schedule === null) {
                throw new OperatorException('front_desk_schedule_unavailable', 'The selected schedule is unavailable.');
            }

            $confirmedCount = DB::table('bookings')
                ->where('shift_schedule_id', $scheduleId)
                ->whereIn('status', $this->attendance->participatingBookingStatuses())
                ->count();
            if ($confirmedCount < 1) {
                throw new OperatorException('front_desk_eligibility_unavailable', 'The assisted booking did not produce a participating Member.');
            }

            $existing = DB::table('operator_eligible_shifts')
                ->where('member_schedule_id', $scheduleId)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if (
                    $existing->operator_site_id !== $operatorSiteId
                    || $existing->sync_status !== 'eligible'
                    || (string) $existing->schedule_starts_at !== (string) $schedule->starts_at
                    || (string) $existing->schedule_ends_at !== (string) $schedule->ends_at
                    || (int) $existing->quota !== (int) $schedule->quota
                ) {
                    throw new OperatorException('front_desk_eligibility_conflict', 'The eligible schedule projection is inconsistent.');
                }
                $eligibleShiftId = (string) $existing->id;
            } else {
                $result = $this->eligibility->consume(
                    'operator-front-desk:shift-eligible:'.$scheduleId,
                    [
                        'schedule_id' => (string) $schedule->id,
                        'operator_site_id' => $operatorSiteId,
                        'starts_at' => (string) $schedule->starts_at.'+00:00',
                        'ends_at' => (string) $schedule->ends_at.'+00:00',
                        'confirmed_count' => $confirmedCount,
                        'quota' => (int) $schedule->quota,
                        'event_version' => 1,
                    ],
                );
                $eligibleShiftId = (string) $result['eligible_shift_id'];
            }
            $assigned = $this->assignments->assignAllForFrontDesk($eligibleShiftId, $operatorSiteId);

            return [
                'eligible_shift_id' => $eligibleShiftId,
                'confirmed_count' => $confirmedCount,
                'assigned_operator_count' => $assigned,
            ];
        });
    }
}
