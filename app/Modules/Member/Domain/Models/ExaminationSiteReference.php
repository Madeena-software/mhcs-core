<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ExaminationSiteReference extends Model
{
    protected $table = 'examination_site_refs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(OperatorOrganizationReference::class, 'operator_organization_ref_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class, 'examination_site_id');
    }
}
