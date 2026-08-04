<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberGuardian extends Model
{
    protected $table = 'member_guardians';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'child_member_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guardian_member_id');
    }
}
