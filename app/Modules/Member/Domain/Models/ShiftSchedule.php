<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use App\Modules\Member\Domain\Enums\ScheduleStatus;
use App\Modules\Member\Domain\MemberIdentityException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShiftSchedule extends Model
{
    protected $table = 'shift_schedules';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::updating(function (self $schedule): void {
            if ($schedule->isDirty('display_reference')) {
                throw new MemberIdentityException('Schedule display_reference is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'eligible_at' => 'immutable_datetime',
            'quota' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ExaminationSiteReference::class, 'examination_site_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceOffering::class, 'service_offering_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'shift_schedule_id');
    }

    public function isOpen(): bool
    {
        return $this->status === ScheduleStatus::Open->value;
    }
}
