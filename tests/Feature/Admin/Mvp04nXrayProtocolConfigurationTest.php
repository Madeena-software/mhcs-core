<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Member\Application\Contracts\OperatorServiceOfferingQuery;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Application\Services\OperatorXrayProtocolConfigurationService;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolMapping;
use App\Modules\Operator\Domain\Models\OperatorXrayProtocolVersion;
use App\Modules\Operator\Domain\OperatorException;
use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\OperatorXrayProtocolMappingResource;
use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\Pages\CreateOperatorXrayProtocolMapping;
use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\Pages\ListOperatorXrayProtocolMappings;
use App\Modules\Operator\Filament\Resources\OperatorXrayProtocolMappings\Pages\PublishNextOperatorXrayProtocolMapping;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Events\DomainEvent;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class Mvp04nXrayProtocolConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_admin_publishes_first_and_next_immutable_service_protocol_versions(): void
    {
        $admin = $this->admin([OperatorAuthorization::PROTOCOL_READ, OperatorAuthorization::PROTOCOL_MANAGE]);
        $offering = $this->offering('SYNTHETIC_SERVICE');
        $this->actingAs($admin);

        $firstOperation = (string) Str::uuid();
        $first = $this->publisher()->publish($offering['id'], 0, [' projection_a ', 'PROJECTION_B'], $firstOperation);

        $this->assertSame(1, $first['version']);
        $this->assertSame('SYNTHETIC_SERVICE', $first['service_code']);
        $this->assertSame(['PROJECTION_A', 'PROJECTION_B'], $first['projection_identifiers']);
        $this->assertDatabaseHas('operator_xray_protocol_mappings', [
            'id' => $first['mapping_id'],
            'service_offering_id' => $offering['id'],
            'current_version' => 1,
            'service_code_snapshot' => 'SYNTHETIC_SERVICE',
        ]);
        $this->assertDatabaseHas('operator_xray_protocol_versions', [
            'operator_xray_protocol_mapping_id' => $first['mapping_id'],
            'version' => 1,
            'service_code_snapshot' => 'SYNTHETIC_SERVICE',
            'published_by_user_id' => $admin->id,
        ]);
        $this->assertSame('SYNTHETIC_SERVICE', DB::table('service_offerings')->where('id', $offering['id'])->value('code'));

        DB::table('service_offerings')->where('id', $offering['id'])->update(['code' => 'SYNTHETIC_SERVICE_V2']);
        $second = $this->publisher()->publish($offering['id'], 1, ['PROJECTION_B', 'PROJECTION_A'], (string) Str::uuid());

        $this->assertSame($first['mapping_id'], $second['mapping_id']);
        $this->assertSame(2, $second['version']);
        $this->assertSame('SYNTHETIC_SERVICE_V2', $second['service_code']);
        $this->assertSame(['PROJECTION_B', 'PROJECTION_A'], $second['projection_identifiers']);
        $this->assertSame(2, DB::table('operator_xray_protocol_versions')->where('operator_xray_protocol_mapping_id', $first['mapping_id'])->count());
        $this->assertSame(
            ['PROJECTION_A', 'PROJECTION_B'],
            json_decode((string) DB::table('operator_xray_protocol_versions')->where('operator_xray_protocol_mapping_id', $first['mapping_id'])->where('version', 1)->value('projection_identifiers'), true, flags: JSON_THROW_ON_ERROR),
        );
        $this->assertSame('SYNTHETIC_SERVICE', DB::table('operator_xray_protocol_versions')->where('operator_xray_protocol_mapping_id', $first['mapping_id'])->where('version', 1)->value('service_code_snapshot'));

        $this->assertSame(2, DB::table('audit_events')->where('action', 'operator.xray-protocol.published')->count());
        $this->assertSame(2, DB::table('outbox_messages')->where('event_name', 'operator.xray-protocol-published')->count());
        $this->assertDatabaseHas('idempotent_consumptions', ['message_id' => $firstOperation, 'consumer' => 'operator.xray-protocol.publish', 'status' => 'handled']);

        $audit = json_decode((string) DB::table('audit_events')->where('action', 'operator.xray-protocol.published')->where('metadata', 'like', '%'.$firstOperation.'%')->value('metadata'), true, flags: JSON_THROW_ON_ERROR);
        $event = json_decode((string) DB::table('outbox_messages')->where('event_name', 'operator.xray-protocol-published')->where('payload', 'like', '%'.$firstOperation.'%')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing(['service_offering_id', 'protocol_version', 'projection_identifiers', 'published_by_user_id', 'operation_id', 'published_at_utc'], array_keys($audit));
        $this->assertSame($audit, $event);
        $this->assertArrayNotHasKey('member_id', $audit);
        $this->assertArrayNotHasKey('booking_id', $audit);
        $this->assertArrayNotHasKey('service_code', $audit);
    }

    public function test_exact_replay_is_duplicate_free_and_stale_or_changed_requests_conflict(): void
    {
        $admin = $this->admin([OperatorAuthorization::PROTOCOL_MANAGE]);
        $offering = $this->offering();
        $this->actingAs($admin);
        $operationId = (string) Str::uuid();

        $first = $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A', 'PROJECTION_B'], $operationId);
        $replay = $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A', 'PROJECTION_B'], $operationId);

        $this->assertEquals($first, $replay);
        $this->assertNotEquals($first, [...$replay, 'projection_identifiers' => array_reverse($replay['projection_identifiers'])]);
        $this->assertSame(1, DB::table('operator_xray_protocol_mappings')->count());
        $this->assertSame(1, DB::table('operator_xray_protocol_versions')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'operator.xray-protocol.published')->count());
        $this->assertSame(1, DB::table('outbox_messages')->where('event_name', 'operator.xray-protocol-published')->count());

        DB::table('service_offerings')->where('id', $offering['id'])->update(['code' => 'SYNTHETIC_SERVICE_CHANGED']);
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], $operationId), 'xray_protocol_conflict');
        $this->assertSame(1, DB::table('operator_xray_protocol_versions')->count());

        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 1, ['PROJECTION_A'], $operationId), 'xray_protocol_conflict');
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], (string) Str::uuid()), 'xray_protocol_conflict');

        $second = $this->publisher()->publish($offering['id'], 1, ['PROJECTION_B'], (string) Str::uuid());
        $this->assertSame(2, $second['version']);
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 1, ['PROJECTION_C'], (string) Str::uuid()), 'xray_protocol_conflict');
        $this->assertSame(2, DB::table('operator_xray_protocol_versions')->count());
    }

    public function test_published_protocol_history_cannot_be_updated_or_deleted_through_models(): void
    {
        $admin = $this->admin([OperatorAuthorization::PROTOCOL_MANAGE]);
        $offering = $this->offering();
        $this->actingAs($admin);
        $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], (string) Str::uuid());

        $version = OperatorXrayProtocolVersion::query()->firstOrFail();
        try {
            $version->forceFill(['service_code_snapshot' => 'MUTATED'])->save();
            $this->fail('Published protocol history must be immutable.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        try {
            $version->delete();
            $this->fail('Published protocol history must be append-only.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('operator_xray_protocol_versions', [
            'id' => $version->getKey(),
            'service_code_snapshot' => 'SYNTHETIC_SERVICE',
        ]);
    }

    public function test_mysql_concurrent_first_publications_leave_one_current_version(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('The X-ray protocol concurrency probe requires MySQL and proc_open.');
        }

        $pdo = $this->mysqlPdo();
        $adminId = (string) Str::uuid();
        $offeringId = (string) Str::uuid();
        $operationIds = [(string) Str::uuid(), (string) Str::uuid()];
        $now = '2026-08-08 10:00:00';
        $mappingId = null;

        try {
            $this->insertPdo($pdo, 'insert into users (id, email, email_verified_at, password, remember_token, account_status, login_enabled, must_change_password, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$adminId, 'protocol-concurrency-'.str_replace('-', '', $adminId).'@example.test', null, 'hash', null, 'active', 1, 0, $now, $now]);
            $this->insertPdo($pdo, 'insert into authorization_role_assignments (id, user_id, role, assigned_by_user_id, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $adminId, 'administrator', null, 1, $now, $now]);
            $this->insertPdo($pdo, 'insert into authorization_permission_assignments (id, user_id, permission, assigned_by_user_id, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?)', [(string) Str::uuid(), $adminId, OperatorAuthorization::PROTOCOL_MANAGE, null, 1, $now, $now]);
            $this->insertPdo($pdo, 'insert into service_offerings (id, code, name, includes_ai, includes_doctor, point_price, active, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$offeringId, 'SYNTHETIC_CONCURRENT', 'Synthetic concurrent offering', 0, 0, '1.0000', 1, $now, $now]);

            $worker = <<<'PHP'
$root = getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$input = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$user = \App\Models\User::query()->findOrFail($input['actor_id']);
\Illuminate\Support\Facades\Auth::guard()->setUser($user);
echo "ready\n";
flush();
fgets(STDIN);
try {
    $published = app(\App\Modules\Operator\Application\Services\OperatorXrayProtocolConfigurationService::class)->publish($input['offering_id'], 0, ['PROJECTION_A'], $input['operation_id']);
    echo 'success:'.$published['version'];
} catch (\App\Modules\Operator\Domain\OperatorException $exception) {
    echo $exception->category;
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
PHP;

            $processes = [];
            foreach ($operationIds as $operationId) {
                $input = base64_encode(json_encode([
                    'actor_id' => $adminId,
                    'offering_id' => $offeringId,
                    'operation_id' => $operationId,
                ], JSON_THROW_ON_ERROR));
                $process = proc_open([PHP_BINARY, '-r', $worker, $input], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start the X-ray protocol concurrency probe.');
                }
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as [$process, $pipes]) {
                $this->assertSame('ready', trim((string) fgets($pipes[1])));
            }

            foreach ($processes as [$process, $pipes]) {
                fwrite($pipes[0], "go\n");
                fclose($pipes[0]);
            }

            $outcomes = [];
            foreach ($processes as [$process, $pipes]) {
                $outcomes[] = trim((string) stream_get_contents($pipes[1]));
                $error = (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $error);
            }
            sort($outcomes);
            $this->assertSame(['success:1', 'xray_protocol_conflict'], $outcomes);

            $mapping = $pdo->prepare('select id from operator_xray_protocol_mappings where service_offering_id = ?');
            $mapping->execute([$offeringId]);
            $mappingId = $mapping->fetchColumn();
            $this->assertIsString($mappingId);
            $this->assertSame(1, (int) $pdo->query("select count(*) from operator_xray_protocol_mappings where service_offering_id = '".$offeringId."'")->fetchColumn());
            $this->assertSame(1, (int) $pdo->query("select count(*) from operator_xray_protocol_versions where operator_xray_protocol_mapping_id = '".$mappingId."'")->fetchColumn());
            $this->assertSame(1, (int) $pdo->query("select count(*) from idempotent_consumptions where consumer = 'operator.xray-protocol.publish' and message_id in ('".$operationIds[0]."', '".$operationIds[1]."') and status = 'handled'")->fetchColumn());
        } finally {
            if (is_string($mappingId)) {
                $this->insertPdo($pdo, 'delete from outbox_messages where subject_id = ?', [$mappingId]);
                $this->insertPdo($pdo, 'delete from audit_events where target_id = ?', [$mappingId]);
                $this->insertPdo($pdo, 'delete from operator_xray_protocol_versions where operator_xray_protocol_mapping_id = ?', [$mappingId]);
                $this->insertPdo($pdo, 'delete from operator_xray_protocol_mappings where id = ?', [$mappingId]);
            }
            $this->insertPdo($pdo, 'delete from idempotent_consumptions where consumer = ? and message_id in (?, ?)', ['operator.xray-protocol.publish', ...$operationIds]);
            $this->insertPdo($pdo, 'delete from authorization_permission_assignments where user_id = ?', [$adminId]);
            $this->insertPdo($pdo, 'delete from authorization_role_assignments where user_id = ?', [$adminId]);
            $this->insertPdo($pdo, 'delete from service_offerings where id = ?', [$offeringId]);
            $this->insertPdo($pdo, 'delete from users where id = ?', [$adminId]);
        }
    }

    public function test_missing_changed_and_invalid_member_service_or_projection_evidence_fails_without_state(): void
    {
        $admin = $this->admin([OperatorAuthorization::PROTOCOL_MANAGE]);
        $offering = $this->offering();
        $this->actingAs($admin);

        DB::table('service_offerings')->where('id', $offering['id'])->update(['active' => false]);
        $missingOperation = (string) Str::uuid();
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], $missingOperation), 'xray_protocol_unavailable');
        $this->assertDatabaseMissing('operator_xray_protocol_mappings', ['service_offering_id' => $offering['id']]);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $missingOperation]);

        DB::table('service_offerings')->where('id', $offering['id'])->update(['active' => true]);
        app()->instance(OperatorServiceOfferingQuery::class, new class($offering['id']) implements OperatorServiceOfferingQuery
        {
            public int $calls = 0;

            public function __construct(private readonly string $offeringId) {}

            public function active(): array
            {
                return [['id' => $this->offeringId, 'code' => 'SYNTHETIC_SERVICE']];
            }

            public function findCurrent(string $serviceOfferingId): ?array
            {
                $this->calls++;

                return $serviceOfferingId === $this->offeringId
                    ? ['id' => $this->offeringId, 'code' => $this->calls === 1 ? 'SYNTHETIC_SERVICE' : 'SYNTHETIC_SERVICE_CHANGED']
                    : null;
            }
        });
        $changedOperation = (string) Str::uuid();
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], $changedOperation), 'xray_protocol_conflict');
        $this->assertDatabaseMissing('operator_xray_protocol_mappings', ['service_offering_id' => $offering['id']]);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $changedOperation]);

        foreach ([[], ['PROJECTION_A', ' projection_a '], ['NOT VALID!']] as $projections) {
            $operationId = (string) Str::uuid();
            $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, $projections, $operationId), 'xray_protocol_invalid');
            $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $operationId]);
        }
        $this->assertSame(0, DB::table('operator_xray_protocol_versions')->count());
    }

    public function test_read_manage_and_global_administrator_authorization_are_independent_and_rechecked(): void
    {
        $offering = $this->offering();
        $reader = $this->admin(['member.admin.access', OperatorAuthorization::PROTOCOL_READ]);
        $this->actingAs($reader);
        Filament::setCurrentPanel('admin');

        $this->get('/admin/operator-xray-protocol-mappings')->assertOk();
        $this->assertFalse(OperatorXrayProtocolMappingResource::canCreate());
        $this->assertFalse(OperatorXrayProtocolMappingResource::canEdit(new OperatorXrayProtocolMapping));
        $this->assertFalse(OperatorXrayProtocolMappingResource::canDelete(new OperatorXrayProtocolMapping));
        Livewire::test(ListOperatorXrayProtocolMappings::class)
            ->assertActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('export');
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], (string) Str::uuid()), 'operator_admin_denied');

        $ordinary = User::factory()->create();
        $this->grant($ordinary, ['operator'], [OperatorAuthorization::PORTAL_ACCESS]);
        $this->actingAs($ordinary);
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], (string) Str::uuid()), 'operator_admin_denied');

        $manager = $this->admin(['member.admin.access', OperatorAuthorization::PROTOCOL_READ, OperatorAuthorization::PROTOCOL_MANAGE]);
        $this->actingAs($manager);
        Filament::setCurrentPanel('admin');
        $this->assertTrue(OperatorXrayProtocolMappingResource::canCreate());
        Livewire::test(CreateOperatorXrayProtocolMapping::class)
            ->fillForm([
                'service_offering_id' => $offering['id'],
                'projection_identifiers' => "PROJECTION_A\nPROJECTION_B",
                'operation_id' => (string) Str::uuid(),
            ])
            ->call('create')
            ->assertHasNoErrors();
        $mapping = OperatorXrayProtocolMapping::query()->where('service_offering_id', $offering['id'])->firstOrFail();
        Livewire::test(PublishNextOperatorXrayProtocolMapping::class, ['record' => $mapping->getKey()])
            ->fillForm(['projection_identifiers' => "PROJECTION_B\nPROJECTION_A", 'operation_id' => (string) Str::uuid()])
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(2, (int) $mapping->refresh()->current_version);

        DB::table('authorization_permission_assignments')->where('user_id', $manager->id)->where('permission', OperatorAuthorization::PROTOCOL_MANAGE)->update(['active' => false]);
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 2, ['PROJECTION_C'], (string) Str::uuid()), 'operator_admin_denied');
        DB::table('authorization_permission_assignments')->where('user_id', $manager->id)->where('permission', OperatorAuthorization::PROTOCOL_MANAGE)->update(['active' => true]);
        DB::table('users')->where('id', $manager->id)->update(['account_status' => 'suspended']);
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 2, ['PROJECTION_C'], (string) Str::uuid()), 'operator_admin_denied');
        $this->assertSame(2, DB::table('operator_xray_protocol_versions')->count());
    }

    public function test_audit_or_outbox_failure_rolls_back_mapping_history_and_idempotency(): void
    {
        $admin = $this->admin([OperatorAuthorization::PROTOCOL_MANAGE]);
        $offering = $this->offering();
        $this->actingAs($admin);
        $auditOperation = (string) Str::uuid();

        app()->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new RuntimeException('Synthetic protocol audit failure.');
            }
        });
        app()->forgetScopedInstances();
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], $auditOperation), 'xray_protocol_failure');
        $this->assertDatabaseMissing('operator_xray_protocol_mappings', ['service_offering_id' => $offering['id']]);
        $this->assertDatabaseMissing('operator_xray_protocol_versions', []);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $auditOperation]);
        $this->assertDatabaseMissing('outbox_messages', ['event_name' => 'operator.xray-protocol-published']);

        app()->instance(AuditStore::class, new DatabaseAuditStore);
        app()->instance(OutboxStore::class, new class implements OutboxStore
        {
            public function record(DomainEvent $event): void
            {
                throw new RuntimeException('Synthetic protocol outbox failure.');
            }

            public function find(string $eventId): ?array
            {
                return null;
            }

            public function markPublished(string $eventId): void {}
        });
        app()->forgetScopedInstances();
        $outboxOperation = (string) Str::uuid();
        $this->assertProtocolFailure(fn (): array => $this->publisher()->publish($offering['id'], 0, ['PROJECTION_A'], $outboxOperation), 'xray_protocol_failure');
        $this->assertDatabaseMissing('operator_xray_protocol_mappings', ['service_offering_id' => $offering['id']]);
        $this->assertDatabaseMissing('operator_xray_protocol_versions', []);
        $this->assertDatabaseMissing('idempotent_consumptions', ['message_id' => $outboxOperation]);
        $this->assertDatabaseMissing('audit_events', ['action' => 'operator.xray-protocol.published']);
    }

    /** @param list<string> $permissions */
    private function admin(array $permissions): User
    {
        $admin = User::factory()->create(['email' => 'protocol-admin-'.Str::lower(Str::random(8)).'@example.test']);
        $this->grant($admin, ['administrator'], $permissions);

        return $admin;
    }

    /** @return array{id: string, code: string} */
    private function offering(string $code = 'SYNTHETIC_SERVICE'): array
    {
        $id = (string) Str::uuid();
        DB::table('service_offerings')->insert([
            'id' => $id,
            'code' => $code,
            'name' => 'Synthetic service offering',
            'includes_ai' => false,
            'includes_doctor' => false,
            'point_price' => '1.0000',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return compact('id', 'code');
    }

    /** @param list<string> $roles @param list<string> $permissions */
    private function grant(User $user, array $roles, array $permissions): void
    {
        foreach ($roles as $role) {
            DB::table('authorization_role_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'role' => $role,
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($permissions as $permission) {
            DB::table('authorization_permission_assignments')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'permission' => $permission,
                'assigned_by_user_id' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function publisher(): OperatorXrayProtocolConfigurationService
    {
        return app(OperatorXrayProtocolConfigurationService::class);
    }

    private function insertPdo(PDO $pdo, string $sql, array $values): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($values);
    }

    private function mysqlPdo(): PDO
    {
        $config = config('database.connections.mysql');

        return new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }

    private function assertProtocolFailure(Closure $callback, string $category): void
    {
        try {
            $callback();
            $this->fail('The protocol publication must fail closed.');
        } catch (OperatorException $exception) {
            $this->assertSame($category, $exception->category);
        }
    }
}
