<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Enums\PointEntryType;
use App\Modules\Member\Domain\Enums\PointRateStatus;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use App\Shared\Validation\NonclinicalValidationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp03PointService
{
    public function __construct(
        private AuditStore $audit,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
        private MemberContextResolver $members,
    ) {}

    public function ensureInitialLocalRate(?string $adminId = null): string
    {
        return DB::transaction(function () use ($adminId): string {
            $active = DB::table('point_exchange_rates')->where('status', PointRateStatus::Active->value)->lockForUpdate()->get();
            if ($active->count() > 1) {
                throw new Mvp03Exception('More than one active point rate exists.');
            }
            if ($active->isNotEmpty()) {
                if ((int) $active->first()->rupiah_per_point !== 10000) {
                    throw new Mvp03Exception('The existing active local point rate is inconsistent.');
                }

                return (string) $active->first()->id;
            }

            $id = (string) Str::uuid();
            $now = $this->clock->now();
            DB::table('point_exchange_rates')->insert([
                'id' => $id,
                'rupiah_per_point' => 10000,
                'status' => PointRateStatus::Active->value,
                'effective_at' => $now,
                'configured_by_admin_id' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->record('member.point-rate.bootstrap', 'point-rate', $id, ['rupiah_per_point' => 10000]);

            return $id;
        });
    }

    public function personalBalance(string $memberId): PointAmount
    {
        $balance = PointAmount::zero();
        DB::table('point_ledger_entries')
            ->where('member_id', $memberId)
            ->where('funding_source', 'personal')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('point_delta')
            ->each(function (mixed $delta) use (&$balance): void {
                $balance = $balance->add(PointAmount::fromString((string) $delta));
            });

        return $balance;
    }

    public function creditPersonalForLocalTesting(string $memberId, string $amount, string $sourceReference): string
    {
        if (! app()->environment(['local', 'testing']) && ! (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            throw new Mvp03Exception('Synthetic point funding is limited to local and testing environments.');
        }
        $amount = PointAmount::fromString($amount);
        if (! $amount->isPositive() || trim($sourceReference) === '') {
            throw new Mvp03Exception('Synthetic point funding is invalid.');
        }

        return DB::transaction(function () use ($memberId, $amount, $sourceReference): string {
            DB::table('members')->where('id', $memberId)->lockForUpdate()->firstOrFail();
            $existing = DB::table('point_ledger_entries')->where('source_reference', $sourceReference)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->member_id !== $memberId
                    || PointAmount::fromString((string) $existing->point_delta)->compare($amount) !== 0
                    || $existing->funding_source !== 'personal') {
                    throw new Mvp03Exception('The existing synthetic funding record is inconsistent.');
                }

                return (string) $existing->id;
            }

            $id = (string) Str::uuid();
            DB::table('point_ledger_entries')->insert([
                'id' => $id,
                'member_id' => $memberId,
                'booking_id' => null,
                'funding_source' => 'personal',
                'entry_type' => PointEntryType::Credit->value,
                'point_delta' => (string) $amount,
                'source_reference' => $sourceReference,
                'reverses_id' => null,
                'created_at' => $this->clock->now(),
            ]);
            $this->record('member.point-funding.local', 'point-ledger-entry', $id, ['source_reference' => $sourceReference]);

            return $id;
        });
    }

    /** @return array{ledger_entry_id: string, point_cost: string, replayed: bool} */
    public function ensureNonclinicalValidationBookingFunding(string $scheduleId): array
    {
        $context = $this->context->current();
        if (! in_array('system', $context->roles, true) || $context->purpose !== 'member.nonclinical-validation.point-funding') {
            throw new Mvp03Exception('Nonclinical validation point funding requires a trusted purpose.');
        }
        if (! Str::isUuid(trim($scheduleId))) {
            throw new Mvp03Exception('The validation booking candidate is invalid.');
        }

        return DB::transaction(function () use ($scheduleId, $context): array {
            $markers = DB::table('member_external_identifiers')
                ->where('namespace', NonclinicalValidationContext::MARKER_NAMESPACE)
                ->where('value', NonclinicalValidationContext::KEY)
                ->lockForUpdate()
                ->get();
            if ($markers->count() !== 1) {
                throw new Mvp03Exception('The nonclinical validation Member state is ambiguous.');
            }

            $memberId = (string) $markers->first()->member_id;
            $member = Member::query()->whereKey($memberId)->lockForUpdate()->first();
            if ($member === null || ! $this->members->isExactNonclinicalValidationIdentity($member)) {
                throw new Mvp03Exception('The nonclinical validation Member state is inconsistent.');
            }

            $schedule = DB::table('shift_schedules')->where('id', trim($scheduleId))->lockForUpdate()->first();
            if ($schedule === null || $schedule->status !== 'open') {
                throw new Mvp03Exception('The validation booking candidate is unavailable.');
            }
            $site = DB::table('examination_site_refs')->where('id', $schedule->examination_site_id)->lockForUpdate()->first();
            $service = DB::table('service_offerings')->where('id', $schedule->service_offering_id)->lockForUpdate()->first();
            if ($site === null || ! $site->active || $service === null || ! $service->active) {
                throw new Mvp03Exception('The validation booking candidate is unavailable.');
            }

            try {
                $cost = PointAmount::fromString((string) $service->point_price);
            } catch (\Throwable $exception) {
                throw new Mvp03Exception('The validation booking candidate has an invalid point cost.', previous: $exception);
            }
            if (! $cost->isPositive()) {
                throw new Mvp03Exception('The validation booking candidate has an invalid point cost.');
            }

            $rates = DB::table('point_exchange_rates')
                ->where('status', PointRateStatus::Active->value)
                ->lockForUpdate()
                ->get();
            if ($rates->count() !== 1) {
                throw new Mvp03Exception('Exactly one active point rate is required.');
            }

            $sourceReference = 'nonclinical-validation:'.NonclinicalValidationContext::KEY.':booking-funding-v1';
            $sourceEntries = DB::table('point_ledger_entries')
                ->where('source_reference', $sourceReference)
                ->lockForUpdate()
                ->get();
            if ($sourceEntries->count() > 1 || ($sourceEntries->isNotEmpty() && (string) $sourceEntries->first()->member_id !== $memberId)) {
                throw new Mvp03Exception('The validation funding ledger state is inconsistent.');
            }

            $entries = DB::table('point_ledger_entries')
                ->where('member_id', $memberId)
                ->where('funding_source', 'personal')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $funding = $entries->where('source_reference', $sourceReference);
            if ($funding->count() > 1) {
                throw new Mvp03Exception('The validation funding ledger state is inconsistent.');
            }

            if ($funding->isEmpty()) {
                if ($entries->isNotEmpty()) {
                    throw new Mvp03Exception('The validation Member has unrelated personal point history.');
                }

                $ledgerEntryId = (string) Str::uuid();
                DB::table('point_ledger_entries')->insert([
                    'id' => $ledgerEntryId,
                    'member_id' => $memberId,
                    'booking_id' => null,
                    'funding_source' => 'personal',
                    'entry_type' => PointEntryType::Credit->value,
                    'point_delta' => (string) $cost,
                    'source_reference' => $sourceReference,
                    'reverses_id' => null,
                    'created_at' => $this->clock->now(),
                ]);
                $this->audit->append(AuditEvent::fromContext(
                    $context,
                    'member.point-funding.nonclinical-validation',
                    'member',
                    'success',
                    $this->clock->now(),
                    'point-ledger-entry',
                    $ledgerEntryId,
                    metadata: [
                        'validation_context' => NonclinicalValidationContext::KEY,
                        'nonclinical' => true,
                        'purpose' => 'booking_validation',
                    ],
                ));

                return [
                    'ledger_entry_id' => $ledgerEntryId,
                    'point_cost' => (string) $cost,
                    'replayed' => false,
                ];
            }

            $fundingEntry = $funding->first();
            if (
                (string) $fundingEntry->member_id !== $memberId
                || $fundingEntry->booking_id !== null
                || $fundingEntry->funding_source !== 'personal'
                || $fundingEntry->entry_type !== PointEntryType::Credit->value
                || (string) $fundingEntry->source_reference !== $sourceReference
                || PointAmount::fromString((string) $fundingEntry->point_delta)->compare($cost) !== 0
            ) {
                throw new Mvp03Exception('The validation funding ledger state is inconsistent.');
            }

            $otherEntries = $entries->reject(fn (object $entry): bool => (string) $entry->id === (string) $fundingEntry->id);
            if ($otherEntries->count() > 1) {
                throw new Mvp03Exception('The validation funding ledger state is inconsistent.');
            }

            $balance = PointAmount::zero();
            foreach ($entries as $entry) {
                $balance = $balance->add(PointAmount::fromString((string) $entry->point_delta));
            }

            if ($otherEntries->isEmpty()) {
                if ($balance->compare($cost) !== 0) {
                    throw new Mvp03Exception('The validation funding ledger state is inconsistent.');
                }

                return [
                    'ledger_entry_id' => (string) $fundingEntry->id,
                    'point_cost' => (string) $cost,
                    'replayed' => true,
                ];
            }

            $charge = $otherEntries->first();
            $expectedCharge = PointAmount::zero()->subtract($cost);
            if (
                $charge->entry_type !== PointEntryType::Charge->value
                || $charge->funding_source !== 'personal'
                || $charge->booking_id === null
                || PointAmount::fromString((string) $charge->point_delta)->compare($expectedCharge) !== 0
            ) {
                throw new Mvp03Exception('The validation booking charge is inconsistent.');
            }

            $booking = DB::table('bookings')
                ->where('id', $charge->booking_id)
                ->where('member_id', $memberId)
                ->where('funding_source', 'personal')
                ->where('booking_type', 'b2c')
                ->where('status', 'confirmed')
                ->where('shift_schedule_id', $schedule->id)
                ->where('service_offering_id', $service->id)
                ->where('point_cost_snapshot', (string) $cost)
                ->first();
            if ($booking === null || $balance->compare(PointAmount::zero()) !== 0) {
                throw new Mvp03Exception('The validation booking charge is inconsistent.');
            }

            return [
                'ledger_entry_id' => (string) $fundingEntry->id,
                'point_cost' => (string) $cost,
                'replayed' => true,
            ];
        });
    }

    private function record(string $action, string $targetType, string $targetId, array $metadata): void
    {
        $context = $this->context->current();
        if ($context->purpose === null) {
            $context = $context->forPurpose($action);
        }

        $this->audit->append(AuditEvent::fromContext($context, $action, 'member', 'success', $this->clock->now(), $targetType, $targetId, metadata: $metadata));
    }
}
