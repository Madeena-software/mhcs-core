<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Family extends Model
{
    protected $table = 'families';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['encrypted_family_card_number', 'family_card_lookup_digest'];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
