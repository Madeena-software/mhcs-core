<?php

declare(strict_types=1);

namespace App\Modules\Operator\Domain\Models;

use App\Modules\Member\Domain\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MemberMasterConsent extends Model
{
    protected $table = 'member_master_consents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'consent_version' => 'integer',
            'signed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'signer_member_id');
    }

    public function visitConfirmations(): HasMany
    {
        return $this->hasMany(ConsentVisitConfirmation::class, 'member_master_consent_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }
}
