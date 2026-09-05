<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConsentVisitConfirmation extends Model
{
    protected $table = 'consent_visit_confirmations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function masterConsent(): BelongsTo
    {
        return $this->belongsTo(MemberMasterConsent::class, 'member_master_consent_id');
    }
}
