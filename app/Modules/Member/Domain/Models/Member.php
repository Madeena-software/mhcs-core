<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use App\Modules\Member\Domain\MemberIdentityException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Member extends Model
{
    protected $table = 'members';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['encrypted_nik', 'nik_lookup_digest'];

    protected static function booted(): void
    {
        self::updating(function (self $member): void {
            foreach (['medical_record_number', 'registration_source', 'nik_lookup_digest'] as $immutable) {
                if ($member->isDirty($immutable)) {
                    throw new MemberIdentityException("Member {$immutable} is immutable.");
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function verificationAssets(): HasMany
    {
        return $this->hasMany(MemberVerificationAsset::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(MemberGuardian::class, 'child_member_id');
    }

    public function externalIdentifiers(): HasMany
    {
        return $this->hasMany(MemberExternalIdentifier::class);
    }
}
