<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperatorOrganizationReference extends Model
{
    protected $table = 'operator_organization_refs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(ExaminationSiteReference::class, 'operator_organization_ref_id');
    }
}
