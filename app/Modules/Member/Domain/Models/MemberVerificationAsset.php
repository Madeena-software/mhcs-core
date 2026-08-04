<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberVerificationAsset extends Model
{
    protected $table = 'member_verification_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['private_object_key', 'checksum'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'bytes' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
