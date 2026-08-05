<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class EligibleShiftIntakeService
{
    public function __construct(
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $payload @return array{eligible_shift_id: string, replayed?: bool} */
    public function consume(string $sourceEventId, array $payload): array
    {
        $sourceEventId = trim($sourceEventId);
        if ($sourceEventId === '') {
            throw new OperatorException('eligibility_invalid', 'A source event identity is required.');
        }
        $payload['source_event_id'] = $sourceEventId;
        $this->assertPayloadShape($payload);

        try {
            $outcome = $this->idempotency->run($sourceEventId, 'operator.shift-eligible', $payload, function () use ($payload): array {
                return DB::transaction(function () use ($payload): array {
                    $siteId = $this->stableSiteId($payload);
                    $site = DB::table('operator_sites')->where('operator_site_id', $siteId)->where('active', true)->lockForUpdate()->first();
                    if ($site === null) {
                        throw new OperatorException('eligibility_site_unavailable', 'Eligibility for an unknown or inactive site was rejected.');
                    }
                    $start = $this->instant((string) $payload['starts_at']);
                    $end = $this->instant((string) $payload['ends_at']);
                    if ($end <= $start) {
                        throw new OperatorException('eligibility_invalid', 'Eligibility schedule times are invalid.');
                    }
                    $existing = OperatorEligibleShift::query()->where('member_schedule_id', (string) $payload['schedule_id'])->lockForUpdate()->first();
                    if ($existing !== null) {
                        if ($existing->operator_site_id !== $siteId || (int) $existing->event_version > (int) $payload['event_version']) {
                            throw new OperatorException('eligibility_conflict', 'The eligible schedule replay conflicts with its stored projection.');
                        }
                        if (
                            $existing->schedule_starts_at->format('Y-m-d H:i:s') !== $start->format('Y-m-d H:i:s')
                            || $existing->schedule_ends_at->format('Y-m-d H:i:s') !== $end->format('Y-m-d H:i:s')
                            || (int) $existing->quota !== (int) $payload['quota']
                        ) {
                            throw new OperatorException('eligibility_conflict', 'The eligible schedule replay contains changed schedule data.');
                        }

                        return ['eligible_shift_id' => (string) $existing->getKey()];
                    }

                    $id = (string) Str::uuid();
                    $now = $this->clock->now();
                    OperatorEligibleShift::query()->create([
                        'id' => $id,
                        'member_schedule_id' => (string) $payload['schedule_id'],
                        'operator_site_id' => $siteId,
                        'schedule_starts_at' => $start,
                        'schedule_ends_at' => $end,
                        'confirmed_count_at_eligibility' => (int) $payload['confirmed_count'],
                        'quota' => (int) $payload['quota'],
                        'event_version' => (int) $payload['event_version'],
                        'source_event_id' => (string) $payload['source_event_id'],
                        'eligible_at' => $now,
                        'sync_status' => 'eligible',
                    ]);
                    $this->audit->append(AuditEvent::fromContext($this->eventContext(), 'operator.shift-eligible.intake', 'operator', 'success', $now, OperatorEligibleShift::class, $id, metadata: ['schedule_id' => (string) $payload['schedule_id'], 'operator_site_id' => $siteId, 'event_version' => (int) $payload['event_version']]));

                    return ['eligible_shift_id' => $id];
                });
            });

            return $outcome->result;
        } catch (IdempotencyConflict $exception) {
            $this->failure('eligibility_conflict');
            throw $exception;
        } catch (OperatorException $exception) {
            $this->failure($exception->category);
            throw $exception;
        } catch (Throwable $exception) {
            $this->failure('eligibility_failure');
            throw new OperatorException('eligibility_failure', 'The eligible schedule could not be consumed.', $exception);
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertPayloadShape(array &$payload): void
    {
        foreach (['member_id', 'member', 'member_name', 'name', 'nik', 'full_nik', 'email', 'phone', 'address'] as $protectedKey) {
            if (array_key_exists($protectedKey, $payload)) {
                throw new OperatorException('eligibility_protected_payload', 'Eligibility payload contains prohibited Member data.');
            }
        }
        $payload['source_event_id'] = (string) ($payload['source_event_id'] ?? '');
        foreach (['schedule_id', 'starts_at', 'ends_at', 'source_event_id'] as $key) {
            if (! is_string($payload[$key]) || trim($payload[$key]) === '') {
                throw new OperatorException('eligibility_invalid', 'Eligibility payload is incomplete.');
            }
        }
        foreach (['confirmed_count', 'quota', 'event_version'] as $key) {
            if (! is_int($payload[$key] ?? null) && ! (is_string($payload[$key] ?? null) && ctype_digit((string) $payload[$key]))) {
                throw new OperatorException('eligibility_invalid', 'Eligibility payload contains invalid counts.');
            }
            $payload[$key] = (int) $payload[$key];
            if ($payload[$key] < 1) {
                throw new OperatorException('eligibility_invalid', 'Eligibility counts must be positive.');
            }
        }
        if ($payload['source_event_id'] === '') {
            throw new OperatorException('eligibility_invalid', 'Eligibility payload requires its source event identity.');
        }
        if ($payload['event_version'] < 1) {
            throw new OperatorException('eligibility_invalid', 'Eligibility event version must be positive.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function stableSiteId(array $payload): string
    {
        if (isset($payload['operator_site_id']) && is_string($payload['operator_site_id']) && trim($payload['operator_site_id']) !== '') {
            return trim($payload['operator_site_id']);
        }

        throw new OperatorException('eligibility_invalid', 'Eligibility requires a stable Operator site identity.');
    }

    private function instant(string $value): DateTimeImmutable
    {
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', trim($value)) !== 1) {
            throw new OperatorException('eligibility_invalid', 'Eligibility times require an explicit offset.');
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new OperatorException('eligibility_invalid', 'Eligibility time is invalid.', $exception);
        }
    }

    private function eventContext(): AuthenticatedContext
    {
        $context = $this->context->current();

        return $context->purpose === null ? $context->forPurpose('operator.shift-eligible') : $context;
    }

    private function failure(string $category): void
    {
        $context = $this->eventContext();
        $this->audit->append(AuditEvent::fromContext($context, 'operator.shift-eligible.failed', 'operator', 'failure', $this->clock->now(), reason: $category));
    }
}
