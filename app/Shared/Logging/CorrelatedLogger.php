<?php

declare(strict_types=1);

namespace App\Shared\Logging;

use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Security\SecurityException;
use App\Shared\Security\SensitiveDataSanitizer;
use Psr\Log\LoggerInterface;

final readonly class CorrelatedLogger
{
    public function __construct(
        private LoggerInterface $logger,
        private AuthenticatedContextProvider $context,
    ) {}

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        $trusted = $this->context->current();

        if ($trusted->operationId === null) {
            throw new SecurityException('Security-relevant logs require a correlation identity.');
        }

        $context['correlation_id'] = (string) $trusted->operationId;
        $this->logger->{$level}(
            SensitiveDataSanitizer::sanitizeLogMessage($message),
            SanitizedLogContext::sanitize($context),
        );
    }
}
