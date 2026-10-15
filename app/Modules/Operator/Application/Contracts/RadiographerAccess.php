<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Contracts;

interface RadiographerAccess
{
    public function authorizeManager(?string $site = null): void;

    public function denied(): void;

    /** Required audit commits before an outermost caller receives protected content. */
    public function run(string $workspace, string $action, callable $operation, int $generation): mixed;
}
