<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\Models\ServiceOffering;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Member\Domain\PointAmount;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class Mvp03OfferingService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ServiceOffering
    {
        $context = $this->authorization->catalogueManage();
        $code = $this->code($attributes['code'] ?? null);
        $price = $this->price($attributes['point_price'] ?? null);
        if (ServiceOffering::query()->where('code', $code)->exists()) {
            throw new Mvp03Exception('Service code is already in use.');
        }

        return DB::transaction(function () use ($attributes, $code, $price, $context): ServiceOffering {
            $id = (string) Str::uuid();
            ServiceOffering::query()->create([
                'id' => $id,
                'code' => $code,
                'name' => $this->text($attributes['name'] ?? null),
                'includes_ai' => (bool) ($attributes['includes_ai'] ?? false),
                'includes_doctor' => (bool) ($attributes['includes_doctor'] ?? false),
                'point_price' => (string) $price,
                'active' => (bool) ($attributes['active'] ?? true),
            ]);
            $offering = ServiceOffering::query()->findOrFail($id);
            $this->audit($context, 'member.service-offering.create', $id, ['code' => $code]);

            return $offering;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(ServiceOffering $offering, array $attributes): ServiceOffering
    {
        $context = $this->authorization->catalogueManage();
        $price = $this->price($attributes['point_price'] ?? $offering->point_price);
        $booked = DB::table('bookings')->where('service_offering_id', $offering->getKey())->exists();
        $code = $this->code($attributes['code'] ?? $offering->code);
        if ($booked && $code !== $offering->code) {
            throw new Mvp03Exception('A booked service code is immutable.');
        }
        if ($code !== $offering->code && ServiceOffering::query()->where('code', $code)->where('id', '<>', $offering->getKey())->exists()) {
            throw new Mvp03Exception('Service code is already in use.');
        }

        return DB::transaction(function () use ($offering, $attributes, $code, $price, $context): ServiceOffering {
            $record = ServiceOffering::query()->whereKey($offering->getKey())->lockForUpdate()->firstOrFail();
            $record->forceFill([
                'code' => $code,
                'name' => $this->text($attributes['name'] ?? $record->name),
                'includes_ai' => (bool) ($attributes['includes_ai'] ?? $record->includes_ai),
                'includes_doctor' => (bool) ($attributes['includes_doctor'] ?? $record->includes_doctor),
                'point_price' => (string) $price,
                'active' => (bool) ($attributes['active'] ?? $record->active),
            ])->save();
            $this->audit($context, 'member.service-offering.update', (string) $record->getKey(), ['code' => $record->code]);

            return $record->refresh();
        });
    }

    private function code(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || preg_match('/\A[A-Z0-9][A-Z0-9_-]{1,63}\z/', $value) !== 1) {
            throw new Mvp03Exception('Service code is invalid.');
        }

        return $value;
    }

    private function text(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || mb_strlen($value, 'UTF-8') > 255) {
            throw new Mvp03Exception('Service name is invalid.');
        }

        return $value;
    }

    private function price(mixed $value): PointAmount
    {
        $price = PointAmount::fromString(is_string($value) || is_int($value) ? (string) $value : '');
        if ($price->isNegative()) {
            throw new Mvp03Exception('Point price cannot be negative.');
        }

        return $price;
    }

    private function audit(object $context, string $action, string $targetId, array $metadata): void
    {
        $this->audit->append(AuditEvent::fromContext($context, $action, 'member', 'success', $this->clock->now(), ServiceOffering::class, $targetId, metadata: $metadata));
    }
}
