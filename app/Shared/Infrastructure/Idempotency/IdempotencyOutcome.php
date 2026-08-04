<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Idempotency;

use InvalidArgumentException;

final readonly class IdempotencyOutcome
{
    private function __construct(
        public string $status,
        public string $messageId,
        public string $consumer,
        public mixed $result,
    ) {
        if (! in_array($this->status, ['handled', 'replayed'], true)) {
            throw new InvalidArgumentException('Unsupported idempotency outcome.');
        }
    }

    public static function handled(string $messageId, string $consumer, mixed $result): self
    {
        return new self('handled', $messageId, $consumer, $result);
    }

    public static function replayed(string $messageId, string $consumer, mixed $result): self
    {
        return new self('replayed', $messageId, $consumer, $result);
    }
}
