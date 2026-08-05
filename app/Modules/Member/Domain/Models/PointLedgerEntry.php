<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use App\Modules\Member\Domain\PointAmount;
use Illuminate\Database\Eloquent\Model;

final class PointLedgerEntry extends Model
{
    protected $table = 'point_ledger_entries';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new \RuntimeException('Point ledger entries are immutable.'));
        self::deleting(static fn (): never => throw new \RuntimeException('Point ledger entries are append-only.'));
    }

    public function amount(): PointAmount
    {
        return PointAmount::fromString((string) $this->point_delta);
    }
}
