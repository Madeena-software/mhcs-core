<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorServiceOfferingQuery;
use Illuminate\Support\Facades\DB;

final readonly class Mvp04OperatorServiceOfferingQuery implements OperatorServiceOfferingQuery
{
    public function active(): array
    {
        return DB::table('service_offerings')
            ->where('active', true)
            ->orderBy('code')
            ->get(['id', 'code'])
            ->map(static fn (object $offering): array => ['id' => (string) $offering->id, 'code' => (string) $offering->code])
            ->all();
    }

    public function findCurrent(string $serviceOfferingId): ?array
    {
        $offering = DB::table('service_offerings')
            ->where('id', $serviceOfferingId)
            ->where('active', true)
            ->lockForUpdate()
            ->first(['id', 'code']);

        return $offering === null ? null : ['id' => (string) $offering->id, 'code' => (string) $offering->code];
    }
}
