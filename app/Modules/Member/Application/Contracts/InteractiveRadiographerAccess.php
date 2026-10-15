<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Models\User;

interface InteractiveRadiographerAccess
{
    public function destination(User $user, ?string $intended): ?string;
}
