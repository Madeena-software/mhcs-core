<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorVitalSignsContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class Mvp04VitalSignsService implements OperatorVitalSignsContract
{
    private const OPERATOR_PERMISSION = 'operator.portal.access';

    private const VALUE_FIELDS = [
        'systolic_bp',
        'diastolic_bp',
        'temperature',
        'height',
        'weight',
    ];

    private const MISSING_REASONS = ['unavailable', 'refused', 'not_applicable'];

    public function __construct(
        private TrustedOperatorSiteContextResolver $trustedSite,
        private Clock $clock,
    ) {}

    /**
     * @param  array{
     *     systolic_bp_value: ?string,
     *     systolic_bp_missing_reason: ?string,
     *     diastolic_bp_value: ?string,
     *     diastolic_bp_missing_reason: ?string,
     *     temperature_value: ?string,
     *     temperature_missing_reason: ?string,
     *     height_value: ?string,
     *     height_missing_reason: ?string,
     *     weight_value: ?string,
     *     weight_missing_reason: ?string,
     *     bmi_missing_reason: ?string
     * }  $data
     * @return array{assessment_id: string, member_id: string, booking_id: string, schedule_id: string}
     */
    public function record(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $memberId,
        string $bookingId,
        string $scheduleId,
        array $data,
        string $occurredAt,
    ): array {
        $this->assertOperatorContext($context, $operatorSiteId);
        $data = $this->normalize($data);
        $occurred = $this->instant($occurredAt);
        $site = DB::table('examination_site_refs')
            ->where('operator_site_id', $operatorSiteId)
            ->where('active', true)
            ->first();
        $booking = DB::table('bookings')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
            ->where('bookings.id', $bookingId)
            ->where('bookings.member_id', $memberId)
            ->where('bookings.shift_schedule_id', $scheduleId)
            ->where('bookings.status', 'checked_in')
            ->where('bookings.examination_site_id_snapshot', $site?->id)
            ->where('shift_schedules.examination_site_id', $site?->id)
            ->lockForUpdate()
            ->first();

        if ($site === null || $booking === null) {
            throw new Mvp03Exception('The checked-in booking is unavailable.');
        }

        $assessmentId = (string) Str::uuid();
        $now = $this->clock->now();
        DB::table('member_vital_signs_assessments')->insert([
            'id' => $assessmentId,
            'member_id' => $memberId,
            'booking_id' => $bookingId,
            'member_schedule_id' => $scheduleId,
            'systolic_bp_value' => $data['systolic_bp_value'],
            'systolic_bp_unit' => 'mmHg',
            'systolic_bp_missing_reason' => $data['systolic_bp_missing_reason'],
            'diastolic_bp_value' => $data['diastolic_bp_value'],
            'diastolic_bp_unit' => 'mmHg',
            'diastolic_bp_missing_reason' => $data['diastolic_bp_missing_reason'],
            'temperature_value' => $data['temperature_value'],
            'temperature_unit' => '°C',
            'temperature_missing_reason' => $data['temperature_missing_reason'],
            'height_value' => $data['height_value'],
            'height_unit' => 'cm',
            'height_missing_reason' => $data['height_missing_reason'],
            'weight_value' => $data['weight_value'],
            'weight_unit' => 'kg',
            'weight_missing_reason' => $data['weight_missing_reason'],
            'bmi_value' => $data['bmi_value'],
            'bmi_unit' => 'kg/m²',
            'bmi_missing_reason' => $data['bmi_missing_reason'],
            'effective_at' => $occurred,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'assessment_id' => $assessmentId,
            'member_id' => $memberId,
            'booking_id' => $bookingId,
            'schedule_id' => $scheduleId,
        ];
    }

    /** @return array<string, ?string> */
    private function normalize(array $data): array
    {
        $normalized = [];
        foreach (self::VALUE_FIELDS as $field) {
            $value = $this->nullableString($data[$field.'_value'] ?? null);
            $reason = $this->nullableString($data[$field.'_missing_reason'] ?? null);
            $hasValue = $value !== null;
            $hasReason = $reason !== null;
            if (
                $hasValue === $hasReason
                || ($hasValue && ! is_numeric($value))
                || (
                    $hasValue
                    && in_array($field, ['height', 'weight'], true)
                    && (! is_finite((float) $value) || (float) $value <= 0)
                )
            ) {
                throw new Mvp03Exception('The vital-signs record is invalid.');
            }
            if ($hasReason && ! in_array($reason, self::MISSING_REASONS, true)) {
                throw new Mvp03Exception('The vital-signs record is invalid.');
            }
            $normalized[$field.'_value'] = $value;
            $normalized[$field.'_missing_reason'] = $reason;
        }

        $height = $normalized['height_value'];
        $weight = $normalized['weight_value'];
        $bmiReason = $this->nullableString($data['bmi_missing_reason'] ?? null);
        if ($height !== null && $weight !== null) {
            if ($bmiReason !== null || (float) $height === 0.0) {
                throw new Mvp03Exception('The vital-signs record is invalid.');
            }
            $normalized['bmi_value'] = (string) round((float) $weight / (((float) $height / 100) ** 2), 2);
            $normalized['bmi_missing_reason'] = null;
        } else {
            if ($bmiReason === null || ! in_array($bmiReason, self::MISSING_REASONS, true)) {
                throw new Mvp03Exception('The vital-signs record is invalid.');
            }
            $normalized['bmi_value'] = null;
            $normalized['bmi_missing_reason'] = $bmiReason;
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function instant(string $value): DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable(trim($value), new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new Mvp03Exception('The vital-signs occurrence time is invalid.', previous: $exception);
        }
    }

    private function assertOperatorContext(AuthenticatedContext $context, string $operatorSiteId): void
    {
        if (
            $context->actorId === null
            || $context->operationId === null
            || ! in_array('operator', $context->roles, true)
            || ! in_array(self::OPERATOR_PERMISSION, $context->permissions, true)
            || ! $this->trustedSite->matches($context, $operatorSiteId, self::OPERATOR_PERMISSION)
        ) {
            throw new Mvp03Exception('A trusted Operator vital-signs context is required.');
        }
    }
}
