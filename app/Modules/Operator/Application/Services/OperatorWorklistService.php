<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorWorklistService
{
    public const CLAIM_PURPOSE = 'operator.basic-examination.claim';

    public const CALL_PURPOSE = 'operator.basic-examination.call';

    public const START_PURPOSE = 'operator.basic-examination.start';

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /** @return list<array<string, mixed>> */
    public function current(): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $rows = DB::table('operator_arrivals')
            ->join('operator_profiles', 'operator_profiles.id', '=', 'operator_arrivals.operator_profile_id')
            ->leftJoin('operator_identity_verifications', 'operator_identity_verifications.arrival_id', '=', 'operator_arrivals.id')
            ->where('operator_arrivals.operator_site_id', $site->getKey())
            ->where('operator_arrivals.status', 'recorded')
            ->select(['operator_arrivals.*', 'operator_profiles.display_name as operator_name'])
            ->addSelect(['operator_identity_verifications.id as verification_case_id', 'operator_identity_verifications.state as verification_state', 'operator_identity_verifications.operator_profile_id as verification_operator_profile_id'])
            ->orderByDesc('operator_arrivals.occurrence_at')
            ->get();

        return $rows->map(function (object $row): array {
            $member = $this->memberAttendance->safeArrivalSummary((string) $row->booking_id) ?? [];

            return [
                'arrival_id' => (string) $row->id,
                'booking_id' => (string) $row->booking_id,
                'member_name' => $member['member_name'] ?? 'Member tidak tersedia',
                'medical_record_number' => $member['medical_record_number'] ?? null,
                'operator_name' => $row->operator_name ?: 'Operator',
                'occurrence_at' => (string) $row->occurrence_at,
                'recorded_at' => (string) $row->recorded_at,
                'status' => 'pending_verification',
                'verification_case_id' => $row->verification_case_id === null ? null : (string) $row->verification_case_id,
                'verification_state' => $row->verification_state === null ? 'unclaimed' : (string) $row->verification_state,
                'verification_operator_profile_id' => $row->verification_operator_profile_id === null ? null : (string) $row->verification_operator_profile_id,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function basicExamination(): array
    {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();

        return DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('operator_sites as sites', 'sites.id', '=', 'admissions.operator_site_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('admissions.operator_site_id', $site->getKey())
            ->where('member_sites.operator_site_id', $site->operator_site_id)
            ->where('admissions.queue_class', 'advance')
            ->where('admissions.stage', 'basic_examination')
            ->whereIn('admissions.state', ['waiting', 'called'])
            ->where(function ($query) use ($profileId): void {
                $query->whereNull('admissions.operator_profile_id')
                    ->orWhere('admissions.operator_profile_id', $profileId);
            })
            ->whereExists(function ($query) use ($profileId): void {
                $query->selectRaw('1')
                    ->from('operator_shift_assignments as assignments')
                    ->join('operator_eligible_shifts as eligible', 'eligible.id', '=', 'assignments.operator_eligible_shift_id')
                    ->whereColumn('eligible.member_schedule_id', 'admissions.member_schedule_id')
                    ->whereColumn('eligible.operator_site_id', 'sites.operator_site_id')
                    ->where('assignments.operator_profile_id', $profileId)
                    ->where('assignments.status', 'active')
                    ->where('eligible.sync_status', 'eligible');
            })
            ->select([
                'admissions.id as admission_id',
                'admissions.operator_profile_id as claim_operator_profile_id',
                'tickets.ticket_number',
                'sites.display_name as site_name',
                'schedules.starts_at as schedule_starts_at',
                'schedules.ends_at as schedule_ends_at',
                'admissions.stage',
                'admissions.state',
                'admissions.ready_at',
            ])
            ->orderBy('admissions.ready_at')
            ->orderBy('admissions.id')
            ->get()
            ->map(static fn (object $row): array => [
                'admission_id' => (string) $row->admission_id,
                'ticket_number' => (string) $row->ticket_number,
                'site_name' => (string) $row->site_name,
                'schedule_starts_at' => (string) $row->schedule_starts_at,
                'schedule_ends_at' => (string) $row->schedule_ends_at,
                'stage' => (string) $row->stage,
                'state' => (string) $row->state,
                'ready_at' => (string) $row->ready_at,
                'claimed_by_current_operator' => $row->claim_operator_profile_id !== null,
            ])->all();
    }

    /** @return array{admission_id: string, stage: string, state: string, claimed_at: string} */
    public function claimBasicExamination(string $admissionId, string $operationId): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('queue_claim_forbidden', 'The queue admission is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::CLAIM_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::CLAIM_PURPOSE,
                $payload,
                function () use ($admissionId, $profileId, $site, $context, $operationId): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('queue_claim_forbidden', 'The queue admission is unavailable.');
                    }

                    $admission = DB::table('operator_queue_admissions as admissions')
                        ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
                        ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
                        ->where('admissions.id', $admissionId)
                        ->where('admissions.operator_site_id', $site->getKey())
                        ->where('member_sites.operator_site_id', $site->operator_site_id)
                        ->select('admissions.*')
                        ->lockForUpdate()
                        ->first();
                    if ($admission === null) {
                        throw new OperatorException('queue_claim_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->operator_profile_id !== null) {
                        if ((string) $admission->operator_profile_id === $profileId) {
                            throw new OperatorException('queue_claim_conflict', 'The queue admission could not be claimed.');
                        }

                        throw new OperatorException('queue_claim_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->queue_class !== 'advance' || $admission->stage !== 'basic_examination' || $admission->state !== 'waiting') {
                        throw new OperatorException('queue_claim_conflict', 'The queue admission could not be claimed.');
                    }
                    if (! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
                        throw new OperatorException('queue_claim_forbidden', 'The queue admission is unavailable.');
                    }
                    if (DB::table('operator_queue_admissions')->where('operator_profile_id', $profileId)->exists()) {
                        throw new OperatorException('queue_claim_conflict', 'The queue admission could not be claimed.');
                    }

                    $now = $this->clock->now();
                    DB::table('operator_queue_admissions')
                        ->where('id', $admissionId)
                        ->update([
                            'operator_profile_id' => $profileId,
                            'claimed_at' => $now,
                            'updated_at' => $now,
                        ]);
                    DB::table('operator_queue_admission_history')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_queue_admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'event_type' => 'claimed',
                        'from_state' => 'waiting',
                        'to_state' => 'waiting',
                        'operation_id' => $operationId,
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $metadata = [
                        'admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'stage' => 'basic_examination',
                        'state' => 'waiting',
                        'claimed_at_utc' => $now->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.queue-admission.claimed',
                        'operator',
                        'success',
                        $now,
                        'queue-admission',
                        $admissionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.queue-admission-claimed',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($admissionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'stage' => 'basic_examination',
                        'state' => 'waiting',
                        'claimed_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('queue_claim_conflict', 'The queue admission could not be claimed.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('queue_claim_conflict', 'The queue admission could not be claimed.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('queue_claim_failure', 'The queue admission could not be claimed.', $exception);
        }
    }

    /** @return array{admission_id: string, stage: string, state: string, called_at: string} */
    public function callBasicExamination(string $admissionId, string $operationId): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('queue_call_forbidden', 'The queue admission is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::CALL_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::CALL_PURPOSE,
                $payload,
                function () use ($admissionId, $profileId, $site, $context, $operationId): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('queue_call_forbidden', 'The queue admission is unavailable.');
                    }

                    $admission = DB::table('operator_queue_admissions as admissions')
                        ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
                        ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
                        ->where('admissions.id', $admissionId)
                        ->where('admissions.operator_site_id', $site->getKey())
                        ->where('member_sites.operator_site_id', $site->operator_site_id)
                        ->select('admissions.*')
                        ->lockForUpdate()
                        ->first();
                    if ($admission === null || (string) $admission->operator_profile_id !== $profileId) {
                        throw new OperatorException('queue_call_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->queue_class !== 'advance' || $admission->stage !== 'basic_examination') {
                        throw new OperatorException('queue_call_conflict', 'The queue admission could not be called.');
                    }
                    if (! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
                        throw new OperatorException('queue_call_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->state !== 'waiting') {
                        throw new OperatorException('queue_call_conflict', 'The queue admission could not be called.');
                    }

                    $now = $this->clock->now();
                    DB::table('operator_queue_admissions')
                        ->where('id', $admissionId)
                        ->update([
                            'state' => 'called',
                            'updated_at' => $now,
                        ]);
                    DB::table('operator_queue_admission_history')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_queue_admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'event_type' => 'called',
                        'from_state' => 'waiting',
                        'to_state' => 'called',
                        'operation_id' => $operationId,
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $metadata = [
                        'admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'queue_class' => 'advance',
                        'stage' => 'basic_examination',
                        'previous_state' => 'waiting',
                        'state' => 'called',
                        'called_at_utc' => $now->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.queue-admission.called',
                        'operator',
                        'success',
                        $now,
                        'queue-admission',
                        $admissionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.queue-admission-called',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($admissionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'stage' => 'basic_examination',
                        'state' => 'called',
                        'called_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('queue_call_conflict', 'The queue admission could not be called.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('queue_call_conflict', 'The queue admission could not be called.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('queue_call_failure', 'The queue admission could not be called.', $exception);
        }
    }

    /** @return array{admission_id: string, stage: string, state: string, started_at: string} */
    public function startBasicExamination(string $admissionId, string $operationId): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('queue_start_forbidden', 'The queue admission is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::START_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::START_PURPOSE,
                $payload,
                function () use ($admissionId, $profileId, $site, $context, $operationId): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('queue_start_forbidden', 'The queue admission is unavailable.');
                    }

                    $admission = DB::table('operator_queue_admissions as admissions')
                        ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
                        ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
                        ->where('admissions.id', $admissionId)
                        ->where('admissions.operator_site_id', $site->getKey())
                        ->where('member_sites.operator_site_id', $site->operator_site_id)
                        ->select('admissions.*')
                        ->lockForUpdate()
                        ->first();
                    if ($admission === null || (string) $admission->operator_profile_id !== $profileId) {
                        throw new OperatorException('queue_start_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->queue_class !== 'advance' || $admission->stage !== 'basic_examination') {
                        throw new OperatorException('queue_start_conflict', 'The queue admission could not be started.');
                    }
                    if (! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
                        throw new OperatorException('queue_start_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->state !== 'called') {
                        throw new OperatorException('queue_start_conflict', 'The queue admission could not be started.');
                    }

                    $now = $this->clock->now();
                    DB::table('operator_queue_admissions')
                        ->where('id', $admissionId)
                        ->update([
                            'state' => 'in_service',
                            'updated_at' => $now,
                        ]);
                    DB::table('operator_queue_admission_history')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_queue_admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'event_type' => 'started',
                        'from_state' => 'called',
                        'to_state' => 'in_service',
                        'operation_id' => $operationId,
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $metadata = [
                        'admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'queue_class' => 'advance',
                        'stage' => 'basic_examination',
                        'previous_state' => 'called',
                        'state' => 'in_service',
                        'started_at_utc' => $now->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.queue-admission.started',
                        'operator',
                        'success',
                        $now,
                        'queue-admission',
                        $admissionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.queue-admission-started',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($admissionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'stage' => 'basic_examination',
                        'state' => 'in_service',
                        'started_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('queue_start_conflict', 'The queue admission could not be started.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('queue_start_conflict', 'The queue admission could not be started.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('queue_start_failure', 'The queue admission could not be started.', $exception);
        }
    }
}
