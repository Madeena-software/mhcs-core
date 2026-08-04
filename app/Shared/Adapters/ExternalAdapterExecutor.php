<?php

declare(strict_types=1);

namespace App\Shared\Adapters;

use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\SensitiveDataSanitizer;
use App\Shared\Time\Clock;
use Closure;
use Throwable;

final readonly class ExternalAdapterExecutor
{
    public function __construct(
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /** @param array<string, mixed> $requestMetadata */
    public function execute(
        AuthenticatedExternalAdapter $adapter,
        AuthenticatedContext $context,
        string $audience,
        array $requestMetadata,
        Closure $call,
    ): AdapterExecutionResult {
        if (
            $context->actorId === null
            || $context->operationId === null
            || $context->purpose === null
            || trim($audience) === ''
            || $adapter->identity() === ''
            || $adapter->audience() !== $audience
            || trim((string) $adapter->credential()) === ''
        ) {
            throw new AdapterException('Authenticated adapter prerequisites are missing.');
        }

        SensitiveDataSanitizer::assertSafe($requestMetadata);
        $metadataDigest = hash('sha256', json_encode($requestMetadata, JSON_THROW_ON_ERROR));
        $base = [
            'adapter' => $adapter->identity(),
            'audience' => $audience,
            'request_metadata_digest' => $metadataDigest,
        ];

        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: 'external-adapter.attempt',
            source: $adapter->identity(),
            outcome: 'attempted',
            occurredAt: $this->clock->now(),
            metadata: $base,
        ));

        try {
            $value = $call($adapter->credential(), $context);
        } catch (Throwable $exception) {
            $classification = str_contains(strtolower($exception::class), 'timeout') ? 'timeout' : 'failure';
            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'external-adapter.completed',
                source: $adapter->identity(),
                outcome: $classification,
                occurredAt: $this->clock->now(),
                metadata: $base,
            ));

            return new AdapterExecutionResult(false, $classification);
        }

        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: 'external-adapter.completed',
            source: $adapter->identity(),
            outcome: 'completed',
            occurredAt: $this->clock->now(),
            metadata: $base,
        ));

        return new AdapterExecutionResult(true, 'completed', $value);
    }
}
