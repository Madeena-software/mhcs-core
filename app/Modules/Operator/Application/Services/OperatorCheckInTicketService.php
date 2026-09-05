<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorCheckInTicketService
{
    public const PURPOSE = 'operator.check-in.issue';

    private const REPRINT_PURPOSE = 'operator.paper-ticket.reprint';

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private OperatorAttendanceContract $memberAttendance,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
        private ?RadiographySessionLocatorService $locators = null,
    ) {}

    /** @return array<string, mixed> */
    public function view(string $caseId): array
    {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $ticket = $this->ticketForBooking((string) $case->booking_id, $site, (string) $identity['profile']->getKey());
        if ($ticket !== null) {
            return ['ticket' => $this->ticketResult($ticket), 'case' => null];
        }

        $schedule = DB::table('shift_schedules')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->where('shift_schedules.id', (string) $case->member_schedule_id)
            ->where('examination_site_refs.operator_site_id', $site->operator_site_id)
            ->where('examination_site_refs.active', true)
            ->select('shift_schedules.*')
            ->first();
        if ($schedule === null) {
            throw new OperatorException('ticket_unavailable', 'The check-in schedule is unavailable.');
        }

        return [
            'ticket' => null,
            'case' => [
                'case_id' => (string) $case->id,
                'schedule_id' => (string) $case->member_schedule_id,
                'site_name' => (string) $site->display_name,
                'schedule_starts_at' => (string) $schedule->starts_at,
                'schedule_ends_at' => (string) $schedule->ends_at,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function issue(string $caseId, string $ticketNumber, string $operationId, bool $bypassBasicExamination = false): array
    {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $number = trim($ticketNumber) === '' ? null : $this->normalizeTicketNumber($ticketNumber);
        $operationId = $this->operation($operationId);
        $profileId = (string) $identity['profile']->getKey();
        $scheduleId = (string) $case->member_schedule_id;
        $bookingId = (string) $case->booking_id;
        if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('ticket_assignment_denied', 'The Operator is not assigned to this shift.');
        }

        $this->assertConsentNotWithdrawn($bookingId);

        $payload = [
            'case_id' => $caseId,
            'schedule_id' => $scheduleId,
            'booking_id' => $bookingId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
            'ticket_number' => $number,
            'bypass_basic_examination' => $bypassBasicExamination,
        ];
        $context = $this->context($identity['context'], self::PURPOSE, $caseId);

        try {
            return $this->idempotency->run(
                $operationId,
                self::PURPOSE,
                $payload,
                function () use ($context, $site, $profileId, $scheduleId, $bookingId, $caseId, $number, $operationId, $bypassBasicExamination): array {
                    if ($number === null) {
                        DB::table('shift_schedules')
                            ->where('id', $scheduleId)
                            ->lockForUpdate()
                            ->first();
                        $number = $this->nextTicketNumber((string) $site->getKey(), $scheduleId);
                    }
                    $now = $this->clock->now();
                    $memberResult = $this->memberAttendance->transitionArrivedToCheckedIn(
                        $context,
                        $site->operator_site_id,
                        $scheduleId,
                        $bookingId,
                        $caseId,
                        $now->format(DATE_ATOM),
                        $operationId,
                    );
                    if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
                        throw new OperatorException('ticket_assignment_denied', 'The Operator is no longer assigned to this shift.');
                    }

                    $ticketId = (string) Str::uuid();
                    DB::table('operator_paper_tickets')->insert([
                        'id' => $ticketId,
                        'booking_id' => $memberResult['booking_id'],
                        'member_schedule_id' => $memberResult['schedule_id'],
                        'operator_site_id' => (string) $site->getKey(),
                        'operator_profile_id' => $profileId,
                        'ticket_number' => $number,
                        'issued_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($bypassBasicExamination) {
                        $queueAdmissionId = (string) Str::uuid();
                        DB::table('operator_queue_admissions')->insert([
                            'id' => $queueAdmissionId,
                            'operator_paper_ticket_id' => $ticketId,
                            'operator_site_id' => (string) $site->getKey(),
                            'member_schedule_id' => $memberResult['schedule_id'],
                            'queue_class' => 'advance',
                            'stage' => 'basic_examination',
                            'state' => 'skipped',
                            'ready_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        DB::table('operator_queue_admission_history')->insert([
                            'id' => (string) Str::uuid(),
                            'operator_queue_admission_id' => $queueAdmissionId,
                            'operator_profile_id' => $profileId,
                            'event_type' => 'skipped',
                            'from_state' => null,
                            'to_state' => 'skipped',
                            'operation_id' => $operationId,
                            'occurred_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        $xrayAdmissionId = (string) Str::uuid();
                        DB::table('operator_queue_admissions')->insert([
                            'id' => $xrayAdmissionId,
                            'operator_paper_ticket_id' => $ticketId,
                            'operator_site_id' => (string) $site->getKey(),
                            'member_schedule_id' => $memberResult['schedule_id'],
                            'queue_class' => 'advance',
                            'stage' => 'xray',
                            'state' => 'waiting',
                            'ready_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        ($this->locators ?? app(RadiographySessionLocatorService::class))->allocate(
                            $xrayAdmissionId,
                            (string) $site->getKey(),
                            (string) $memberResult['schedule_id'],
                        );
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

                        $this->audit->append(AuditEvent::fromContext(
                            $context,
                            'operator.basic-examination.skipped',
                            'operator',
                            'success',
                            $now,
                            'queue-admission',
                            $queueAdmissionId,
                            metadata: [
                                'ticket_id' => $ticketId,
                                'ticket_number' => $number,
                                'schedule_id' => $scheduleId,
                                'operator_site_id' => $site->operator_site_id,
                                'operator_id' => $profileId,
                                'stage' => 'basic_examination',
                                'state' => 'skipped',
                                'xray_admission_id' => $xrayAdmissionId,
                            ],
                        ));
                    } else {
                        $queueAdmissionId = (string) Str::uuid();
                        DB::table('operator_queue_admissions')->insert([
                            'id' => $queueAdmissionId,
                            'operator_paper_ticket_id' => $ticketId,
                            'operator_site_id' => (string) $site->getKey(),
                            'member_schedule_id' => $memberResult['schedule_id'],
                            'queue_class' => 'advance',
                            'stage' => 'basic_examination',
                            'state' => 'waiting',
                            'ready_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        DB::table('operator_queue_admission_history')->insert([
                            'id' => (string) Str::uuid(),
                            'operator_queue_admission_id' => $queueAdmissionId,
                            'operator_profile_id' => $profileId,
                            'event_type' => 'admitted',
                            'from_state' => null,
                            'to_state' => 'waiting',
                            'operation_id' => $operationId,
                            'occurred_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $this->audit->append(AuditEvent::fromContext(
                            $context,
                            'operator.queue-admission.created',
                            'operator',
                            'success',
                            $now,
                            'queue-admission',
                            $queueAdmissionId,
                            metadata: [
                                'ticket_id' => $ticketId,
                                'ticket_number' => $number,
                                'schedule_id' => $scheduleId,
                                'operator_site_id' => $site->operator_site_id,
                                'queue_class' => 'advance',
                                'stage' => 'basic_examination',
                                'state' => 'waiting',
                                'ready_at_utc' => $now->format(DATE_ATOM),
                            ],
                        ));
                        $this->outbox->record(new VersionedDomainEvent(
                            LocalId::fromString($queueAdmissionId),
                            'operator.queue-admission-created',
                            1,
                            $now,
                            [
                                'queue_admission_id' => $queueAdmissionId,
                                'ticket_id' => $ticketId,
                                'ticket_number' => $number,
                                'schedule_id' => $scheduleId,
                                'operator_site_id' => $site->operator_site_id,
                                'queue_class' => 'advance',
                                'stage' => 'basic_examination',
                                'state' => 'waiting',
                                'ready_at' => $now->format(DATE_ATOM),
                            ],
                            LocalId::fromString($queueAdmissionId),
                            $context->operationId,
                        ));
                    }

                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.paper-ticket.issued',
                        'operator',
                        'success',
                        $now,
                        'ticket',
                        $ticketId,
                        metadata: [
                            'case_id' => $caseId,
                            'schedule_id' => $scheduleId,
                            'operator_site_id' => $site->operator_site_id,
                            'operator_id' => $profileId,
                            'ticket_number' => $number,
                            'issued_at_utc' => $now->format(DATE_ATOM),
                        ],
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.paper-ticket-issued',
                        1,
                        $now,
                        [
                            'ticket_id' => $ticketId,
                            'schedule_id' => $scheduleId,
                            'operator_site_id' => $site->operator_site_id,
                            'operator_id' => $profileId,
                            'ticket_number' => $number,
                            'issued_at' => $now->format(DATE_ATOM),
                        ],
                        LocalId::fromString($ticketId),
                        $context->operationId,
                    ));

                    $ticket = $this->ticketForId($ticketId, $site, $profileId);
                    if ($ticket === null) {
                        throw new OperatorException('ticket_failure', 'The paper ticket could not be loaded after issue.');
                    }

                    return $this->ticketResult($ticket);
                },
            )->result;
        } catch (IdempotencyConflict|OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('ticket_failure', 'The paper ticket could not be issued.', $exception);
        }
    }

    /** @return array<string, mixed> */
    public function show(string $ticketId): array
    {
        [$site, $profileId] = $this->currentSite();
        $ticket = $this->ticketForId($ticketId, $site, $profileId);
        if ($ticket === null) {
            throw new OperatorException('ticket_unavailable', 'The paper ticket is unavailable.');
        }

        return $this->ticketResult($ticket);
    }

    /** @return array<string, mixed> */
    public function reprint(string $ticketId, string $operationId): array
    {
        [$site, $profileId] = $this->currentSite();
        $operationId = $this->operation($operationId);
        $ticket = $this->ticketForId($ticketId, $site, $profileId);
        if ($ticket === null) {
            throw new OperatorException('ticket_unavailable', 'The paper ticket is unavailable.');
        }
        $payload = [
            'ticket_id' => $ticketId,
            'operator_profile_id' => $profileId,
            'operator_site_id' => (string) $site->operator_site_id,
        ];
        $context = $this->authorization->current(self::REPRINT_PURPOSE);

        try {
            return $this->idempotency->run(
                $operationId,
                self::REPRINT_PURPOSE,
                $payload,
                function () use ($ticketId, $site, $profileId, $context): array {
                    $ticket = $this->ticketForId($ticketId, $site, $profileId);
                    if ($ticket === null) {
                        throw new OperatorException('ticket_unavailable', 'The paper ticket is unavailable.');
                    }
                    $now = $this->clock->now();
                    $this->audit->append(AuditEvent::fromContext(
                        $context,
                        'operator.paper-ticket.reprint-requested',
                        'operator',
                        'success',
                        $now,
                        'ticket',
                        $ticketId,
                        metadata: [
                            'schedule_id' => (string) $ticket->member_schedule_id,
                            'operator_site_id' => $site->operator_site_id,
                            'operator_id' => $profileId,
                            'ticket_number' => (string) $ticket->ticket_number,
                            'requested_at_utc' => $now->format(DATE_ATOM),
                        ],
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.paper-ticket-reprint-requested',
                        1,
                        $now,
                        [
                            'ticket_id' => $ticketId,
                            'schedule_id' => (string) $ticket->member_schedule_id,
                            'operator_site_id' => $site->operator_site_id,
                            'operator_id' => $profileId,
                            'ticket_number' => (string) $ticket->ticket_number,
                            'requested_at' => $now->format(DATE_ATOM),
                        ],
                        LocalId::fromString($ticketId),
                        $context->operationId,
                    ));

                    return $this->ticketResult($ticket);
                },
            )->result;
        } catch (IdempotencyConflict|OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('ticket_failure', 'The paper ticket reprint could not be requested.', $exception);
        }
    }

    /** @return array{0: object, 1: string} */
    private function currentSite(): array
    {
        $identity = $this->authorization->identity();
        $site = $this->authorization->portalSite($identity);

        return [$site, (string) $identity['profile']->getKey()];
    }

    /** @return array{0: array<string, mixed>, 1: object, 2: object} */
    private function matchedCase(string $caseId): array
    {
        $identity = $this->authorization->identity();
        $site = $this->authorization->portalSite($identity);
        $caseId = trim($caseId);
        if (! Str::isUuid($caseId)) {
            throw new OperatorException('ticket_unavailable', 'The check-in case is unavailable.');
        }
        $case = DB::table('operator_identity_verifications')
            ->where('id', $caseId)
            ->where('operator_site_id', $site->getKey())
            ->where('operator_profile_id', $identity['profile']->getKey())
            ->whereNull('active_claim_operator_profile_id')
            ->whereIn('state', ['matched', 'nonclinical_validation'])
            ->first();
        if ($case === null) {
            throw new OperatorException('ticket_unavailable', 'Only an eligible identity case can issue a paper ticket.');
        }
        if ($case->state === 'nonclinical_validation') {
            $memberId = DB::table('bookings')->where('id', $case->booking_id)->value('member_id');
            if (! is_string($memberId) || ! $this->memberAttendance->isExactNonclinicalValidationMember($memberId)) {
                throw new OperatorException('ticket_unavailable', 'Only the canonical validation Member can issue a paper ticket.');
            }
        }

        return [$identity, $site, $case];
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

    private function operation(string $operationId): string
    {
        $operationId = trim($operationId);
        if (! Str::isUuid($operationId)) {
            throw new OperatorException('ticket_invalid', 'A valid ticket operation is required.');
        }

        return $operationId;
    }

    private function normalizeTicketNumber(string $ticketNumber): string
    {
        $ticketNumber = strtoupper(trim($ticketNumber));
        if ($ticketNumber === '' || strlen($ticketNumber) > 32 || preg_match('/\A[A-Z0-9-]+\z/D', $ticketNumber) !== 1) {
            throw new OperatorException('ticket_invalid', 'Ticket number must contain only letters, numbers, and hyphens up to 32 characters.');
        }

        return $ticketNumber;
    }

    private function nextTicketNumber(string $operatorSiteId, string $scheduleId): string
    {
        $number = DB::table('operator_paper_tickets')
            ->where('operator_site_id', $operatorSiteId)
            ->where('member_schedule_id', $scheduleId)
            ->count() + 1;

        return 'T-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    private function ticketForBooking(string $bookingId, object $site, string $profileId): ?object
    {
        $ticket = DB::table('operator_paper_tickets')
            ->where('booking_id', $bookingId)
            ->where('operator_site_id', $site->getKey())
            ->first();
        if ($ticket === null || ! $this->assignments->isAssigned($profileId, (string) $ticket->member_schedule_id, $site->operator_site_id)) {
            return null;
        }

        return $this->ticketForId((string) $ticket->id, $site, $profileId);
    }

    private function ticketForId(string $ticketId, object $site, string $profileId): ?object
    {
        if (! Str::isUuid(trim($ticketId))) {
            return null;
        }
        $ticket = DB::table('operator_paper_tickets')
            ->join('operator_sites', 'operator_sites.id', '=', 'operator_paper_tickets.operator_site_id')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'operator_paper_tickets.member_schedule_id')
            ->where('operator_paper_tickets.id', trim($ticketId))
            ->where('operator_paper_tickets.operator_site_id', $site->getKey())
            ->select([
                'operator_paper_tickets.id',
                'operator_paper_tickets.member_schedule_id',
                'operator_paper_tickets.ticket_number',
                'operator_paper_tickets.issued_at',
                'operator_sites.operator_site_id',
                'operator_sites.display_name as site_name',
                'shift_schedules.starts_at as schedule_starts_at',
                'shift_schedules.ends_at as schedule_ends_at',
            ])
            ->first();
        if ($ticket === null || ! $this->assignments->isAssigned($profileId, (string) $ticket->member_schedule_id, $site->operator_site_id)) {
            return null;
        }

        return $ticket;
    }

    /** @return array{ticket_id: string, ticket_number: string, site_name: string, schedule_starts_at: string, schedule_ends_at: string, issued_at: string, status: string} */
    private function ticketResult(object $ticket): array
    {
        return [
            'ticket_id' => (string) $ticket->id,
            'ticket_number' => (string) $ticket->ticket_number,
            'site_name' => (string) $ticket->site_name,
            'schedule_starts_at' => (string) $ticket->schedule_starts_at,
            'schedule_ends_at' => (string) $ticket->schedule_ends_at,
            'issued_at' => (string) $ticket->issued_at,
            'status' => 'issued',
        ];
    }

    private function assertConsentNotWithdrawn(string $bookingId): void
    {
        $consent = DB::table('examination_consents')
            ->where('booking_id', $bookingId)
            ->first();
        if ($consent !== null && $consent->status === 'withdrawn') {
            throw new OperatorException('consent_withdrawn', 'Informed consent has been withdrawn. Paper ticket issue is blocked.');
        }

        $memberId = DB::table('bookings')->where('id', $bookingId)->value('member_id');
        if (is_string($memberId)) {
            $withdrawnMaster = DB::table('member_master_consents')
                ->where('member_id', $memberId)
                ->where('status', 'withdrawn')
                ->orderByDesc('consent_version')
                ->first();
            if ($withdrawnMaster !== null) {
                $hasNewerActive = DB::table('member_master_consents')
                    ->where('member_id', $memberId)
                    ->where('status', 'active')
                    ->where('consent_version', '>', $withdrawnMaster->consent_version)
                    ->exists();
                if (! $hasNewerActive) {
                    throw new OperatorException('consent_withdrawn', 'Informed consent has been withdrawn. Paper ticket issue is blocked.');
                }
            }
        }
    }
}
