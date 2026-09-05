<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\ShiftScheduleClosureHandler;
use App\Modules\Operator\Domain\Models\RadiographySessionLocator;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Identity\LocalId;
use App\Shared\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RadiographySessionLocatorService implements ShiftScheduleClosureHandler
{
    private const int MAX_CODES = 10000;

    private const int MAX_ALLOCATION_RETRIES = 5;

    public function __construct(
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /**
     * Allocate a unique four-digit operational locator code (0000–9999) for an active radiography session.
     */
    public function allocate(string $admissionId, string $operatorSiteId, string $memberScheduleId): RadiographySessionLocator
    {
        $admissionId = trim($admissionId);
        $operatorSiteId = trim($operatorSiteId);
        $memberScheduleId = trim($memberScheduleId);

        // Check if admission already has an active locator
        $existing = RadiographySessionLocator::query()
            ->where('operator_queue_admission_id', $admissionId)
            ->where('status', 'active')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        for ($attempt = 0; $attempt < self::MAX_ALLOCATION_RETRIES; $attempt++) {
            try {
                return DB::transaction(function () use ($admissionId, $operatorSiteId, $memberScheduleId): RadiographySessionLocator {
                    $activeCodes = RadiographySessionLocator::query()
                        ->where('operator_site_id', $operatorSiteId)
                        ->where('member_schedule_id', $memberScheduleId)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->pluck('locator_code')
                        ->all();

                    if (count($activeCodes) >= self::MAX_CODES) {
                        throw new OperatorException(
                            'locator_code_space_exhausted',
                            'Radiography locator codes are exhausted for the active shift.',
                        );
                    }

                    $code = $this->generateUnusedCode($activeCodes);
                    $now = $this->clock->now();
                    $locatorId = (string) Str::uuid();
                    $activeKey = sprintf('%s:%s:%s', $operatorSiteId, $memberScheduleId, $code);

                    $locator = RadiographySessionLocator::query()->create([
                        'id' => $locatorId,
                        'operator_queue_admission_id' => $admissionId,
                        'operator_site_id' => $operatorSiteId,
                        'member_schedule_id' => $memberScheduleId,
                        'locator_code' => $code,
                        'status' => 'active',
                        'active_key' => $activeKey,
                        'allocated_at' => $now,
                    ]);

                    DB::table('operator_queue_admissions')
                        ->where('id', $admissionId)
                        ->update([
                            'locator_code' => $code,
                            'updated_at' => $now,
                        ]);

                    $metadata = [
                        'admission_id' => $admissionId,
                        'operator_site_id' => $operatorSiteId,
                        'schedule_id' => $memberScheduleId,
                        'code' => $code,
                    ];

                    $this->audit->append(new AuditEvent(
                        eventId: (string) Str::uuid(),
                        eventVersion: 1,
                        actorId: null,
                        sessionId: null,
                        roles: ['operator'],
                        permissions: ['operator.session.locate'],
                        siteId: LocalId::fromString($operatorSiteId),
                        caseId: null,
                        targetType: 'queue-admission',
                        targetId: $admissionId,
                        action: 'operator.queue-admission.located',
                        previousStateDigest: null,
                        newStateDigest: null,
                        reason: null,
                        occurredAt: $now,
                        recordedAt: $now,
                        correlationId: null,
                        source: 'operator',
                        outcome: 'success',
                        metadata: $metadata,
                    ));

                    return $locator;
                });
            } catch (QueryException $exception) {
                // If race condition hit active_key uniqueness, retry
                if ($attempt === self::MAX_ALLOCATION_RETRIES - 1) {
                    throw new OperatorException(
                        'locator_allocation_conflict',
                        'Failed to allocate a unique radiography locator code.',
                        $exception,
                    );
                }
            }
        }

        throw new OperatorException(
            'locator_allocation_failure',
            'Failed to allocate a unique radiography locator code.',
        );
    }

    /**
     * Mark code unusable when the session completes.
     */
    public function markCompleted(string $admissionId): void
    {
        $this->invalidate($admissionId, 'completed', 'session_completed');
    }

    /**
     * Mark code unusable when the session is cancelled.
     */
    public function markCancelled(string $admissionId, string $reason = 'session_cancelled'): void
    {
        $this->invalidate($admissionId, 'cancelled', $reason);
    }

    /**
     * Invalidate all active locators when a shift closes.
     */
    public function closeShiftLocators(string $memberScheduleId): int
    {
        $now = $this->clock->now();

        return RadiographySessionLocator::query()
            ->where('member_schedule_id', $memberScheduleId)
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'active_key' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => 'shift_closed',
                'updated_at' => $now,
            ]);
    }

    public function onShiftClosed(string $shiftScheduleId): void
    {
        $this->closeShiftLocators($shiftScheduleId);
    }

    /**
     * Find an active locator by site, shift, and 4-digit code.
     */
    public function findActive(string $operatorSiteId, string $memberScheduleId, string $code): ?RadiographySessionLocator
    {
        return RadiographySessionLocator::query()
            ->where('operator_site_id', $operatorSiteId)
            ->where('member_schedule_id', $memberScheduleId)
            ->where('locator_code', $code)
            ->where('status', 'active')
            ->first();
    }

    private function invalidate(string $admissionId, string $toStatus, string $reason): void
    {
        $now = $this->clock->now();

        RadiographySessionLocator::query()
            ->where('operator_queue_admission_id', $admissionId)
            ->where('status', 'active')
            ->update([
                'status' => $toStatus,
                'active_key' => null,
                'invalidated_at' => $now,
                'invalidation_reason' => $reason,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  list<string>  $activeCodes
     */
    private function generateUnusedCode(array $activeCodes): string
    {
        $activeMap = array_flip($activeCodes);

        // Try random selection first
        for ($i = 0; $i < 30; $i++) {
            $candidate = sprintf('%04d', random_int(0, 9999));
            if (! isset($activeMap[$candidate])) {
                return $candidate;
            }
        }

        // Sequential search if density is high
        for ($i = 0; $i < self::MAX_CODES; $i++) {
            $candidate = sprintf('%04d', $i);
            if (! isset($activeMap[$candidate])) {
                return $candidate;
            }
        }

        throw new OperatorException(
            'locator_code_space_exhausted',
            'Radiography locator codes are exhausted for the active shift.',
        );
    }
}
