<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Application\Bus\InProcessCommandBus;
use App\Shared\Application\Bus\InProcessQueryBus;
use App\Shared\Application\Contracts\Command;
use App\Shared\Application\Contracts\CommandHandler;
use App\Shared\Application\Contracts\Query;
use App\Shared\Application\Contracts\QueryHandler;
use App\Shared\Application\Exceptions\DuplicateHandler;
use App\Shared\Application\Exceptions\MissingHandler;
use App\Shared\Context\AuditContext;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\ExternalId;
use App\Shared\Identity\LocalId;
use App\Shared\Money\Money;
use App\Shared\Time\FrozenClock;
use App\Shared\Time\SystemClock;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class SharedFoundationTest extends TestCase
{
    public function test_local_and_external_identifiers_are_separate_immutable_values(): void
    {
        $local = LocalId::fromString('member-123');
        $external = new ExternalId('fhir', 'Patient/123');

        $this->assertSame('member-123', (string) $local);
        $this->assertSame('fhir:Patient/123', (string) $external);
        $this->assertNotSame($local::class, $external::class);
        $this->assertSame(['source' => 'fhir', 'value' => 'Patient/123'], $external->jsonSerialize());
    }

    public function test_money_uses_minor_units_and_rejects_float_or_mixed_currency_math(): void
    {
        $money = Money::fromMinorUnits(1250, 'idr');

        $this->assertSame(1250, $money->minorAmount);
        $this->assertSame('IDR', $money->currency);
        $this->assertSame(1500, $money->add(Money::fromMinorUnits(250, 'IDR'))->minorAmount);

        $this->expectException(InvalidArgumentException::class);
        Money::fromFloat(12.5, 'IDR');
    }

    public function test_money_rejects_mixed_currency_arithmetic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(1, 'IDR')->add(Money::fromMinorUnits(1, 'USD'));
    }

    public function test_money_minor_unit_constructor_rejects_a_float(): void
    {
        $this->expectException(\TypeError::class);

        Money::fromMinorUnits(1.5, 'IDR');
    }

    public function test_clock_can_be_deterministic(): void
    {
        $time = new DateTimeImmutable('2026-08-04 10:11:12+00:00');
        $clock = new FrozenClock($time);

        $this->assertSame('2026-08-04T10:11:12+00:00', $clock->now()->format(DATE_ATOM));
        $this->assertSame('UTC', (new SystemClock)->now()->getTimezone()->getName());
    }

    public function test_authenticated_and_audit_context_preserve_declared_fields(): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString('actor-1'),
            operationId: new CorrelationId('operation-1'),
            sessionId: LocalId::fromString('session-1'),
            roles: ['operator'],
            permissions: ['attendance.read'],
            siteId: LocalId::fromString('site-1'),
            caseId: LocalId::fromString('case-1'),
            purpose: 'attendance',
        );
        $audit = AuditContext::fromAuthenticatedContext($context);

        $this->assertSame('actor-1', (string) $audit->actorId);
        $this->assertSame('operation-1', (string) $audit->operationId);
        $this->assertSame(['operator'], $audit->roles);
        $this->assertSame(['attendance.read'], $audit->permissions);
        $this->assertSame('site-1', (string) $audit->siteId);
        $this->assertSame('case-1', (string) $audit->caseId);
        $this->assertSame('attendance', $audit->purpose);
    }

    public function test_command_and_query_buses_dispatch_only_registered_handlers_in_process(): void
    {
        $command = new class implements Command
        {
            public function __construct(public readonly string $value = 'command') {}
        };
        $commandHandler = new class implements CommandHandler
        {
            public function handle(Command $command): mixed
            {
                return $command->value.'-handled';
            }
        };
        $commandBus = new InProcessCommandBus(app());
        $commandBus->register($command::class, $commandHandler::class);

        $this->assertSame('command-handled', $commandBus->dispatch($command));

        $query = new class implements Query {};
        $queryHandler = new class implements QueryHandler
        {
            public function handle(Query $query): mixed
            {
                return 'query-result';
            }
        };
        $queryBus = new InProcessQueryBus(app());
        $queryBus->register($query::class, $queryHandler::class);

        $this->assertSame('query-result', $queryBus->dispatch($query));
    }

    public function test_missing_and_duplicate_handler_registrations_fail_explicitly(): void
    {
        $command = new class implements Command {};
        $bus = new InProcessCommandBus(app());

        try {
            $bus->dispatch($command);
            $this->fail('A missing handler must throw.');
        } catch (MissingHandler) {
            $this->addToAssertionCount(1);
        }

        $handler = new class implements CommandHandler
        {
            public function handle(Command $command): mixed
            {
                return null;
            }
        };
        $bus->register($command::class, $handler::class);

        $this->expectException(DuplicateHandler::class);
        $bus->register($command::class, $handler::class);
    }
}
