<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LocalImagingOrder extends Model
{
    protected $table = 'local_imaging_orders';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['authored_at' => 'immutable_datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
