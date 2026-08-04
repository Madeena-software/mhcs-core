<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Idempotency;

use Closure;

interface IdempotencyStore
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(
        string $messageId,
        string $consumer,
        array $payload,
        Closure $callback,
    ): IdempotencyOutcome;
}
