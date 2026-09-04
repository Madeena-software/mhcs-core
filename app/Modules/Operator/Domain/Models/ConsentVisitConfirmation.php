<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\Member;
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function masterConsent(): BelongsTo
    {
        return $this->belongsTo(MemberMasterConsent::class, 'member_master_consent_id');
    }
}
