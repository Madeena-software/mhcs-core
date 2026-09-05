<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;

final class GrabberClient extends Model
{
    /** @var string */
    protected $table = 'grabber_clients';

    /** @var string */
    protected $keyType = 'string';

    /** @var bool */
    public $incrementing = false;

    /** @var list<string> */
    protected $guarded = [];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
