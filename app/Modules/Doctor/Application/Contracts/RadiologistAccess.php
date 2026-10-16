<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Contracts;

interface RadiologistAccess
{
    public function authorizeManager(?string $site = null): void;

    public function run(string $workspace, string $action, callable $operation, int $generation): mixed;
}
