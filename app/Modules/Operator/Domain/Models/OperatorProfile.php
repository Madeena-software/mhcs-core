<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperatorProfile extends Model
{
    protected $table = 'operator_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function siteAssignments(): HasMany
    {
        return $this->hasMany(OperatorSiteAssignment::class, 'operator_profile_id');
    }
}
