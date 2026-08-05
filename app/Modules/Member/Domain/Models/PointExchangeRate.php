<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class PointExchangeRate extends Model
{
    protected $table = 'point_exchange_rates';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rupiah_per_point' => 'integer', 'effective_at' => 'immutable_datetime'];
    }
}
