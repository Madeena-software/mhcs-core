<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperatorShiftAssignment extends Model
{
    protected $table = 'operator_shift_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function eligibleShift(): BelongsTo
    {
        return $this->belongsTo(OperatorEligibleShift::class, 'operator_eligible_shift_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(OperatorProfile::class, 'operator_profile_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
