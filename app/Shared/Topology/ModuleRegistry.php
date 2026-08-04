<?php

declare(strict_types=1);

namespace App\Shared\Topology;

use LogicException;

final class ModuleRegistry
{
    /** @var list<string> */
    private array $modules = [];

    public function register(string $module): void
    {
        if (in_array($module, $this->modules, true)) {
            throw new LogicException("Module {$module} was registered more than once.");
        }

        $this->modules[] = $module;
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->modules;
    }

    public function has(string $module): bool
    {
        return in_array($module, $this->modules, true);
    }
}
