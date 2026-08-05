<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperatorArrival extends Model
{
    protected $table = 'operator_arrivals';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurrence_at' => 'immutable_datetime',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(OperatorSite::class, 'operator_site_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'operator_profile_id');
    }
}
