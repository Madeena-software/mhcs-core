<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\PointAmount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class ServiceOffering extends Model
{
    protected $table = 'service_offerings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $offering): void {
            if ($offering->isDirty('code') && DB::table('bookings')->where('service_offering_id', $offering->getKey())->exists()) {
                throw new MemberIdentityException('A booked service code is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['includes_ai' => 'boolean', 'includes_doctor' => 'boolean', 'active' => 'boolean'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ShiftSchedule::class, 'service_offering_id');
    }

    public function pointPrice(): PointAmount
    {
        return PointAmount::fromString((string) $this->point_price);
    }
}
