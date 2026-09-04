<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Services\MedicalRecordNumberGenerator;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Operator\Domain\Models\OperatorEligibleShift;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Modules\Operator\Domain\Models\OperatorShiftAssignment;
use App\Modules\Operator\Domain\Models\OperatorSite;
use App\Modules\Operator\Domain\OperatorException;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class OperatorFieldOperationsService
{
    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private ProtectedIdentifierService $identifiers,
        private MedicalRecordNumberGenerator $mrn,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /** @return array<string, mixed> */
    public function createShift(
        string $operatorSiteId,
        string $startsAt,
        string $endsAt,
        int $quota = 100,
    ): array {
        $portal = $this->authorization->portal();
        $profileId = (string) $portal['profile']->getKey();

        // Cross-site isolation: Verify operator is assigned to this site
        $site = OperatorSite::query()
            ->where(function ($q) use ($operatorSiteId) {
                $q->where('operator_site_id', $operatorSiteId)
                  ->orWhere('id', $operatorSiteId);
            })
            ->where('active', true)
            ->first();

        if ($site === null) {
            throw new OperatorException('shift_site_unavailable', 'The specified operator site is unavailable.');
        }

        $isAssignedToSite = DB::table('operator_site_assignments')
            ->where('operator_profile_id', $profileId)
            ->where('operator_site_id', $site->getKey())
            ->where('active', true)
            ->exists();

        if (! $isAssignedToSite) {
            throw new OperatorException('shift_site_denied', 'The Operator is not authorized to create shifts for this site.');
        }

        $start = $this->instant($startsAt);
        $end = $this->instant($endsAt);
        if ($end <= $start) {
            throw new OperatorException('shift_schedule_invalid', 'Shift end time must be after start time.');
        }

        if ($quota < 1) {
            throw new OperatorException('shift_quota_invalid', 'Quota must be at least 1.');
        }

        $now = $this->clock->now();

        return DB::transaction(function () use ($portal, $profileId, $site, $operatorSiteId, $start, $end, $quota, $now): array {
            $siteRef = DB::table('examination_site_refs')
                ->where('operator_site_id', $site->operator_site_id)
                ->where('active', true)
                ->first();

            if ($siteRef === null) {
                throw new OperatorException('shift_site_unavailable', 'Examination site reference unavailable for site: '.$site->operator_site_id);
            }

            $service = DB::table('service_offerings')
                ->where('active', true)
                ->orderBy('id')
                ->first();

            if ($service === null) {
                throw new OperatorException('shift_service_unavailable', 'No active service offerings found.');
            }

            // Generate unique display reference
            $displayReference = 'JAD-'.Str::upper(Str::random(8));
            while (DB::table('shift_schedules')->where('display_reference', $displayReference)->exists()) {
                $displayReference = 'JAD-'.Str::upper(Str::random(8));
            }

            $scheduleId = (string) Str::uuid();
            DB::table('shift_schedules')->insert([
                'id' => $scheduleId,
                'examination_site_id' => (string) $siteRef->id,
                'service_offering_id' => (string) $service->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'quota' => $quota,
                'status' => 'open',
                'eligible_at' => $now,
                'display_reference' => $displayReference,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $eligibleShiftId = (string) Str::uuid();
            OperatorEligibleShift::query()->create([
                'id' => $eligibleShiftId,
                'member_schedule_id' => $scheduleId,
                'operator_site_id' => $site->operator_site_id,
                'schedule_starts_at' => $start,
                'schedule_ends_at' => $end,
                'confirmed_count_at_eligibility' => 0,
                'quota' => $quota,
                'event_version' => 1,
                'source_event_id' => (string) Str::uuid(),
                'eligible_at' => $now,
                'sync_status' => 'eligible',
            ]);

            $assignmentId = (string) Str::uuid();
            OperatorShiftAssignment::query()->create([
                'id' => $assignmentId,
                'operator_eligible_shift_id' => $eligibleShiftId,
                'operator_profile_id' => $profileId,
                'assigned_by_user_id' => (string) $portal['context']->actorId,
                'status' => 'active',
                'assigned_at' => $now,
                'revoked_at' => null,
                'reason' => 'field_shift_creation',
            ]);

            $this->audit->append(AuditEvent::fromContext(
                $portal['context'],
                'operator.field-shift.created',
                'operator',
                'success',
                $now,
                OperatorEligibleShift::class,
                $eligibleShiftId,
                metadata: [
                    'schedule_id' => $scheduleId,
                    'operator_site_id' => $operatorSiteId,
                    'starts_at' => $start->format(DATE_ATOM),
                    'ends_at' => $end->format(DATE_ATOM),
                    'quota' => $quota,
                    'display_reference' => $displayReference,
                ],
            ));

            $this->outbox->record(new VersionedDomainEvent(
                LocalId::fromString((string) Str::uuid()),
                'operator.field-shift-created',
                1,
                $now,
                [
                    'schedule_id' => $scheduleId,
                    'eligible_shift_id' => $eligibleShiftId,
                    'operator_site_id' => $operatorSiteId,
                    'operator_profile_id' => $profileId,
                    'display_reference' => $displayReference,
                ],
                LocalId::fromString($eligibleShiftId),
                $portal['context']->operationId,
            ));

            return [
                'schedule_id' => $scheduleId,
                'eligible_shift_id' => $eligibleShiftId,
                'display_reference' => $displayReference,
                'operator_site_id' => $operatorSiteId,
                'site_name' => $site->display_name,
                'starts_at' => $start->format(DATE_ATOM),
                'ends_at' => $end->format(DATE_ATOM),
                'quota' => $quota,
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    public function searchMembers(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $builder = DB::table('members');

        // Check if query could be NIK
        if (preg_match('/\A\d{16}\z/', $query) === 1) {
            $digest = $this->identifiers->lookupDigest($query);
            $builder->where('nik_lookup_digest', $digest);
        } else {
            $builder->where(function ($q) use ($query): void {
                $q->where('medical_record_number', 'like', '%'.$query.'%')
                    ->orWhere('name', 'like', '%'.$query.'%')
                    ->orWhere('phone', 'like', '%'.$query.'%');
            });
        }

        $results = $builder->limit(20)->get();

        return $results->map(function (object $m): array {
            return [
                'member_id' => (string) $m->id,
                'medical_record_number' => (string) $m->medical_record_number,
                'name' => (string) $m->name,
                'birth_date' => (string) $m->birth_date,
                'administrative_gender' => (string) $m->administrative_gender,
                'phone' => (string) ($m->phone ?? ''),
                'affiliation' => (string) ($m->affiliation ?? ''),
                'office_location' => (string) ($m->office_location ?? ''),
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function addExistingMemberToShift(
        string $memberId,
        string $scheduleId,
        string $operationId,
    ): array {
        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();

        if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('shift_assignment_denied', 'The Operator is not assigned to this shift.');
        }

        $member = DB::table('members')->where('id', $memberId)->first();
        if ($member === null) {
            throw new OperatorException('member_unavailable', 'The Member record was not found.');
        }

        $schedule = DB::table('shift_schedules')->where('id', $scheduleId)->first();
        if ($schedule === null) {
            throw new OperatorException('shift_unavailable', 'The shift schedule was not found.');
        }

        $service = DB::table('service_offerings')->where('id', $schedule->service_offering_id)->first();
        if ($service === null) {
            throw new OperatorException('service_unavailable', 'The service offering was not found.');
        }

        $siteRef = DB::table('examination_site_refs')->where('id', $schedule->examination_site_id)->first();
        if ($siteRef === null) {
            throw new OperatorException('site_unavailable', 'The examination site reference was not found.');
        }

        $rate = DB::table('point_exchange_rates')->orderByDesc('created_at')->first();
        $now = $this->clock->now();

        return DB::transaction(function () use (
            $portal, $site, $profileId, $member, $schedule, $service, $siteRef, $rate, $operationId, $now
        ): array {
            // Check if member already has a booking for this shift
            $existingBooking = DB::table('bookings')
                ->where('shift_schedule_id', $schedule->id)
                ->where('member_id', $member->id)
                ->whereIn('status', ['confirmed', 'arrived', 'checked_in', 'in_progress'])
                ->first();

            $bookingId = $existingBooking !== null ? (string) $existingBooking->id : (string) Str::uuid();

            if ($existingBooking === null) {
                DB::table('bookings')->insert([
                    'id' => $bookingId,
                    'member_id' => (string) $member->id,
                    'shift_schedule_id' => (string) $schedule->id,
                    'service_offering_id' => (string) $service->id,
                    'examination_site_id_snapshot' => (string) $siteRef->id,
                    'booking_type' => 'field',
                    'funding_source' => 'operational',
                    'status' => 'arrived',
                    'service_code_snapshot' => $service->code,
                    'point_cost_snapshot' => '0.0000',
                    'point_exchange_rate_id' => $rate !== null ? (string) $rate->id : (string) Str::uuid(),
                    'includes_ai_snapshot' => (bool) $service->includes_ai,
                    'includes_doctor_snapshot' => (bool) $service->includes_doctor,
                    'site_code_snapshot' => $siteRef->code,
                    'site_name_snapshot' => $siteRef->display_name,
                    'site_timezone_snapshot' => $siteRef->timezone,
                    'created_at' => $now,
                    'confirmed_at' => $now,
                    'updated_at' => $now,
                ]);

                // Record local imaging order
                DB::table('local_imaging_orders')->insert([
                    'id' => (string) Str::uuid(),
                    'booking_id' => $bookingId,
                    'member_id' => (string) $member->id,
                    'shift_schedule_id' => (string) $schedule->id,
                    'examination_site_id' => (string) $siteRef->id,
                    'service_code_snapshot' => $service->code,
                    'status' => 'authored',
                    'authored_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                // Ensure status is at least arrived
                if ($existingBooking->status === 'confirmed') {
                    DB::table('bookings')->where('id', $bookingId)->update([
                        'status' => 'arrived',
                        'updated_at' => $now,
                    ]);
                }
            }

            // Ensure arrival record exists
            $existingArrival = DB::table('operator_arrivals')
                ->where('booking_id', $bookingId)
                ->first();

            $arrivalId = $existingArrival !== null ? (string) $existingArrival->id : (string) Str::uuid();
            if ($existingArrival === null) {
                DB::table('operator_arrivals')->insert([
                    'id' => $arrivalId,
                    'booking_id' => $bookingId,
                    'member_schedule_id' => (string) $schedule->id,
                    'operator_site_id' => (string) $site->getKey(),
                    'operator_profile_id' => $profileId,
                    'occurrence_at' => $now,
                    'recorded_at' => $now,
                    'operation_id' => $operationId,
                    'source' => 'operator.portal.field',
                    'status' => 'recorded',
                ]);
            }

            // Ensure identity verification case exists and is matched
            $existingCase = DB::table('operator_identity_verifications')
                ->where('booking_id', $bookingId)
                ->first();

            $caseId = $existingCase !== null ? (string) $existingCase->id : (string) Str::uuid();
            if ($existingCase === null) {
                DB::table('operator_identity_verifications')->insert([
                    'id' => $caseId,
                    'arrival_id' => $arrivalId,
                    'booking_id' => $bookingId,
                    'member_schedule_id' => (string) $schedule->id,
                    'operator_site_id' => (string) $site->getKey(),
                    'operator_profile_id' => $profileId,
                    'state' => 'matched',
                    'started_at' => $now,
                    'decided_at' => $now,
                    'operation_id' => $operationId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->audit->append(AuditEvent::fromContext(
                $portal['context'],
                'operator.member.admitted-to-shift',
                'operator',
                'success',
                $now,
                Booking::class,
                $bookingId,
                metadata: [
                    'schedule_id' => (string) $schedule->id,
                    'operator_site_id' => $site->operator_site_id,
                    'case_id' => $caseId,
                ],
            ));

            return [
                'booking_id' => $bookingId,
                'case_id' => $caseId,
                'arrival_id' => $arrivalId,
                'schedule_id' => (string) $schedule->id,
                'member_id' => (string) $member->id,
                'member_name' => (string) $member->name,
                'medical_record_number' => (string) $member->medical_record_number,
            ];
        });
    }

    /**
     * On-the-spot registration capturing all 7 required fields:
     * 1. Full name
     * 2. Gender
     * 3. NIK
     * 4. Birth date
     * 5. Phone number
     * 6. Affiliation/organization name
     * 7. Office location
     *
     * Resolves existing member if NIK already exists (deduplication) without creating duplicate.
     * Internal MRN is always generated; NIK never becomes MRN or PatientID.
     *
     * @param array{
     *     name: string,
     *     administrative_gender: string,
     *     nik: string,
     *     birth_date: string,
     *     phone: string,
     *     affiliation: string,
     *     office_location: string,
     * } $data
     * @return array<string, mixed>
     */
    public function registerAndAdmitMember(
        array $data,
        string $scheduleId,
        string $operationId,
    ): array {
        $this->validateRegistrationFields($data);

        $portal = $this->authorization->portal();
        $site = $this->authorization->portalSite($portal);
        $profileId = (string) $portal['profile']->getKey();

        if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('shift_assignment_denied', 'The Operator is not assigned to this shift.');
        }

        $nikRaw = trim($data['nik']);
        $digest = $this->identifiers->lookupDigest($nikRaw);
        $now = $this->clock->now();

        $memberId = DB::transaction(function () use ($data, $nikRaw, $digest, $portal, $now): string {
            // Deduplication check by NIK digest
            $existingMember = DB::table('members')
                ->where('nik_lookup_digest', $digest)
                ->lockForUpdate()
                ->first();

            if ($existingMember !== null) {
                // Member already exists. Update non-immutable contact and affiliation fields.
                DB::table('members')->where('id', $existingMember->id)->update([
                    'name' => trim($data['name']),
                    'administrative_gender' => trim($data['administrative_gender']),
                    'phone' => trim($data['phone']),
                    'birth_date' => trim($data['birth_date']),
                    'affiliation' => trim($data['affiliation']),
                    'office_location' => trim($data['office_location']),
                    'updated_at' => $now,
                ]);

                $this->audit->append(AuditEvent::fromContext(
                    $portal['context'],
                    'operator.member.resolved-existing',
                    'operator',
                    'success',
                    $now,
                    Member::class,
                    (string) $existingMember->id,
                    metadata: [
                        'medical_record_number' => $existingMember->medical_record_number,
                    ],
                ));

                return (string) $existingMember->id;
            }

            // Create new User and Member
            $userId = (string) Str::uuid();
            $memberId = (string) Str::uuid();
            $mrn = $this->mrn->generate();

            // Invariant check: NIK must never be MRN
            if ($mrn === $nikRaw) {
                throw new OperatorException('registration_invariant_failed', 'Security invariant violated: MRN matches NIK.');
            }

            $protectedNik = $this->identifiers->protect($nikRaw);

            DB::table('users')->insert([
                'id' => $userId,
                'email' => null,
                'password' => Hash::make(Str::random(32)),
                'account_status' => 'active',
                'login_enabled' => false,
                'must_change_password' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('members')->insert([
                'id' => $memberId,
                'user_id' => $userId,
                'family_id' => null,
                'medical_record_number' => $mrn,
                'identity_status' => 'verified',
                'identity_document_type' => 'nik',
                'encrypted_nik' => $protectedNik['encrypted_display'],
                'nik_lookup_digest' => $digest,
                'name' => trim($data['name']),
                'birth_date' => trim($data['birth_date']),
                'administrative_gender' => trim($data['administrative_gender']),
                'registration_source' => 'operator_field',
                'phone' => trim($data['phone']),
                'affiliation' => trim($data['affiliation']),
                'office_location' => trim($data['office_location']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->audit->append(AuditEvent::fromContext(
                $portal['context'],
                'operator.member.registered',
                'operator',
                'success',
                $now,
                Member::class,
                $memberId,
                metadata: [
                    'medical_record_number' => $mrn,
                    'registration_source' => 'operator_field',
                ],
            ));

            return $memberId;
        });

        // Now admit member directly to shift
        return $this->addExistingMemberToShift($memberId, $scheduleId, $operationId);
    }

    /** @param array<string, mixed> $data */
    private function validateRegistrationFields(array $data): void
    {
        $required = [
            'name',
            'administrative_gender',
            'nik',
            'birth_date',
            'phone',
            'affiliation',
            'office_location',
        ];

        foreach ($required as $field) {
            if (! isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new OperatorException('registration_invalid', "Field '{$field}' is mandatory for field registration.");
            }
        }

        $nik = trim((string) $data['nik']);
        if (preg_match('/\A\d{8,20}\z/', $nik) !== 1) {
            throw new OperatorException('registration_invalid', 'NIK must be numeric and between 8 and 20 digits.');
        }

        $gender = trim((string) $data['administrative_gender']);
        if (! in_array($gender, ['male', 'female', 'other', 'administrative_male', 'administrative_female'], true)) {
            throw new OperatorException('registration_invalid', 'Invalid gender specified.');
        }

        $birthDate = trim((string) $data['birth_date']);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $birthDate) !== 1) {
            throw new OperatorException('registration_invalid', 'Birth date must be in YYYY-MM-DD format.');
        }
    }

    private function instant(string $value): DateTimeImmutable
    {
        $raw = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $raw) === 1) {
            $raw .= 'T00:00:00Z';
        }

        return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'));
    }
}
