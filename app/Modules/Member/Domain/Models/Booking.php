<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Models;

use App\Modules\Member\Domain\PointAmount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Booking extends Model
{
    protected $table = 'bookings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'point_cost_snapshot' => 'decimal:4',
            'includes_ai_snapshot' => 'boolean',
            'includes_doctor_snapshot' => 'boolean',
            'created_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ShiftSchedule::class, 'shift_schedule_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceOffering::class, 'service_offering_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ExaminationSiteReference::class, 'examination_site_id_snapshot');
    }

    public function imagingOrder(): HasOne
    {
        return $this->hasOne(LocalImagingOrder::class, 'booking_id');
    }

    public function pointCost(): PointAmount
    {
        return PointAmount::fromString((string) $this->point_cost_snapshot);
    }
}
