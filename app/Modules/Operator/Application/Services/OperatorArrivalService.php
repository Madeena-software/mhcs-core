<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Operator\Domain\Models\OperatorArrival;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorArrivalService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @return array{arrival_id: string, booking_id: string, schedule_id: string, status: string} */
    public function record(string $bookingId, string $occurrenceAt, string $idempotencyKey): array
    {
        $portal = null;
        try {
            $portal = $this->authorization->portal();
            $site = $this->authorization->portalSite($portal);
            $operation = trim($idempotencyKey);
            if ($operation === '' || strlen($operation) > 191) {
                throw new OperatorException('arrival_invalid', 'An arrival operation identity is required.');
            }
            $occurrence = $this->instant($occurrenceAt);
            $payload = [
                'booking_id' => trim($bookingId),
                'occurrence_at' => $occurrence->format(DATE_ATOM),
                'operator_profile_id' => (string) $portal['profile']->getKey(),
                'operator_site_id' => (string) $site->getKey(),
            ];

            return $this->idempotency->run($operation, 'operator.arrival.record', $payload, function () use ($portal, $site, $bookingId, $occurrence, $operation): array {
                return DB::transaction(function () use ($portal, $site, $bookingId, $occurrence, $operation): array {
                    $target = $this->memberAttendance->resolveBookingForArrival($site->operator_site_id, trim($bookingId), $occurrence->format(DATE_ATOM));
                    if (! $this->assignments->isAssigned((string) $portal['profile']->getKey(), $target['schedule_id'], $site->operator_site_id)) {
                        throw new OperatorException('arrival_assignment_denied', 'The Operator is not assigned to this schedule.');
                    }
                    $now = $this->clock->now();
                    $arrivalId = (string) Str::uuid();
                    OperatorArrival::query()->create([
                        'id' => $arrivalId,
                        'booking_id' => $target['booking_id'],
                        'member_schedule_id' => $target['schedule_id'],
                        'operator_site_id' => (string) $site->getKey(),
                        'operator_profile_id' => (string) $portal['profile']->getKey(),
                        'occurrence_at' => $occurrence,
                        'recorded_at' => $now,
                        'operation_id' => $operation,
                        'source' => 'operator.portal',
                        'status' => 'recorded',
                    ]);
                    $memberResult = $this->memberAttendance->transitionConfirmedToArrived(
                        $portal['context']->forPurpose(OperatorAuthorization::ARRIVAL_RECORD),
                        $site->operator_site_id,
                        $target['booking_id'],
                        $occurrence->format(DATE_ATOM),
                        $now->format(DATE_ATOM),
                        $operation,
                    );
                    $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.arrival.record', 'operator', 'success', $occurrence, OperatorArrival::class, $arrivalId, metadata: ['booking_id' => $target['booking_id'], 'schedule_id' => $target['schedule_id'], 'operator_site_id' => $site->operator_site_id, 'recorded_at_utc' => $now->format(DATE_ATOM)]));

                    return ['arrival_id' => $arrivalId, 'booking_id' => $memberResult['booking_id'], 'schedule_id' => $memberResult['schedule_id'], 'status' => $memberResult['status']];
                });
            })->result;
        } catch (IdempotencyConflict $exception) {
            $this->failure($portal, 'arrival_conflict');
            throw $exception;
        } catch (OperatorException $exception) {
            $this->failure($portal, $exception->category);
            throw $exception;
        } catch (Throwable $exception) {
            $this->failure($portal, 'arrival_failure');
            throw new OperatorException('arrival_failure', 'The arrival could not be recorded.', $exception);
        }
    }

    /** @return array{confirmation_token: string, summary: array<string, mixed>, occurrence_at: string, schedule_id: string} */
    public function confirm(string $bookingId, string $occurrenceAt): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $occurrence = $this->instant($occurrenceAt);
        $target = $this->memberAttendance->resolveBookingForArrival($site->operator_site_id, trim($bookingId), $occurrence->format(DATE_ATOM));
        if (! $this->assignments->isAssigned((string) $portal['profile']->getKey(), $target['schedule_id'], $site->operator_site_id)) {
            throw new OperatorException('arrival_assignment_denied', 'The Operator is not assigned to this schedule.');
        }

        $summary = $this->memberAttendance->safeArrivalSummary($target['booking_id']);
        if ($summary === null || $summary['schedule_id'] !== $target['schedule_id']) {
            throw new OperatorException('arrival_confirmation_invalid', 'The requested arrival is unavailable.');
        }

        $token = (string) Str::uuid();
        session()->put('operator.arrival_confirmation', [
            'token' => $token,
            'booking_id' => $target['booking_id'],
            'occurrence_at' => $occurrence->format(DATE_ATOM),
            'idempotency_key' => (string) Str::uuid(),
            'operator_profile_id' => (string) $portal['profile']->getKey(),
            'operator_site_id' => (string) $site->getKey(),
            'schedule_id' => $target['schedule_id'],
            'expires_at' => $this->clock->now()->modify('+5 minutes')->format(DATE_ATOM),
        ]);

        return [
            'confirmation_token' => $token,
            'summary' => $summary,
            'occurrence_at' => $occurrence->format(DATE_ATOM),
            'schedule_id' => $target['schedule_id'],
        ];
    }

    /** @return array{arrival_id: string, booking_id: string, schedule_id: string, status: string} */
    public function recordConfirmed(string $confirmationToken): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $state = session()->get('operator.arrival_confirmation');
        if (! is_array($state) || ($state['token'] ?? null) !== trim($confirmationToken)) {
            throw new OperatorException('arrival_confirmation_invalid', 'The arrival confirmation is missing or invalid.');
        }
        if (($state['operator_profile_id'] ?? null) !== (string) $portal['profile']->getKey() || ($state['operator_site_id'] ?? null) !== (string) $site->getKey()) {
            throw new OperatorException('arrival_confirmation_invalid', 'The arrival confirmation is no longer valid for this Operator context.');
        }
        try {
            $expiresValue = $state['expires_at'] ?? null;
            if (! is_string($expiresValue) || trim($expiresValue) === '') {
                throw new OperatorException('arrival_confirmation_invalid', 'The arrival confirmation is invalid.');
            }
            $expiresAt = new DateTimeImmutable($expiresValue);
        } catch (Throwable $exception) {
            if ($exception instanceof OperatorException) {
                throw $exception;
            }
            throw new OperatorException('arrival_confirmation_invalid', 'The arrival confirmation is invalid.', $exception);
        }
        if ($this->clock->now() >= $expiresAt) {
            session()->forget('operator.arrival_confirmation');
            throw new OperatorException('arrival_confirmation_expired', 'The arrival confirmation has expired.');
        }
        if (($state['consumed'] ?? false) === true && is_array($state['result'] ?? null)) {
            return $state['result'];
        }

        $result = $this->record(
            (string) $state['booking_id'],
            (string) $state['occurrence_at'],
            (string) $state['idempotency_key'],
        );
        session()->put('operator.arrival_confirmation', [...$state, 'consumed' => true, 'result' => $result]);

        return $result;
    }

    public function cancelConfirmation(string $confirmationToken): void
    {
        $this->authorization->portal();
        $state = session()->get('operator.arrival_confirmation');
        if (! is_array($state) || ($state['token'] ?? null) !== trim($confirmationToken)) {
            throw new OperatorException('arrival_confirmation_invalid', 'The arrival confirmation is missing or invalid.');
        }

        session()->forget('operator.arrival_confirmation');
    }

    private function instant(string $value): DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', $value) !== 1) {
            throw new OperatorException('arrival_invalid', 'Arrival time requires an explicit offset.');
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new OperatorException('arrival_invalid', 'Arrival time is invalid.', $exception);
        }
    }

    private function failure(?array $portal, string $category): void
    {
        if ($portal === null) {
            return;
        }
        $this->audit->append(AuditEvent::fromContext($portal['context'], 'operator.arrival.failed', 'operator', 'failure', $this->clock->now(), reason: $category));
    }
}
