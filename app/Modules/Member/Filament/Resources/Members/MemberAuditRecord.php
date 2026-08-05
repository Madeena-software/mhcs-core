<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Members;

use Illuminate\Database\Eloquent\Model;

final class MemberAuditRecord extends Model
{
    protected $table = 'audit_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
