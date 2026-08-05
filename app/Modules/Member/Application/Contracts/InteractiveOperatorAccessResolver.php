<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Models\User;

interface InteractiveOperatorAccessResolver
{
    public function canAccess(User $user): bool;
}
