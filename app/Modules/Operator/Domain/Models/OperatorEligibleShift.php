<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperatorEligibleShift extends Model
{
    protected $table = 'operator_eligible_shifts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'schedule_starts_at' => 'immutable_datetime',
            'schedule_ends_at' => 'immutable_datetime',
            'eligible_at' => 'immutable_datetime',
            'confirmed_count_at_eligibility' => 'integer',
            'quota' => 'integer',
            'event_version' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OperatorShiftAssignment::class, 'operator_eligible_shift_id');
    }
}
