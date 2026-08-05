<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Bookings;

use Illuminate\Database\Eloquent\Model;

final class BookingAuditRecord extends Model
{
    protected $table = 'audit_events';
    protected $primaryKey = 'event_id';
    public $incrementing = false;
    public $timestamps = false;
}
