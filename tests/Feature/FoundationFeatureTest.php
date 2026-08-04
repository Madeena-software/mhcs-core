<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class FoundationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dependency_constraints_and_topology_are_declarative(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('^8.4', $composer['require']['php']);
        $this->assertSame('^13.8', $composer['require']['laravel/framework']);
        $this->assertSame('^5.0', $composer['require']['filament/filament']);
        $require = array_keys($composer['require']);
        $requireDev = array_keys($composer['require-dev']);
        sort($require);
        sort($requireDev);
        $this->assertSame(['filament/filament', 'laravel/framework', 'laravel/tinker', 'php'], $require);
        $this->assertSame(
            ['fakerphp/faker', 'laravel/pail', 'laravel/pao', 'laravel/pint', 'mockery/mockery', 'nunomaduro/collision', 'phpunit/phpunit'],
            $requireDev,
        );
        $this->assertSame(
            ['Member', 'Operator', 'Doctor', 'Image Gateway'],
            config('mhcs.modules'),
        );
        $this->assertSame(
            ['member', 'operator', 'doctor', 'administrator'],
            config('mhcs.web_interfaces'),
        );
        $this->assertSame(
            ['notifications', 'image orchestration', 'AI routing', 'payouts'],
            config('mhcs.queue_purposes'),
        );
        $this->assertSame(
            ['retries', 'reconciliation', 'reminders', 'daily doctor payout batches'],
            config('mhcs.scheduler_purposes'),
        );
        $this->assertSame([], config('mhcs.mpips.direct_application_clients'));
        $this->assertSame('private black-box external service', config('mhcs.mpips.boundary'));
        $this->assertStringContainsString('measured operational need', config('mhcs.network_boundary_rule'));
    }

    public function test_laravel_process_commands_load_without_starting_persistent_workers(): void
    {
        $this->artisan('list')->assertExitCode(0);
        $this->artisan('queue:work', ['--help' => true])->assertExitCode(0);
        $this->artisan('schedule:list')->assertExitCode(0);
    }

    public function test_versioned_event_metadata_is_valid_and_serializable(): void
    {
        $event = new VersionedDomainEvent(
            id: LocalId::fromString('event-1'),
            name: 'member.example.created',
            version: 1,
            time: new DateTimeImmutable('2026-08-04T12:00:00+00:00'),
            data: ['value' => 42],
            subject: LocalId::fromString('member-1'),
        );

        $this->assertSame('event-1', $event->eventId());
        $this->assertSame('member.example.created', $event->eventName());
        $this->assertSame(1, $event->eventVersion());
        $this->assertSame('member-1', $event->subjectId());
        $this->assertSame(['value' => 42], $event->payload());
        $this->assertIsString(json_encode($event->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_event_version_and_payload_validation_fail_closed(): void
    {
        try {
            new VersionedDomainEvent(
                id: LocalId::fromString('event-invalid'),
                name: 'foundation.invalid',
                version: 0,
                time: new DateTimeImmutable('2026-08-04T12:00:00+00:00'),
                data: [],
            );
            $this->fail('An event version must be positive.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(\InvalidArgumentException::class);
        $resource = fopen('php://memory', 'rb');
        new VersionedDomainEvent(
            id: LocalId::fromString('event-invalid'),
            name: 'foundation.invalid',
            version: 1,
            time: new DateTimeImmutable('2026-08-04T12:00:00+00:00'),
            data: ['resource' => $resource],
        );
    }

    public function test_source_change_and_outbox_event_commit_together(): void
    {
        $this->createSourceTable();
        $event = $this->event('event-commit');

        DB::transaction(function () use ($event): void {
            DB::table('foundation_transaction_probe')->insert(['id' => 'commit']);
            app(OutboxStore::class)->record($event);
        });

        $this->assertDatabaseHas('foundation_transaction_probe', ['id' => 'commit']);
        $this->assertDatabaseHas('outbox_messages', ['event_id' => 'event-commit']);
        $this->assertSame('pending', app(OutboxStore::class)->find('event-commit')['status']);

        Schema::dropIfExists('foundation_transaction_probe');
    }

    public function test_source_change_and_outbox_event_roll_back_together(): void
    {
        $this->createSourceTable();
        $event = $this->event('event-rollback');

        try {
            DB::transaction(function () use ($event): void {
                DB::table('foundation_transaction_probe')->insert(['id' => 'rollback']);
                app(OutboxStore::class)->record($event);
                throw new RuntimeException('test rollback');
            });
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseMissing('foundation_transaction_probe', ['id' => 'rollback']);
        $this->assertDatabaseMissing('outbox_messages', ['event_id' => 'event-rollback']);

        Schema::dropIfExists('foundation_transaction_probe');
    }

    public function test_idempotency_replay_does_not_repeat_a_protected_side_effect(): void
    {
        $this->createSourceTable('idempotency_probe');
        $store = app(IdempotencyStore::class);
        $executions = 0;

        $first = $store->run('message-1', 'consumer-a', ['value' => 1], function () use (&$executions): array {
            $executions++;
            DB::table('idempotency_probe')->insert(['id' => 'side-effect']);

            return ['ok' => true];
        });
        $replay = $store->run('message-1', 'consumer-a', ['value' => 1], function () use (&$executions): array {
            $executions++;

            return ['ok' => false];
        });

        $this->assertSame('handled', $first->status);
        $this->assertSame('replayed', $replay->status);
        $this->assertSame(['ok' => true], $replay->result);
        $this->assertSame(1, $executions);
        $this->assertDatabaseCount('idempotency_probe', 1);

        Schema::dropIfExists('idempotency_probe');
    }

    public function test_changed_payload_with_the_same_identity_is_a_conflict(): void
    {
        $store = app(IdempotencyStore::class);
        $store->run('message-2', 'consumer-a', ['value' => 1], static fn (): string => 'ok');

        $this->expectException(IdempotencyConflict::class);
        $store->run('message-2', 'consumer-a', ['value' => 2], static fn (): string => 'changed');
    }

    public function test_failed_attempt_is_recorded_as_failed_and_can_retry(): void
    {
        $this->createSourceTable('idempotency_retry_probe');
        $store = app(IdempotencyStore::class);
        $executions = 0;

        try {
            $store->run('message-3', 'consumer-a', ['value' => 1], function () use (&$executions): never {
                $executions++;
                DB::table('idempotency_retry_probe')->insert(['id' => 'rolled-back']);
                throw new RuntimeException('expected failure');
            });
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $failed = DB::table('idempotent_consumptions')
            ->where('message_id', 'message-3')
            ->where('consumer', 'consumer-a')
            ->first();

        $this->assertSame('failed', $failed->status);
        $this->assertSame(1, $failed->attempts);
        $this->assertDatabaseCount('idempotency_retry_probe', 0);

        $result = $store->run('message-3', 'consumer-a', ['value' => 1], function () use (&$executions): array {
            $executions++;
            DB::table('idempotency_retry_probe')->insert(['id' => 'retried']);

            return ['retried' => true];
        });

        $this->assertSame('handled', $result->status);
        $this->assertSame(2, $executions);
        $this->assertDatabaseHas('idempotency_retry_probe', ['id' => 'retried']);

        Schema::dropIfExists('idempotency_retry_probe');
    }

    private function event(string $id): VersionedDomainEvent
    {
        return new VersionedDomainEvent(
            id: LocalId::fromString($id),
            name: 'foundation.tested',
            version: 1,
            time: new DateTimeImmutable('2026-08-04T12:00:00+00:00'),
            data: ['id' => $id],
        );
    }

    private function createSourceTable(string $name = 'foundation_transaction_probe'): void
    {
        Schema::dropIfExists($name);
        Schema::create($name, function (Blueprint $table): void {
            $table->string('id')->primary();
        });
    }
}
