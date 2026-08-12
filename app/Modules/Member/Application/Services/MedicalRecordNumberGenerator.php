<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use Illuminate\Support\Str;

final class MedicalRecordNumberGenerator
{
    public function generate(): string
    {
        return 'MRN-'.Str::upper(Str::random(8));
    }
}
