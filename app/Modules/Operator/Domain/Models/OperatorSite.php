<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperatorSite extends Model
{
    protected $table = 'operator_sites';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OperatorSiteAssignment::class, 'operator_site_id');
    }
}
