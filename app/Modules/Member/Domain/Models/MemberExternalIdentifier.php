<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MemberExternalIdentifier extends Model
{
    protected $table = 'member_external_identifiers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
