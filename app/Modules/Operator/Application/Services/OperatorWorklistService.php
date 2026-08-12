<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Contracts\OperatorPaperQuestionnaireContract;
use App\Modules\Member\Application\Contracts\OperatorVitalSignsContract;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorWorklistService
{
    public const CLAIM_PURPOSE = 'operator.basic-examination.claim';

    public const XRAY_CLAIM_PURPOSE = 'operator.xray.claim';

    public const XRAY_CALL_PURPOSE = 'operator.xray.call';

    public const CALL_PURPOSE = 'operator.basic-examination.call';

    public const START_PURPOSE = 'operator.basic-examination.start';

    public const VITAL_SIGNS_PURPOSE = 'operator.basic-examination.vital-signs';

    public const QUESTIONNAIRE_PURPOSE = OperatorPaperQuestionnaireContract::PURPOSE;

    public const COMPLETE_PURPOSE = 'operator.basic-examination.complete';

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
        private OperatorPaperQuestionnaireContract $memberQuestionnaire,
        private OperatorVitalSignsContract $memberVitalSigns,
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
        $profileId = (string) $portal['profile']->getKey();
        $rows = DB::table('operator_arrivals')
            ->join('bookings', 'bookings.id', '=', 'operator_arrivals.booking_id')
            ->join('operator_profiles', 'operator_profiles.id', '=', 'operator_arrivals.operator_profile_id')
            ->leftJoin('operator_identity_verifications', 'operator_identity_verifications.arrival_id', '=', 'operator_arrivals.id')
            ->where('operator_arrivals.operator_site_id', $site->getKey())
            ->where('operator_arrivals.status', 'recorded')
            ->where('bookings.status', 'arrived')
            ->select(['operator_arrivals.*', 'operator_profiles.display_name as operator_name'])
            ->addSelect(['operator_identity_verifications.id as verification_case_id', 'operator_identity_verifications.state as verification_state', 'operator_identity_verifications.operator_profile_id as verification_operator_profile_id'])
            ->orderByDesc('operator_arrivals.occurrence_at')
            ->get();

        return $rows->map(function (object $row) use ($profileId): array {
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
                'can_open_verification' => $row->verification_case_id !== null
                    && (string) $row->verification_operator_profile_id === $profileId,
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
            ->whereIn('admissions.state', ['waiting', 'called', 'in_service'])
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
            ->selectRaw('exists (select 1 from operator_vital_signs_executions as executions where executions.operator_queue_admission_id = admissions.id) as has_vital_signs_execution')
            ->selectRaw('exists (select 1 from member_paper_questionnaires as questionnaires where questionnaires.booking_id = tickets.booking_id and questionnaires.member_schedule_id = admissions.member_schedule_id and questionnaires.operator_site_id = ?) as has_questionnaire', [$site->operator_site_id])
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
                'has_vital_signs_execution' => (bool) $row->has_vital_signs_execution,
                'has_questionnaire' => (bool) $row->has_questionnaire,
                'can_complete' => $row->claim_operator_profile_id !== null
                    && $row->state === 'in_service'
                    && (bool) $row->has_vital_signs_execution
                    && (bool) $row->has_questionnaire,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function xrayReadiness(): array
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
            ->where('admissions.stage', 'xray')
            ->where(function ($query) use ($profileId): void {
                $query->where(function ($query): void {
                    $query->whereNull('admissions.operator_profile_id')
                        ->where('admissions.state', 'waiting');
                })->orWhere(function ($query) use ($profileId): void {
                    $query->where('admissions.operator_profile_id', $profileId)
                        ->whereIn('admissions.state', ['waiting', 'called']);
                });
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

    /** @return array{admission_id: string, units: array<string, string>} */
    public function vitalSignsForm(string $admissionId): array
    {
        $admissionId = trim($admissionId);
        if (! Str::isUuid($admissionId)) {
            throw new OperatorException('vital_signs_forbidden', 'The vital-signs record is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $admission = $this->captureAdmission($admissionId, (string) $portal['profile']->getKey(), $site);
        $this->assertCaptureAdmission($admission, (string) $portal['profile']->getKey(), $site);

        return [
            'admission_id' => $admissionId,
            'units' => [
                'blood_pressure' => 'mmHg',
                'temperature' => '°C',
                'height' => 'cm',
                'weight' => 'kg',
                'bmi' => 'kg/m²',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{admission_id: string, execution_id: string, state: string, recorded_at: string}
     */
    public function recordBasicExaminationVitalSigns(string $admissionId, string $operationId, array $data): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('vital_signs_forbidden', 'The vital-signs record is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
            'data' => $data,
        ];
        $context = $this->authorization->current(self::VITAL_SIGNS_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::VITAL_SIGNS_PURPOSE,
                $payload,
                function () use ($admissionId, $operationId, $profileId, $site, $context, $data): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('vital_signs_forbidden', 'The vital-signs record is unavailable.');
                    }

                    $admission = $this->captureAdmission($admissionId, $profileId, $site, lock: true);
                    $this->assertCaptureAdmission($admission, $profileId, $site);
                    if (DB::table('operator_vital_signs_executions')->where('operator_queue_admission_id', $admissionId)->exists()) {
                        throw new OperatorException('vital_signs_conflict', 'The vital-signs record could not be recorded.');
                    }

                    $occurredAt = $this->clock->now();
                    try {
                        $assessment = $this->memberVitalSigns->record(
                            $context,
                            (string) $site->operator_site_id,
                            (string) $admission->member_id,
                            (string) $admission->booking_id,
                            (string) $admission->member_schedule_id,
                            $data,
                            $occurredAt->format(DATE_ATOM),
                        );
                    } catch (QueryException $exception) {
                        throw new OperatorException('vital_signs_conflict', 'The vital-signs record could not be recorded.', $exception);
                    } catch (Throwable $exception) {
                        throw new OperatorException('vital_signs_forbidden', 'The vital-signs record is unavailable.', $exception);
                    }
                    $executionId = (string) Str::uuid();
                    DB::table('operator_vital_signs_executions')->insert([
                        'id' => $executionId,
                        'member_vital_signs_assessment_id' => $assessment['assessment_id'],
                        'operator_queue_admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'operator_site_id' => (string) $site->getKey(),
                        'occurred_at' => $occurredAt,
                        'operation_id' => $operationId,
                        'created_at' => $occurredAt,
                        'updated_at' => $occurredAt,
                    ]);
                    $metadata = [
                        'admission_id' => $admissionId,
                        'assessment_id' => $assessment['assessment_id'],
                        'execution_id' => $executionId,
                        'operator_profile_id' => $profileId,
                        'operator_site_id' => (string) $site->getKey(),
                        'stage' => 'basic_examination',
                        'state' => 'in_service',
                        'recorded_at_utc' => $occurredAt->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.basic-examination.vital-signs-recorded',
                        'operator',
                        'success',
                        $occurredAt,
                        'operator-vital-signs-execution',
                        $executionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.basic-examination-vital-signs-recorded',
                        1,
                        $occurredAt,
                        $metadata,
                        LocalId::fromString($executionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'execution_id' => $executionId,
                        'state' => 'in_service',
                        'recorded_at' => $occurredAt->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('vital_signs_conflict', 'The vital-signs record could not be recorded.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('vital_signs_conflict', 'The vital-signs record could not be recorded.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('vital_signs_failure', 'The vital-signs record could not be recorded.', $exception);
        }
    }

    /** @return array{admission_id: string} */
    public function questionnaireForm(string $admissionId): array
    {
        $admissionId = trim($admissionId);
        if (! Str::isUuid($admissionId)) {
            throw new OperatorException('questionnaire_forbidden', 'The paper questionnaire is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $this->assertCaptureAdmission($this->captureAdmission($admissionId, (string) $portal['profile']->getKey(), $site), (string) $portal['profile']->getKey(), $site);

        return ['admission_id' => $admissionId];
    }

    /** @return array{questionnaire_id: string, completed_at: string} */
    public function recordBasicExaminationQuestionnaire(string $admissionId, string $operationId, UploadedFile $photo): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('questionnaire_forbidden', 'The paper questionnaire is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $context = $this->authorization->current(self::QUESTIONNAIRE_PURPOSE);
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];

        try {
            return $this->idempotency->run(
                $operationId,
                self::QUESTIONNAIRE_PURPOSE,
                $payload,
                function () use ($admissionId, $operationId, $profileId, $site, $context, $photo): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('questionnaire_forbidden', 'The paper questionnaire is unavailable.');
                    }

                    $admission = $this->captureAdmission($admissionId, $profileId, $site, lock: true);
                    $this->assertCaptureAdmission($admission, $profileId, $site);

                    return $this->memberQuestionnaire->record(
                        $context,
                        (string) $site->operator_site_id,
                        $profileId,
                        (string) $admission->member_id,
                        (string) $admission->booking_id,
                        (string) $admission->member_schedule_id,
                        $operationId,
                        $photo,
                    );
                },
            )->result;
        } catch (IdempotencyConflict|QueryException $exception) {
            throw new OperatorException('questionnaire_conflict', 'The paper questionnaire could not be recorded.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('questionnaire_failure', 'The paper questionnaire could not be recorded.', $exception);
        }
    }

    /** @return array{admission_id: string, state: string, xray_admission_id: string, xray_state: string, completed_at: string} */
    public function completeBasicExamination(string $admissionId, string $operationId): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('queue_completion_forbidden', 'The queue admission is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::COMPLETE_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::COMPLETE_PURPOSE,
                $payload,
                function () use ($admissionId, $operationId, $profileId, $site, $context): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('queue_completion_forbidden', 'The queue admission is unavailable.');
                    }

                    $admission = $this->captureAdmission($admissionId, $profileId, $site, lock: true);
                    if ($admission === null) {
                        throw new OperatorException('queue_completion_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->queue_class !== 'advance' || $admission->stage !== 'basic_examination' || $admission->state !== 'in_service') {
                        throw new OperatorException('queue_completion_conflict', 'The queue admission could not be completed.');
                    }
                    if ((string) $admission->operator_profile_id !== $profileId) {
                        throw new OperatorException('queue_completion_forbidden', 'The queue admission is unavailable.');
                    }
                    if ($admission->booking_status !== 'checked_in' || ! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
                        throw new OperatorException('queue_completion_forbidden', 'The queue admission is unavailable.');
                    }

                    $executionCount = DB::table('operator_vital_signs_executions as executions')
                        ->join('member_vital_signs_assessments as assessments', 'assessments.id', '=', 'executions.member_vital_signs_assessment_id')
                        ->where('executions.operator_queue_admission_id', $admissionId)
                        ->where('executions.operator_profile_id', $profileId)
                        ->where('executions.operator_site_id', $site->getKey())
                        ->where('assessments.member_id', $admission->member_id)
                        ->where('assessments.booking_id', $admission->booking_id)
                        ->where('assessments.member_schedule_id', $admission->member_schedule_id)
                        ->select('executions.id')
                        ->lockForUpdate()
                        ->get()
                        ->count();
                    if ($executionCount !== 1) {
                        throw new OperatorException('queue_completion_conflict', 'The queue admission could not be completed.');
                    }

                    $questionnaireCount = DB::table('member_paper_questionnaires')
                        ->where('member_id', $admission->member_id)
                        ->where('booking_id', $admission->booking_id)
                        ->where('member_schedule_id', $admission->member_schedule_id)
                        ->where('operator_site_id', $site->operator_site_id)
                        ->lockForUpdate()
                        ->count();
                    if ($questionnaireCount !== 1) {
                        throw new OperatorException('queue_completion_conflict', 'The queue admission could not be completed.');
                    }

                    $now = $this->clock->now();
                    $xrayAdmissionId = (string) Str::uuid();
                    DB::table('operator_queue_admissions')
                        ->where('id', $admissionId)
                        ->update([
                            'state' => 'completed',
                            'operator_profile_id' => null,
                            'claimed_at' => null,
                            'updated_at' => $now,
                        ]);
                    DB::table('operator_queue_admission_history')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_queue_admission_id' => $admissionId,
                        'operator_profile_id' => $profileId,
                        'event_type' => 'completed',
                        'from_state' => 'in_service',
                        'to_state' => 'completed',
                        'operation_id' => $operationId,
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('operator_queue_admissions')->insert([
                        'id' => $xrayAdmissionId,
                        'operator_paper_ticket_id' => (string) $admission->operator_paper_ticket_id,
                        'operator_site_id' => (string) $admission->operator_site_id,
                        'member_schedule_id' => (string) $admission->member_schedule_id,
                        'queue_class' => 'advance',
                        'stage' => 'xray',
                        'state' => 'waiting',
                        'ready_at' => $now,
                        'operator_profile_id' => null,
                        'claimed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    DB::table('operator_queue_admission_history')->insert([
                        'id' => (string) Str::uuid(),
                        'operator_queue_admission_id' => $xrayAdmissionId,
                        'operator_profile_id' => $profileId,
                        'event_type' => 'admitted',
                        'from_state' => null,
                        'to_state' => 'waiting',
                        'operation_id' => (string) Str::uuid(),
                        'occurred_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $metadata = [
                        'admission_id' => $admissionId,
                        'xray_admission_id' => $xrayAdmissionId,
                        'operator_profile_id' => $profileId,
                        'operator_site_id' => (string) $site->getKey(),
                        'queue_class' => 'advance',
                        'stage' => 'basic_examination',
                        'previous_state' => 'in_service',
                        'state' => 'completed',
                        'completed_at_utc' => $now->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.basic-examination.completed',
                        'operator',
                        'success',
                        $now,
                        'queue-admission',
                        $admissionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.basic-examination-completed',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($admissionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'state' => 'completed',
                        'xray_admission_id' => $xrayAdmissionId,
                        'xray_state' => 'waiting',
                        'completed_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('queue_completion_conflict', 'The queue admission could not be completed.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('queue_completion_conflict', 'The queue admission could not be completed.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('queue_completion_failure', 'The queue admission could not be completed.', $exception);
        }
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
                    if (DB::table('operator_queue_admissions')
                        ->where('operator_profile_id', $profileId)
                        ->where('stage', 'basic_examination')
                        ->exists()) {
                        throw new OperatorException('queue_claim_busy', 'This Operator already has another queue admission in progress.');
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

    /** @return array{admission_id: string, stage: string, state: string, claimed_at: string} */
    public function claimXray(string $admissionId, string $operationId): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('xray_claim_forbidden', 'The X-ray admission is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::XRAY_CLAIM_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::XRAY_CLAIM_PURPOSE,
                $payload,
                function () use ($admissionId, $profileId, $site, $context, $operationId): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('xray_claim_forbidden', 'The X-ray admission is unavailable.');
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
                        throw new OperatorException('xray_claim_forbidden', 'The X-ray admission is unavailable.');
                    }
                    if ($admission->operator_profile_id !== null) {
                        if ((string) $admission->operator_profile_id === $profileId) {
                            throw new OperatorException('xray_claim_conflict', 'The X-ray admission could not be claimed.');
                        }

                        throw new OperatorException('xray_claim_forbidden', 'The X-ray admission is unavailable.');
                    }
                    if ($admission->queue_class !== 'advance' || $admission->stage !== 'xray' || $admission->state !== 'waiting') {
                        throw new OperatorException('xray_claim_conflict', 'The X-ray admission could not be claimed.');
                    }
                    if (! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
                        throw new OperatorException('xray_claim_forbidden', 'The X-ray admission is unavailable.');
                    }
                    if (DB::table('operator_queue_admissions')
                        ->where('operator_profile_id', $profileId)
                        ->whereIn('stage', ['basic_examination', 'xray'])
                        ->exists()) {
                        throw new OperatorException('xray_claim_busy', 'This Operator already has another queue admission in progress.');
                    }
                    if (DB::table('operator_queue_admissions')->where('operator_profile_id', $profileId)->exists()) {
                        throw new OperatorException('xray_claim_conflict', 'The X-ray admission could not be claimed.');
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
                        'queue_class' => 'advance',
                        'state' => 'waiting',
                        'claimed_at_utc' => $now->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.xray.claimed',
                        'operator',
                        'success',
                        $now,
                        'queue-admission',
                        $admissionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.xray-claimed',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($admissionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'stage' => 'xray',
                        'state' => 'waiting',
                        'claimed_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('xray_claim_conflict', 'The X-ray admission could not be claimed.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('xray_claim_conflict', 'The X-ray admission could not be claimed.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('xray_claim_failure', 'The X-ray admission could not be claimed.', $exception);
        }
    }

    /** @return array{admission_id: string, stage: string, state: string, called_at: string} */
    public function callXray(string $admissionId, string $operationId): array
    {
        $admissionId = trim($admissionId);
        $operationId = trim($operationId);
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('xray_call_forbidden', 'The X-ray admission is unavailable.');
        }

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();
        $payload = [
            'admission_id' => $admissionId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::XRAY_CALL_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::XRAY_CALL_PURPOSE,
                $payload,
                function () use ($admissionId, $profileId, $site, $context, $operationId): array {
                    $transactionPortal = $this->authorization->portal();
                    $transactionSite = $this->authorization->portalSite($transactionPortal);
                    if ((string) $transactionPortal['profile']->getKey() !== $profileId || (string) $transactionSite->getKey() !== (string) $site->getKey()) {
                        throw new OperatorException('xray_call_forbidden', 'The X-ray admission is unavailable.');
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
                        throw new OperatorException('xray_call_forbidden', 'The X-ray admission is unavailable.');
                    }
                    if ($admission->queue_class !== 'advance' || $admission->stage !== 'xray') {
                        throw new OperatorException('xray_call_conflict', 'The X-ray admission could not be called.');
                    }
                    if (! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
                        throw new OperatorException('xray_call_forbidden', 'The X-ray admission is unavailable.');
                    }
                    if ($admission->state !== 'waiting') {
                        throw new OperatorException('xray_call_conflict', 'The X-ray admission could not be called.');
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
                        'previous_state' => 'waiting',
                        'state' => 'called',
                        'called_at_utc' => $now->format(DATE_ATOM),
                    ];
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.xray.called',
                        'operator',
                        'success',
                        $now,
                        'queue-admission',
                        $admissionId,
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.xray-called',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString($admissionId),
                        $context->operationId,
                    ));

                    return [
                        'admission_id' => $admissionId,
                        'stage' => 'xray',
                        'state' => 'called',
                        'called_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result;
        } catch (IdempotencyConflict $exception) {
            throw new OperatorException('xray_call_conflict', 'The X-ray admission could not be called.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            throw new OperatorException('xray_call_conflict', 'The X-ray admission could not be called.', $exception);
        } catch (Throwable $exception) {
            throw new OperatorException('xray_call_failure', 'The X-ray admission could not be called.', $exception);
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

    private function captureAdmission(string $admissionId, string $profileId, object $site, bool $lock = false): ?object
    {
        $query = DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('admissions.id', $admissionId)
            ->where('admissions.operator_site_id', $site->getKey())
            ->where('member_sites.operator_site_id', $site->operator_site_id)
            ->select([
                'admissions.*',
                'tickets.booking_id',
                'bookings.member_id',
                'bookings.status as booking_status',
            ]);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function assertCaptureAdmission(?object $admission, string $profileId, object $site): void
    {
        if ($admission === null || (string) $admission->operator_profile_id !== $profileId) {
            throw new OperatorException('vital_signs_forbidden', 'The vital-signs record is unavailable.');
        }
        if ($admission->queue_class !== 'advance' || $admission->stage !== 'basic_examination' || $admission->state !== 'in_service') {
            throw new OperatorException('vital_signs_conflict', 'The vital-signs record could not be recorded.');
        }
        if ($admission->booking_status !== 'checked_in' || ! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
            throw new OperatorException('vital_signs_forbidden', 'The vital-signs record is unavailable.');
        }
    }
}
