<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorServiceOfferingQuery;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolMapping;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolVersion;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class OperatorXrayProtocolConfigurationService
{
    public const PURPOSE = 'operator.xray-protocol.publish';

    private const MAX_PROJECTIONS = 16;

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorServiceOfferingQuery $offerings,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
    ) {}

    /**
     * @return array{mapping_id: string, service_offering_id: string, service_code: string, version: int, projection_identifiers: list<string>, published_at: string}
     */
    public function publish(mixed $serviceOfferingId, mixed $expectedVersion, mixed $projectionIdentifiers, mixed $operationId): array
    {
        $context = $this->authorization->protocolManage()->forPurpose(self::PURPOSE);
        $serviceOfferingId = $this->serviceOfferingId($serviceOfferingId);
        $service = $this->offering($serviceOfferingId);
        $expectedVersion = $this->expectedVersion($expectedVersion);
        $projectionIdentifiers = $this->projectionIdentifiers($projectionIdentifiers);
        $operationId = $this->operationId($operationId);
        $actorId = (string) $context->actorId;
        $payload = [
            'actor_id' => $actorId,
            'service_offering_id' => $serviceOfferingId,
            'service_code_snapshot' => $service['code'],
            'expected_version' => $expectedVersion,
            'projection_identifiers' => $projectionIdentifiers,
        ];

        try {
            return DB::transaction(fn (): array => $this->idempotency->run(
                $operationId,
                self::PURPOSE,
                $payload,
                function () use ($actorId, $expectedVersion, $operationId, $projectionIdentifiers, $service, $serviceOfferingId): array {
                    $transactionContext = $this->authorization->protocolManage()->forPurpose(self::PURPOSE);
                    if ((string) $transactionContext->actorId !== $actorId) {
                        throw new OperatorException('xray_protocol_unavailable', 'The protocol configuration is unavailable.');
                    }
                    if ($this->offering($serviceOfferingId) !== $service) {
                        throw new OperatorException('xray_protocol_conflict', 'The protocol configuration changed before publication.');
                    }

                    $mapping = OperatorXrayProtocolMapping::query()
                        ->where('service_offering_id', $serviceOfferingId)
                        ->lockForUpdate()
                        ->first();
                    $currentVersion = $mapping === null ? 0 : (int) $mapping->current_version;
                    if ($expectedVersion !== $currentVersion) {
                        throw new OperatorException('xray_protocol_conflict', 'The protocol configuration changed before publication.');
                    }

                    $version = $currentVersion + 1;
                    $now = $this->clock->now();
                    if ($mapping === null) {
                        $mapping = OperatorXrayProtocolMapping::query()->create([
                            'id' => (string) Str::uuid(),
                            'service_offering_id' => $serviceOfferingId,
                            'current_version' => $version,
                            'service_code_snapshot' => $service['code'],
                            'projection_identifiers' => $projectionIdentifiers,
                            'published_by_user_id' => $actorId,
                            'published_at' => $now,
                        ]);
                    } else {
                        $mapping->forceFill([
                            'current_version' => $version,
                            'service_code_snapshot' => $service['code'],
                            'projection_identifiers' => $projectionIdentifiers,
                            'published_by_user_id' => $actorId,
                            'published_at' => $now,
                        ])->save();
                    }

                    OperatorXrayProtocolVersion::query()->create([
                        'id' => (string) Str::uuid(),
                        'operator_xray_protocol_mapping_id' => $mapping->getKey(),
                        'version' => $version,
                        'service_code_snapshot' => $service['code'],
                        'projection_identifiers' => $projectionIdentifiers,
                        'published_by_user_id' => $actorId,
                        'published_at' => $now,
                    ]);
                    $metadata = $this->metadata($serviceOfferingId, $version, $projectionIdentifiers, $actorId, $operationId, $now);
                    $this->audit->append(AuditEvent::fromContext(
                        $transactionContext,
                        'operator.xray-protocol.published',
                        'operator',
                        'success',
                        $now,
                        'operator-xray-protocol-mapping',
                        (string) $mapping->getKey(),
                        metadata: $metadata,
                    ));
                    $this->outbox->record(new VersionedDomainEvent(
                        LocalId::fromString((string) Str::uuid()),
                        'operator.xray-protocol-published',
                        1,
                        $now,
                        $metadata,
                        LocalId::fromString((string) $mapping->getKey()),
                        $transactionContext->operationId,
                    ));

                    return [
                        'mapping_id' => (string) $mapping->getKey(),
                        'service_offering_id' => $serviceOfferingId,
                        'service_code' => $service['code'],
                        'version' => $version,
                        'projection_identifiers' => $projectionIdentifiers,
                        'published_at' => $now->format(DATE_ATOM),
                    ];
                },
            )->result);
        } catch (IdempotencyConflict|QueryException $exception) {
            throw new OperatorException('xray_protocol_conflict', 'The protocol configuration changed before publication.', $exception);
        } catch (OperatorException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OperatorException('xray_protocol_failure', 'The protocol configuration could not be published.', $exception);
        }
    }

    private function serviceOfferingId(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (! Str::isUuid($value)) {
            throw new OperatorException('xray_protocol_unavailable', 'The protocol configuration is unavailable.');
        }

        return $value;
    }

    /** @return array{id: string, code: string} */
    private function offering(string $serviceOfferingId): array
    {
        $offering = $this->offerings->findCurrent($serviceOfferingId);
        if (
            $offering === null
            || ($offering['id'] ?? null) !== $serviceOfferingId
            || ! is_string($offering['code'] ?? null)
            || trim($offering['code']) === ''
            || mb_strlen($offering['code'], 'UTF-8') > 64
        ) {
            throw new OperatorException('xray_protocol_unavailable', 'The protocol configuration is unavailable.');
        }

        return ['id' => $serviceOfferingId, 'code' => trim($offering['code'])];
    }

    private function expectedVersion(mixed $value): int
    {
        if (is_int($value)) {
            $version = $value;
        } elseif (is_string($value) && ctype_digit($value)) {
            $version = (int) $value;
        } else {
            throw new OperatorException('xray_protocol_invalid', 'The protocol configuration is invalid.');
        }
        if ($version < 0) {
            throw new OperatorException('xray_protocol_invalid', 'The protocol configuration is invalid.');
        }

        return $version;
    }

    /** @return list<string> */
    private function projectionIdentifiers(mixed $value): array
    {
        if (! is_array($value) || count($value) === 0 || count($value) > self::MAX_PROJECTIONS) {
            throw new OperatorException('xray_protocol_invalid', 'The protocol configuration is invalid.');
        }

        $identifiers = [];
        foreach ($value as $identifier) {
            if (! is_string($identifier)) {
                throw new OperatorException('xray_protocol_invalid', 'The protocol configuration is invalid.');
            }
            $identifier = strtoupper(trim($identifier));
            if (preg_match('/\A[A-Z0-9][A-Z0-9_-]{0,63}\z/', $identifier) !== 1 || in_array($identifier, $identifiers, true)) {
                throw new OperatorException('xray_protocol_invalid', 'The protocol configuration is invalid.');
            }
            $identifiers[] = $identifier;
        }

        return $identifiers;
    }

    private function operationId(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (! Str::isUuid($value)) {
            throw new OperatorException('xray_protocol_invalid', 'The protocol configuration is invalid.');
        }

        return $value;
    }

    /** @param list<string> $projectionIdentifiers @return array<string, mixed> */
    private function metadata(string $serviceOfferingId, int $version, array $projectionIdentifiers, string $actorId, string $operationId, \DateTimeImmutable $publishedAt): array
    {
        return [
            'service_offering_id' => $serviceOfferingId,
            'protocol_version' => $version,
            'projection_identifiers' => $projectionIdentifiers,
            'published_by_user_id' => $actorId,
            'operation_id' => $operationId,
            'published_at_utc' => $publishedAt->format(DATE_ATOM),
        ];
    }
}
