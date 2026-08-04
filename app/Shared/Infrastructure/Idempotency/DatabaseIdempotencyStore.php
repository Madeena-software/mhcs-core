<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Idempotency;

use App\Shared\Time\Clock;
use Closure;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class DatabaseIdempotencyStore implements IdempotencyStore
{
    public function __construct(private readonly Clock $clock) {}

    public function run(
        string $messageId,
        string $consumer,
        array $payload,
        Closure $callback,
    ): IdempotencyOutcome {
        $this->assertIdentity($messageId, $consumer);
        $payloadHash = $this->payloadHash($payload);

        $existing = DB::transaction(function () use ($messageId, $consumer, $payloadHash): object {
            $now = $this->clock->now();

            DB::table('idempotent_consumptions')->insertOrIgnore([
                'message_id' => $messageId,
                'consumer' => $consumer,
                'payload_hash' => $payloadHash,
                'status' => 'pending',
                'result' => null,
                'attempts' => 0,
                'last_error' => null,
                'handled_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return DB::table('idempotent_consumptions')
                ->where('message_id', $messageId)
                ->where('consumer', $consumer)
                ->first();
        });

        $this->assertPayload($existing->payload_hash, $payloadHash, $messageId, $consumer);

        if ($existing->status === 'handled') {
            return IdempotencyOutcome::replayed(
                $messageId,
                $consumer,
                $this->decodeResult($existing->result),
            );
        }

        try {
            return DB::transaction(function () use ($messageId, $consumer, $payloadHash, $callback): IdempotencyOutcome {
                $row = DB::table('idempotent_consumptions')
                    ->where('message_id', $messageId)
                    ->where('consumer', $consumer)
                    ->lockForUpdate()
                    ->first();

                $this->assertPayload($row->payload_hash, $payloadHash, $messageId, $consumer);

                if ($row->status === 'handled') {
                    return IdempotencyOutcome::replayed(
                        $messageId,
                        $consumer,
                        $this->decodeResult($row->result),
                    );
                }

                $result = $callback();
                $encodedResult = json_encode($result, JSON_THROW_ON_ERROR);
                $now = $this->clock->now();

                DB::table('idempotent_consumptions')
                    ->where('message_id', $messageId)
                    ->where('consumer', $consumer)
                    ->update([
                        'status' => 'handled',
                        'result' => $encodedResult,
                        'attempts' => $row->attempts + 1,
                        'last_error' => null,
                        'handled_at' => $now,
                        'updated_at' => $now,
                    ]);

                return IdempotencyOutcome::handled($messageId, $consumer, $result);
            });
        } catch (IdempotencyConflict $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            DB::table('idempotent_consumptions')
                ->where('message_id', $messageId)
                ->where('consumer', $consumer)
                ->update([
                    'status' => 'failed',
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => $exception::class,
                    'updated_at' => $this->clock->now(),
                ]);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new IdempotencyConflict('The idempotency payload must be JSON serializable.', previous: $exception);
        }
    }

    private function assertPayload(
        string $storedHash,
        string $payloadHash,
        string $messageId,
        string $consumer,
    ): void {
        if (! hash_equals($storedHash, $payloadHash)) {
            throw new IdempotencyConflict("Idempotency identity {$consumer}/{$messageId} was reused with a changed payload.");
        }
    }

    private function decodeResult(?string $result): mixed
    {
        if ($result === null) {
            return null;
        }

        return json_decode($result, true, flags: JSON_THROW_ON_ERROR);
    }

    private function assertIdentity(string $messageId, string $consumer): void
    {
        if (trim($messageId) === '' || trim($consumer) === '') {
            throw new IdempotencyConflict('Message ID and consumer identity are required.');
        }
    }
}
