<?php

declare(strict_types=1);

namespace App\Shared\Adapters;

final readonly class AdapterExecutionResult
{
    public function __construct(
        public bool $completed,
        public string $classification,
        public mixed $value = null,
    ) {}
}
