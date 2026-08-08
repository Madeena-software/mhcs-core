<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class OperatorXrayProtocolVersion extends Model
{
    protected $table = 'operator_xray_protocol_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['projection_identifiers' => 'array', 'published_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new \RuntimeException('X-ray protocol versions are immutable.'));
        self::deleting(static fn (): never => throw new \RuntimeException('X-ray protocol versions are append-only.'));
    }
}
