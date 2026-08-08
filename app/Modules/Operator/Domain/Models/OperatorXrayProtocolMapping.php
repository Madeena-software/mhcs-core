<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class OperatorXrayProtocolMapping extends Model
{
    protected $table = 'operator_xray_protocol_mappings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['projection_identifiers' => 'array', 'published_at' => 'immutable_datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(OperatorXrayProtocolVersion::class, 'operator_xray_protocol_mapping_id')->orderBy('version');
    }
}
