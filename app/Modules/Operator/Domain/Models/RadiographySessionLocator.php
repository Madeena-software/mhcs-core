<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class RadiographySessionLocator extends Model
{
    /** @var string */
    protected $table = 'radiography_session_locators';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var list<string> */
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'allocated_at' => 'immutable_datetime',
        'invalidated_at' => 'immutable_datetime',
    ];
}
