<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use Illuminate\Support\Str;

final class MedicalRecordNumberGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
