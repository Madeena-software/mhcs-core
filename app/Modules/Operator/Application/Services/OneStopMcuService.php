<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Time\Clock;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OneStopMcuService
{
    public const SAVE_PURPOSE = 'operator.one-stop-mcu.save';

    public const PDF_PURPOSE = 'operator.one-stop-mcu.pdf';

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private AuditStore $audit,
        private ProtectedIdentifierService $identifiers,
        private Clock $clock,
    ) {}

    /** @return list<array<string, mixed>> */
    public function worklist(): array
    {
        [$portal, $site] = $this->scope();
        $profileId = (string) $portal['profile']->getKey();

        return DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('admissions.operator_site_id', $site->getKey())
            ->where('member_sites.operator_site_id', $site->operator_site_id)
            ->where('admissions.queue_class', 'advance')
            ->where('admissions.stage', 'basic_examination')
            ->whereIn('admissions.state', ['waiting', 'called', 'in_service'])
            ->where('bookings.status', 'checked_in')
            ->where(function ($query) use ($profileId): void {
                $query->whereNull('admissions.operator_profile_id')->orWhere('admissions.operator_profile_id', $profileId);
            })
            ->whereExists(function ($query) use ($profileId, $site): void {
                $query->selectRaw('1')->from('operator_shift_assignments as assignments')
                    ->join('operator_eligible_shifts as eligible', 'eligible.id', '=', 'assignments.operator_eligible_shift_id')
                    ->whereColumn('eligible.member_schedule_id', 'admissions.member_schedule_id')
                    ->whereColumn('eligible.operator_site_id', 'member_sites.operator_site_id')
                    ->where('assignments.operator_profile_id', $profileId)
                    ->where('assignments.status', 'active')
                    ->where('eligible.sync_status', 'eligible')
                    ->where('eligible.operator_site_id', $site->operator_site_id);
            })
            ->select([
                'admissions.id as admission_id',
                'tickets.ticket_number',
                'members.name as member_name',
                'members.medical_record_number',
                'schedules.display_reference as schedule_reference',
                'admissions.state',
            ])
            ->selectRaw('exists (select 1 from operator_mcu_examinations where operator_queue_admission_id = admissions.id) as has_mcu_exam')
            ->orderBy('tickets.issued_at')
            ->get()
            ->map(static fn (object $row): array => [
                'admission_id' => (string) $row->admission_id,
                'ticket_number' => (string) $row->ticket_number,
                'member_name' => (string) $row->member_name,
                'medical_record_number' => (string) $row->medical_record_number,
                'schedule_reference' => (string) $row->schedule_reference,
                'state' => (string) $row->state,
                'has_mcu_exam' => (bool) $row->has_mcu_exam,
            ])->all();
    }

    /** @return array<string, mixed> */
    public function form(string $admissionId): array
    {
        [$portal, $site] = $this->scope();
        $admission = $this->admission($admissionId, $portal, $site);
        $exam = DB::table('operator_mcu_examinations')->where('operator_queue_admission_id', $admissionId)->first();

        return [
            'admission_id' => $admissionId,
            'member' => [
                'name' => (string) $admission->member_name,
                'birth_date' => (string) $admission->birth_date,
                'sex' => (string) $admission->administrative_gender,
                'medical_record_number' => (string) $admission->medical_record_number,
            ],
            'site_name' => (string) $site->display_name,
            'site_timezone' => (string) $site->timezone,
            'examiner_name' => (string) ($portal['profile']->display_name ?: $portal['user']->getFilamentName()),
            'existing_exam' => $exam !== null,
        ];
    }

    /** @param array<string, mixed> $input */
    public function record(string $admissionId, array $input): string
    {
        [$portal, $site] = $this->scope();
        $profileId = (string) $portal['profile']->getKey();
        $operationId = trim((string) ($input['operation_id'] ?? ''));
        if (! Str::isUuid($admissionId) || ! Str::isUuid($operationId)) {
            throw new OperatorException('mcu_forbidden', 'The MCU examination is unavailable.');
        }

        $data = $this->normalize($input, (string) $site->timezone);
        $context = $this->authorization->current(self::SAVE_PURPOSE);
        try {
            $examId = DB::transaction(function () use ($admissionId, $data, $profileId, $site, $operationId, $portal, $context): string {
                $admission = $this->admission($admissionId, $portal, $site, lock: true);
                if (DB::table('operator_mcu_examinations')->where('operator_queue_admission_id', $admissionId)->exists()) {
                    throw new OperatorException('mcu_conflict', 'An MCU examination is already saved for this visit.');
                }

                $now = $this->clock->now();
                $id = (string) Str::uuid();
                DB::table('operator_mcu_examinations')->insert([
                    'id' => $id,
                    'operator_queue_admission_id' => $admissionId,
                    'member_id' => (string) $admission->member_id,
                    'booking_id' => (string) $admission->booking_id,
                    'member_schedule_id' => (string) $admission->member_schedule_id,
                    'operator_profile_id' => $profileId,
                    'operator_site_id' => (string) $site->getKey(),
                    ...$data,
                    'operation_id' => $operationId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->audit->append(AuditEvent::fromContext(
                    $context,
                    'operator.one-stop-mcu.saved',
                    'operator',
                    'success',
                    $now,
                    'operator-mcu-examination',
                    $id,
                    metadata: [
                        'booking_id' => (string) $admission->booking_id,
                        'schedule_id' => (string) $admission->member_schedule_id,
                        'operator_profile_id' => $profileId,
                        'operator_site_id' => (string) $site->getKey(),
                    ],
                ));

                return $id;
            });
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('mcu_conflict', 'The MCU examination could not be saved.', $exception);
        }

        return $examId;
    }

    /** @return array<string, mixed> */
    public function report(string $admissionId): array
    {
        [$portal, $site] = $this->scope();
        $this->admission($admissionId, $portal, $site);
        $exam = DB::table('operator_mcu_examinations as exams')
            ->join('members', 'members.id', '=', 'exams.member_id')
            ->join('operator_profiles', 'operator_profiles.id', '=', 'exams.operator_profile_id')
            ->where('exams.operator_queue_admission_id', $admissionId)
            ->select(['exams.*', 'members.name as member_name', 'members.birth_date', 'members.administrative_gender', 'members.encrypted_nik', 'operator_profiles.display_name as examiner_name'])
            ->first();
        if ($exam === null || $exam->encrypted_nik === null) {
            throw new OperatorException('mcu_not_found', 'The saved MCU examination is unavailable.');
        }
        $participantNik = $this->identifiers->display((string) $exam->encrypted_nik);
        unset($exam->encrypted_nik);

        $at = CarbonImmutable::parse((string) $exam->examined_at, 'UTC')->setTimezone((string) $site->timezone);
        $this->audit->append(AuditEvent::fromContext(
            $this->authorization->current(self::PDF_PURPOSE),
            'operator.one-stop-mcu.pdf-accessed',
            'operator',
            'success',
            $this->clock->now(),
            'operator-mcu-examination',
            (string) $exam->id,
            metadata: ['operator_profile_id' => (string) $portal['profile']->getKey(), 'operator_site_id' => (string) $site->getKey()],
        ));

        return [
            'exam' => $exam,
            'participant_nik' => $participantNik,
            'site_name' => (string) $site->display_name,
            'site_address' => (string) ($site->address_line ?? ''),
            'examined_at_local' => $at->format('d-m-Y H:i'),
            'birth_date' => CarbonImmutable::parse((string) $exam->birth_date)->format('d-m-Y'),
            'sex' => (string) $exam->administrative_gender,
            'examiner_name' => (string) $exam->examiner_name,
        ];
    }

    /** @return array<string, mixed> */
    private function scope(): array
    {
        try {
            $portal = $this->authorization->portal();
            $site = $this->authorization->portalSite($portal);

            return [$portal, $site];
        } catch (Throwable $exception) {
            throw new OperatorException('mcu_forbidden', 'The MCU examination is unavailable.', $exception);
        }
    }

    private function admission(string $id, array $portal, object $site, bool $lock = false): object
    {
        if (! Str::isUuid($id)) {
            throw new OperatorException('mcu_forbidden', 'The MCU examination is unavailable.');
        }
        $profileId = (string) $portal['profile']->getKey();
        $query = DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->join('shift_schedules as schedules', 'schedules.id', '=', 'admissions.member_schedule_id')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->where('admissions.id', $id)
            ->where('admissions.operator_site_id', $site->getKey())
            ->where('member_sites.operator_site_id', $site->operator_site_id)
            ->where('admissions.queue_class', 'advance')
            ->where('admissions.stage', 'basic_examination')
            ->whereIn('admissions.state', ['waiting', 'called', 'in_service'])
            ->where('bookings.status', 'checked_in')
            ->where(function ($query) use ($profileId): void {
                $query->whereNull('admissions.operator_profile_id')->orWhere('admissions.operator_profile_id', $profileId);
            })
            ->select([
                'admissions.*',
                'tickets.booking_id',
                'bookings.member_id',
                'members.name as member_name',
                'members.birth_date',
                'members.administrative_gender',
                'members.medical_record_number',
            ]);
        $admission = ($lock ? $query->lockForUpdate() : $query)->first();

        if ($admission === null || ! $this->assignments->isAssigned($profileId, (string) $admission->member_schedule_id, $site->operator_site_id)) {
            throw new OperatorException('mcu_forbidden', 'The MCU examination is unavailable.');
        }

        return $admission;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalize(array $input, string $timezone): array
    {
        $number = static function (string $key, bool $required = true, bool $positive = false) use ($input): ?string {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '') {
                if ($required) {
                    throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
                }

                return null;
            }
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value > 999999.99 || ($positive ? (float) $value <= 0 : (float) $value < 0)) {
                throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
            }

            return $value;
        };

        $fasting = (string) ($input['fasting_status'] ?? '');
        if (! in_array($fasting, ['fasting', 'non_fasting'], true)) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }
        $duration = $number('fasting_duration_hours', required: $fasting === 'fasting');
        if ($duration !== null && (float) $duration > 9999.99) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }
        $lastMeal = trim((string) ($input['last_meal_at'] ?? ''));
        if ($fasting === 'non_fasting' && $lastMeal === '') {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }
        if ($lastMeal !== '' && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $lastMeal) !== 1) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }
        if ($fasting === 'fasting' && $lastMeal !== '') {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }

        $pef = [
            $number('pef_attempt_i', positive: true),
            $number('pef_attempt_ii', positive: true),
            $number('pef_attempt_iii', positive: true),
        ];
        $height = (float) $number('height_cm', positive: true);
        $weight = (float) $number('weight_kg', positive: true);
        $bmi = round($weight / (($height / 100) ** 2), 2);
        if (! is_finite($bmi) || $bmi > 999999.99) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }

        try {
            $examinedAt = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', (string) ($input['examined_at'] ?? ''), new DateTimeZone($timezone));
        } catch (Throwable $exception) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.', $exception);
        }
        if ($examinedAt === false || $examinedAt->format('Y-m-d\TH:i') !== (string) $input['examined_at']) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        if (mb_strlen($notes) > 5000) {
            throw new OperatorException('mcu_invalid', 'The MCU examination values are invalid.');
        }

        return [
            'examined_at' => $examinedAt->utc()->format('Y-m-d H:i:s'),
            'systolic_bp_mmhg' => $number('systolic_bp', positive: true),
            'diastolic_bp_mmhg' => $number('diastolic_bp', positive: true),
            'weight_kg' => (string) $weight,
            'height_cm' => (string) $height,
            'temperature_c' => $number('temperature_c', positive: true),
            'bmi' => (string) $bmi,
            'glucose_mg_dl' => $number('glucose_mg_dl'),
            'total_cholesterol_mg_dl' => $number('total_cholesterol_mg_dl'),
            'uric_acid_mg_dl' => $number('uric_acid_mg_dl'),
            'fasting_status' => $fasting,
            'fasting_duration_hours' => $duration,
            'last_meal_at' => $lastMeal === '' ? null : $lastMeal.':00',
            'pef_attempt_i' => $pef[0],
            'pef_attempt_ii' => $pef[1],
            'pef_attempt_iii' => $pef[2],
            'pef_highest_value' => max(array_map('floatval', $pef)),
            'notes' => $notes === '' ? null : $notes,
        ];
    }
}
