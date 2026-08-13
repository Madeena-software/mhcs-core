<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Enums\PointEntryType;
use App\Modules\Member\Domain\Enums\PointRateStatus;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp03PointService
{
    public function __construct(
        private AuditStore $audit,
        private AuthenticatedContextProvider $context,
        private Clock $clock,
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

    private function record(string $action, string $targetType, string $targetId, array $metadata): void
    {
        $context = $this->context->current();
        if ($context->purpose === null) {
            $context = $context->forPurpose($action);
        }

        $this->audit->append(AuditEvent::fromContext($context, $action, 'member', 'success', $this->clock->now(), $targetType, $targetId, metadata: $metadata));
    }
}
