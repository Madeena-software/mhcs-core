<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Models\User;

interface InteractiveRadiologistAccess
{
    public function destination(User $user, ?string $intended): ?string;
}
