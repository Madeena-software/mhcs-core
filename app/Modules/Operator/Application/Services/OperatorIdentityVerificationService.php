<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Contracts\OperatorIdentityVerificationContract;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;
use App\Shared\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorIdentityVerificationService
{
    public const OPEN = 'open';

    public const MATCHED = 'matched';

    public const MISMATCH_REPORTED = 'mismatch_reported';

    public const INSUFFICIENT_EVIDENCE = 'insufficient_evidence';

    public const CANCELLED = 'cancelled';

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
        private OperatorIdentityVerificationContract $memberIdentity,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function start(string $arrivalId, string $operationId, bool $reclaim = false): array
    {
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $operationId = $this->operation($operationId);

        try {
            return DB::transaction(function () use ($identity, $site, $arrivalId, $operationId, $reclaim): array {
                $profileId = (string) $identity['profile']->getKey();
                if (DB::table('operator_profiles')->where('id', $profileId)->where('active', true)->lockForUpdate()->first() === null) {
                    throw new OperatorException('identity_operator_unavailable', 'The Operator profile is unavailable.');
                }
                $arrival = DB::table('operator_arrivals')
                    ->where('id', trim($arrivalId))
                    ->where('operator_site_id', $site->getKey())
                    ->where('status', 'recorded')
                    ->lockForUpdate()
                    ->first();
                if ($arrival === null || ! $this->arrivedBooking((string) $arrival->booking_id, (string) $arrival->member_schedule_id)) {
                    throw new OperatorException('identity_arrival_unavailable', 'The arrived Member is unavailable for verification.');
                }
                if (! $this->assignments->isAssigned($profileId, (string) $arrival->member_schedule_id, $site->operator_site_id)) {
                    throw new OperatorException('identity_assignment_denied', 'The Operator is not assigned to this schedule.');
                }

                $existingOperation = DB::table('operator_identity_verifications')->where('operation_id', $operationId)->lockForUpdate()->first();
                if ($existingOperation !== null) {
                    if ((string) $existingOperation->arrival_id !== (string) $arrival->id || (string) $existingOperation->operator_profile_id !== $profileId) {
                        throw new OperatorException('identity_operation_conflict', 'The verification operation conflicts with existing work.');
                    }

                    return $this->caseResult($existingOperation);
                }

                $case = DB::table('operator_identity_verifications')
                    ->where('arrival_id', $arrival->id)
                    ->lockForUpdate()
                    ->first();
                if ($case !== null && $case->state === self::OPEN) {
                    throw new OperatorException('identity_claim_unavailable', 'This arrived Member is already claimed for verification.');
                }
                if ($case !== null && $case->state !== self::CANCELLED) {
                    throw new OperatorException('identity_terminal', 'This verification case is terminal and cannot be reopened.');
                }
                if ($case !== null && ! $reclaim) {
                    throw new OperatorException('identity_reclaim_required', 'Reclaiming a cancelled verification case requires explicit confirmation.');
                }
                if (DB::table('operator_identity_verifications')
                    ->where('active_claim_operator_profile_id', $profileId)
                    ->where('state', self::OPEN)
                    ->where('arrival_id', '!=', (string) $arrival->id)
                    ->exists()) {
                    throw new OperatorException('identity_operator_claimed', 'This Operator already has an open verification case.');
                }

                $now = $this->clock->now();
                if ($case === null) {
                    $caseId = (string) Str::uuid();
                    DB::table('operator_identity_verifications')->insert([
                        'id' => $caseId,
                        'arrival_id' => (string) $arrival->id,
                        'booking_id' => (string) $arrival->booking_id,
                        'member_schedule_id' => (string) $arrival->member_schedule_id,
                        'operator_site_id' => (string) $site->getKey(),
                        'operator_profile_id' => $profileId,
                        'active_claim_operator_profile_id' => $profileId,
                        'state' => self::OPEN,
                        'started_at' => $now,
                        'decided_at' => null,
                        'reason_category' => null,
                        'reason' => null,
                        'operation_id' => $operationId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $case = DB::table('operator_identity_verifications')->where('id', $caseId)->first();
                    $fromState = null;
                } else {
                    $fromState = (string) $case->state;
                    DB::table('operator_identity_verifications')->where('id', $case->id)->update([
                        'operator_site_id' => (string) $site->getKey(),
                        'operator_profile_id' => $profileId,
                        'active_claim_operator_profile_id' => $profileId,
                        'state' => self::OPEN,
                        'started_at' => $now,
                        'decided_at' => null,
                        'reason_category' => null,
                        'reason' => null,
                        'operation_id' => $operationId,
                        'updated_at' => $now,
                    ]);
                    $case = DB::table('operator_identity_verifications')->where('id', $case->id)->first();
                }

                $this->event($case, 'started', $fromState, self::OPEN, null, $operationId, $now);
                $this->audit($identity['context'], 'operator.identity-verification.started', $case, $now, 'identity_case_started', ['reclaimed' => $fromState === self::CANCELLED]);

                return $this->caseResult($case);
            });
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)) {
                throw new OperatorException('identity_operator_claimed', 'This Operator already has an open verification case.', $exception);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function view(string $caseId): array
    {
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $case = $this->caseForOperator($identity, $site->getKey(), $caseId);
        if ($case->state !== self::OPEN) {
            return ['case' => $this->caseResult($case), 'safeSummary' => null, 'evidenceStatus' => 'closed', 'allowedDecisions' => [], 'view' => null];
        }

        try {
            $memberView = $this->memberIdentity->currentView(
                $this->context($identity['context'], 'operator.identity.view', (string) $case->id),
                $site->operator_site_id,
                (string) $case->member_schedule_id,
                (string) $case->booking_id,
                (string) $case->id,
            );
            if ($memberView['evidence_status'] === 'unavailable') {
                $safeSummary = $this->memberAttendance->safeArrivalSummary((string) $case->booking_id);
                if ($safeSummary === null) {
                    throw new OperatorException('identity_view_unavailable', 'The identity verification view is unavailable.');
                }

                return [
                    'case' => $this->caseResult($case),
                    'safeSummary' => $safeSummary,
                    'evidenceStatus' => 'unavailable',
                    'allowedDecisions' => [self::MISMATCH_REPORTED, self::INSUFFICIENT_EVIDENCE],
                    'view' => null,
                ];
            }
            $view = $memberView['view'];
            if (! is_array($view)) {
                throw new OperatorException('identity_view_unavailable', 'The identity verification view is unavailable.');
            }
            if (DB::table('operator_identity_verification_events')
                ->where('verification_id', $case->id)
                ->where('event_type', 'previous_photos_revealed')
                ->exists()) {
                $view['previous_photos_revealed'] = true;
                $view['previous_profile_photos'] = $this->previousPhotos($identity, $site, $case);
            }

            return [
                'case' => $this->caseResult($case),
                'safeSummary' => null,
                'evidenceStatus' => 'available',
                'allowedDecisions' => [self::MATCHED, self::MISMATCH_REPORTED, self::INSUFFICIENT_EVIDENCE],
                'view' => $view,
            ];
        } catch (Throwable $exception) {
            throw new OperatorException('identity_view_unavailable', 'The identity verification view is unavailable.', $exception);
        }
    }

    /** @return array<string, mixed> */
    public function lookupByNik(string $caseId, string $nik, string $at): array
    {
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $case = $this->openCase($identity, $site->getKey(), $caseId);

        try {
            $this->memberIdentity->lookupByNik(
                $this->context($identity['context'], 'operator.identity.lookup', (string) $case->id),
                $site->operator_site_id,
                (string) $case->member_schedule_id,
                (string) $case->booking_id,
                (string) $case->id,
                $nik,
                $at,
            );

            return $this->decide($caseId, self::MATCHED, null, (string) Str::uuid());
        } catch (Throwable $exception) {
            throw new OperatorException('identity_lookup_unavailable', 'The identity lookup is unavailable.', $exception);
        }
    }

    /** @return list<array<string, mixed>> */
    public function revealPreviousPhotos(string $caseId, string $reason, string $operationId): array
    {
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $operationId = $this->operation($operationId);
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($identity, $site, $caseId, $reason, $operationId): array {
            $case = $this->openCase($identity, $site->getKey(), $caseId);
            $existing = $this->replayedEvent($case, $operationId, 'previous_photos_revealed', $reason);
            if ($existing !== null) {
                return $this->previousPhotos($identity, $site, $case);
            }
            try {
                $photos = $this->memberIdentity->revealPreviousPhotos(
                    $this->context($identity['context'], 'operator.identity.previous', (string) $case->id),
                    $site->operator_site_id,
                    (string) $case->member_schedule_id,
                    (string) $case->booking_id,
                    (string) $case->id,
                    $reason,
                );
            } catch (Throwable $exception) {
                throw new OperatorException('identity_previous_unavailable', 'Previous profile photos are unavailable.', $exception);
            }
            $this->event($case, 'previous_photos_revealed', self::OPEN, self::OPEN, $reason, $operationId, $this->clock->now());
            $this->audit($identity['context'], 'operator.identity-verification.previous-photos.revealed', $case, $this->clock->now(), 'latest_photo_insufficient');

            return $photos;
        });
    }

    /** @return array{contents: string, format: string} */
    public function retrieveAsset(string $caseId, string $assetId): array
    {
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $case = $this->openCase($identity, $site->getKey(), $caseId);
        try {
            $memberView = $this->memberIdentity->currentView(
                $this->context($identity['context'], 'operator.identity.view', (string) $case->id),
                $site->operator_site_id,
                (string) $case->member_schedule_id,
                (string) $case->booking_id,
                (string) $case->id,
            );
            $view = $memberView['view'];
            if (! is_array($view)) {
                throw new OperatorException('identity_asset_unavailable', 'The requested verification asset is unavailable.');
            }
            $allowed = array_filter([
                data_get($view, 'identity_document.asset_id'),
                data_get($view, 'latest_profile_photo.asset_id'),
            ]);
            $revealed = DB::table('operator_identity_verification_events')
                ->where('verification_id', $case->id)
                ->where('event_type', 'previous_photos_revealed')
                ->exists();
            if ($revealed) {
                $allowed = [...$allowed, ...array_filter(array_column($this->previousPhotos($identity, $site, $case), 'asset_id'))];
            }
            if (! in_array($assetId, $allowed, true)) {
                throw new OperatorException('identity_asset_unavailable', 'The requested verification asset is unavailable.');
            }

            return $this->memberIdentity->retrieveAsset(
                $this->context($identity['context'], 'operator.identity.asset', (string) $case->id),
                $site->operator_site_id,
                (string) $case->member_schedule_id,
                (string) $case->booking_id,
                (string) $case->id,
                $assetId,
            );
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('identity_asset_unavailable', 'The requested verification asset is unavailable.', $exception);
        }
    }

    /** @return array<string, mixed> */
    public function decide(string $caseId, string $state, ?string $reason, string $operationId): array
    {
        if (! in_array($state, [self::MATCHED, self::MISMATCH_REPORTED, self::INSUFFICIENT_EVIDENCE], true)) {
            throw new OperatorException('identity_decision_invalid', 'The verification decision is invalid.');
        }
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $operationId = $this->operation($operationId);
        $reason = $reason === null ? null : $this->reason($reason);
        if ($state !== self::MATCHED && $reason === null) {
            throw new OperatorException('identity_reason_required', 'A reason is required for this verification decision.');
        }

        return DB::transaction(function () use ($identity, $site, $caseId, $state, $reason, $operationId): array {
            $case = $this->caseForOperator($identity, $site->getKey(), $caseId);
            $existing = $this->replayedEvent($case, $operationId, 'decision', $reason, $state);
            if ($existing !== null) {
                return $this->caseResult($case);
            }
            if ($case->state !== self::OPEN) {
                throw new OperatorException('identity_terminal', 'A terminal verification decision cannot be changed.');
            }
            if ($state === self::MATCHED) {
                try {
                    $memberView = $this->memberIdentity->currentView(
                        $this->context($identity['context'], 'operator.identity.view', (string) $case->id),
                        $site->operator_site_id,
                        (string) $case->member_schedule_id,
                        (string) $case->booking_id,
                        (string) $case->id,
                    );
                    if ($memberView['evidence_status'] !== 'available' || ! is_array($memberView['view'])) {
                        throw new OperatorException('identity_evidence_unavailable', 'Current approved identity evidence is unavailable.');
                    }
                } catch (Throwable $exception) {
                    throw new OperatorException('identity_evidence_unavailable', 'Current approved identity evidence is unavailable.', $exception);
                }
            }
            $now = $this->clock->now();
            DB::table('operator_identity_verifications')->where('id', $case->id)->update([
                'state' => $state,
                'active_claim_operator_profile_id' => null,
                'decided_at' => $now,
                'reason_category' => $state,
                'reason' => $reason,
                'updated_at' => $now,
            ]);
            $this->event($case, 'decision', self::OPEN, $state, $reason, $operationId, $now);
            $case = DB::table('operator_identity_verifications')->where('id', $case->id)->first();
            $this->audit($identity['context'], 'operator.identity-verification.'.$state, $case, $now, $this->auditReasonForDecision($state));

            return $this->caseResult($case);
        });
    }

    /** @return array<string, mixed> */
    public function cancel(string $caseId, string $reason, string $operationId): array
    {
        $identity = $this->identity();
        $site = $this->authorization->portalSite($identity);
        $reason = $this->reason($reason);
        $operationId = $this->operation($operationId);

        return DB::transaction(function () use ($identity, $site, $caseId, $reason, $operationId): array {
            $case = $this->caseForOperator($identity, $site->getKey(), $caseId);
            $existing = $this->replayedEvent($case, $operationId, 'cancelled', $reason);
            if ($existing !== null) {
                return $this->caseResult($case);
            }
            if ($case->state !== self::OPEN) {
                throw new OperatorException('identity_terminal', 'A terminal verification case cannot be cancelled.');
            }
            $now = $this->clock->now();
            DB::table('operator_identity_verifications')->where('id', $case->id)->update([
                'state' => self::CANCELLED,
                'active_claim_operator_profile_id' => null,
                'decided_at' => $now,
                'reason_category' => self::CANCELLED,
                'reason' => $reason,
                'updated_at' => $now,
            ]);
            $this->event($case, 'cancelled', self::OPEN, self::CANCELLED, $reason, $operationId, $now);
            $case = DB::table('operator_identity_verifications')->where('id', $case->id)->first();
            $this->audit($identity['context'], 'operator.identity-verification.cancelled', $case, $now, 'identity_case_cancelled');

            return $this->caseResult($case);
        });
    }

    public function hasOpenCase(string $profileId, string $siteId): bool
    {
        return DB::table('operator_identity_verifications')
            ->where('active_claim_operator_profile_id', $profileId)
            ->where('state', self::OPEN)
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    private function previousPhotos(array $identity, object $site, object $case): array
    {
        try {
            return $this->memberIdentity->revealPreviousPhotos(
                $this->context($identity['context'], 'operator.identity.previous', (string) $case->id),
                $site->operator_site_id,
                (string) $case->member_schedule_id,
                (string) $case->booking_id,
                (string) $case->id,
                'prior_photo_reveal_replay',
            );
        } catch (Throwable $exception) {
            throw new OperatorException('identity_previous_unavailable', 'Previous profile photos are unavailable.', $exception);
        }
    }

    private function identity(): array
    {
        return $this->authorization->identity();
    }

    private function openCase(array $identity, string $siteId, string $caseId): object
    {
        $case = $this->caseForOperator($identity, $siteId, $caseId);
        if ($case->state !== self::OPEN) {
            throw new OperatorException('identity_case_closed', 'The verification case is no longer open.');
        }

        return $case;
    }

    private function caseForOperator(array $identity, string $siteId, string $caseId): object
    {
        if (! Str::isUuid($caseId)) {
            throw new OperatorException('identity_case_invalid', 'The verification case is unavailable.');
        }
        $case = DB::table('operator_identity_verifications')
            ->where('id', $caseId)
            ->where('operator_site_id', $siteId)
            ->where('operator_profile_id', (string) $identity['profile']->getKey())
            ->lockForUpdate()
            ->first();
        if ($case === null || ! $this->arrivedBooking((string) $case->booking_id, (string) $case->member_schedule_id) || ! $this->assignments->isAssigned((string) $identity['profile']->getKey(), (string) $case->member_schedule_id, (string) $this->siteStableId($siteId))) {
            throw new OperatorException('identity_case_unavailable', 'The verification case is unavailable.');
        }

        return $case;
    }

    private function siteStableId(string $siteId): string
    {
        $stable = DB::table('operator_sites')->where('id', $siteId)->value('operator_site_id');
        if (! is_string($stable) || trim($stable) === '') {
            throw new OperatorException('identity_site_unavailable', 'The active site is unavailable.');
        }

        return $stable;
    }

    private function arrivedBooking(string $bookingId, string $scheduleId): bool
    {
        return DB::table('bookings')
            ->where('id', $bookingId)
            ->where('shift_schedule_id', $scheduleId)
            ->where('status', 'arrived')
            ->exists();
    }

    private function operation(string $operationId): string
    {
        $operationId = trim($operationId);
        if (! Str::isUuid($operationId)) {
            throw new OperatorException('identity_operation_invalid', 'A valid verification operation is required.');
        }

        return $operationId;
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $reason) === 1) {
            throw new OperatorException('identity_reason_required', 'A bounded reason is required.');
        }

        return $reason;
    }

    private function event(object $case, string $type, ?string $from, ?string $to, ?string $reason, string $operationId, object $occurredAt): void
    {
        DB::table('operator_identity_verification_events')->insert([
            'id' => (string) Str::uuid(),
            'verification_id' => (string) $case->id,
            'event_type' => $type,
            'from_state' => $from,
            'to_state' => $to,
            'reason' => $reason,
            'operation_id' => $operationId,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    private function replayedEvent(object $case, string $operationId, string $type, ?string $reason, ?string $state = null): ?object
    {
        $event = DB::table('operator_identity_verification_events')->where('operation_id', $operationId)->lockForUpdate()->first();
        if ($event === null) {
            return null;
        }
        if (
            (string) $event->verification_id !== (string) $case->id
            || (string) $event->event_type !== $type
            || (string) ($event->reason ?? '') !== (string) ($reason ?? '')
            || ($state !== null && (string) $event->to_state !== $state)
        ) {
            throw new OperatorException('identity_operation_conflict', 'The verification operation conflicts with existing work.');
        }

        return $event;
    }

    /** @return array<string, mixed> */
    private function caseResult(object $case): array
    {
        return [
            'case_id' => (string) $case->id,
            'arrival_id' => (string) $case->arrival_id,
            'booking_id' => (string) $case->booking_id,
            'schedule_id' => (string) $case->member_schedule_id,
            'site_id' => (string) $case->operator_site_id,
            'operator_profile_id' => (string) $case->operator_profile_id,
            'state' => (string) $case->state,
            'started_at' => (string) $case->started_at,
            'decided_at' => $case->decided_at === null ? null : (string) $case->decided_at,
            'reason_category' => $case->reason_category,
            'reason' => $case->reason,
        ];
    }

    private function audit(AuthenticatedContext $context, string $action, object $case, object $occurredAt, ?string $reason = null, array $metadata = []): void
    {
        $this->audit->append(AuditEvent::fromContext(
            $context,
            $action,
            'operator',
            'success',
            $occurredAt,
            'operator_identity_verification',
            (string) $case->id,
            reason: $reason,
            metadata: ['arrival_id' => (string) $case->arrival_id, 'booking_id' => (string) $case->booking_id, 'schedule_id' => (string) $case->member_schedule_id, 'operator_site_id' => (string) $case->operator_site_id, ...$metadata],
        ));
    }

    private function auditReasonForDecision(string $state): string
    {
        return match ($state) {
            self::MATCHED => 'identity_matched',
            self::MISMATCH_REPORTED => 'identity_mismatch_reported',
            self::INSUFFICIENT_EVIDENCE => 'identity_evidence_insufficient',
        };
    }

    private function context(AuthenticatedContext $base, string $purpose, string $caseId): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: $base->actorId,
            operationId: $base->operationId,
            sessionId: $base->sessionId,
            roles: $base->roles,
            permissions: $base->permissions,
            siteId: $base->siteId,
            caseId: LocalId::fromString($caseId),
            purpose: $purpose,
        );
    }
}
