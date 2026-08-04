<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class MemberDatabaseConformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_mode_and_foreign_keys_are_enforced(): void
    {
        $this->assertTrue((bool) config('database.connections.mysql.strict'));

        if (DB::connection()->getDriverName() === 'mysql') {
            $mode = (string) DB::selectOne('select @@session.sql_mode as mode')->mode;

            $this->assertMatchesRegularExpression('/(^|,)STRICT_(TRANS_TABLES|ALL_TABLES)(,|$)/', $mode);
            $this->assertSame('InnoDB', DB::selectOne('select @@default_storage_engine as engine')->engine);
        } else {
            $this->assertTrue((bool) config('database.connections.sqlite.foreign_key_constraints'));
        }

        try {
            DB::table('members')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => (string) Str::uuid(),
                'family_id' => null,
                'medical_record_number' => (string) Str::uuid(),
                'identity_status' => 'pending_verification',
                'identity_document_type' => 'ktp',
                'encrypted_nik' => 'synthetic',
                'nik_lookup_digest' => hash('sha256', (string) Str::uuid()),
                'name' => 'Synthetic constraint probe',
                'birth_date' => '1985-08-04',
                'administrative_gender' => 'unspecified',
                'registration_source' => 'administrator',
                'phone' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail('A member with a missing authentication foreign key must be rejected.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_uniqueness_and_transaction_rollback_are_enforced_by_the_database(): void
    {
        $operationId = 'conformance-'.Str::uuid();
        $row = $this->operationRow($operationId);
        DB::table('member_operations')->insert($row);

        try {
            DB::table('member_operations')->insert($row);
            $this->fail('A duplicate operation key must be rejected.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $rolledBackId = 'rollback-'.Str::uuid();

        try {
            DB::transaction(function () use ($rolledBackId): void {
                DB::table('member_operations')->insert($this->operationRow($rolledBackId));
                throw new RuntimeException('synthetic rollback');
            });
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseMissing('member_operations', ['operation_id' => $rolledBackId]);
    }

    public function test_concurrent_duplicate_operation_is_rejected_by_unique_constraint(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('The concurrency probe requires MySQL and proc_open.');
        }

        $operationId = 'concurrent-'.Str::uuid();
        $row = $this->operationRow($operationId);
        $parent = $this->mysqlPdo();
        $parent->beginTransaction();
        $this->insertOperation($parent, $row);

        $worker = <<<'PHP'
$row = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
);
$statement = $pdo->prepare(
    'insert into member_operations (id, operation_type, operation_id, payload_hash, status, result, created_at, updated_at) '.
    'values (:id, :operation_type, :operation_id, :payload_hash, :status, :result, :created_at, :updated_at)',
);
echo "ready\n";
flush();
try {
    $statement->execute($row);
    echo 'inserted';
} catch (PDOException) {
    echo 'rejected';
}
PHP;

        $process = proc_open(
            [PHP_BINARY, '-r', $worker, base64_encode(json_encode($row, JSON_THROW_ON_ERROR))],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($process)) {
            $parent->rollBack();
            throw new RuntimeException('Unable to start the concurrency probe.');
        }

        $ready = trim((string) fgets($pipes[1]));

        if ($ready !== 'ready') {
            $parent->rollBack();
            proc_terminate($process);
            proc_close($process);
            throw new RuntimeException('The concurrency probe did not start.');
        }

        $parent->commit();
        $outcome = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $parent->exec('delete from member_operations where operation_id = '.$parent->quote($operationId));

        $this->assertSame('rejected', $outcome);
        $this->assertSame(0, $exitCode);
    }

    public function test_concurrent_mysql_direct_approved_asset_recording_keeps_one_current_asset(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('The direct asset-recording concurrency probe requires MySQL and proc_open.');
        }

        $pdo = $this->mysqlPdo();
        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $now = '2026-08-04 10:00:00';
        $assetIds = [(string) Str::uuid(), (string) Str::uuid()];

        try {
            $this->insertPdo($pdo, 'insert into users (id, email, email_verified_at, password, remember_token, account_status, login_enabled, must_change_password, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$userId, 'direct-recording-'.str_replace('-', '', $userId).'@example.test', null, 'hash', null, 'active', 1, 0, $now, $now]);
            $this->insertPdo($pdo, 'insert into members (id, user_id, family_id, medical_record_number, identity_status, identity_document_type, encrypted_nik, nik_lookup_digest, name, birth_date, administrative_gender, registration_source, phone, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$memberId, $userId, null, (string) Str::uuid(), 'pending_verification', 'ktp', 'synthetic', hash('sha256', $memberId), 'Synthetic direct recording member', '1985-08-04', 'unspecified', 'administrator', null, $now, $now]);

            $pdo->beginTransaction();
            $pdo->prepare('select id from members where id = ? for update')->execute([$memberId]);
            $worker = <<<'PHP'
$root = getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make('config')->set([
    'mhcs.security.identifier_key' => str_repeat('i', 32),
    'mhcs.security.object_key' => str_repeat('o', 32),
    'mhcs.security.grant_key' => str_repeat('g', 32),
]);
$input = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$context = new \App\Shared\Context\AuthenticatedContext(
    actorId: \App\Shared\Identity\LocalId::fromString($input['actor_id']),
    operationId: new \App\Shared\Context\CorrelationId($input['operation_id']),
    roles: ['administrator'],
    permissions: ['member.registration.manage', 'member.identity.verify'],
    purpose: 'member.registration',
);
$app->instance(\App\Shared\Context\AuthenticatedContextProvider::class, new class($context) implements \App\Shared\Context\AuthenticatedContextProvider {
    public function __construct(private readonly \App\Shared\Context\AuthenticatedContext $context) {}
    public function current(): \App\Shared\Context\AuthenticatedContext { return $this->context; }
});
$member = \App\Modules\Member\Domain\Models\Member::query()->findOrFail($input['member_id']);
$object = new \App\Shared\Storage\PrivateObject(
    key: \App\Shared\Storage\OpaqueObjectKey::fromString('objects/'.$input['object']),
    checksum: str_repeat($input['checksum'], 64),
    bytes: 1,
    encryption: 'AES-256-GCM',
    createdAt: new \DateTimeImmutable('2026-08-04T10:00:00+00:00'),
);
echo "ready\n";
flush();
try {
    $id = app(\App\Modules\Member\Application\Services\MemberVerificationAssetService::class)->recordForRegistration(
        $member,
        new \App\Modules\Member\Application\Data\VerificationAssetInput(\App\Modules\Member\Domain\Enums\VerificationAssetType::ProfilePhoto, $object, 'image/jpeg'),
        $context,
    );
    echo $id;
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    echo "failed";
    exit(1);
}
PHP;

            $processes = [];
            foreach ($assetIds as $index => $assetId) {
                $input = base64_encode(json_encode([
                    'actor_id' => $userId,
                    'member_id' => $memberId,
                    'operation_id' => 'direct-recording-'.$index.'-'.str_replace('-', '', $memberId),
                    'object' => $assetId,
                    'checksum' => dechex($index + 10),
                ], JSON_THROW_ON_ERROR));
                $pipes = [];
                $process = proc_open([PHP_BINARY, '-r', $worker, $input], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start the direct recording probe.');
                }
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as [$process, $pipes]) {
                $this->assertSame('ready', trim((string) fgets($pipes[1])));
            }
            $pdo->commit();

            foreach ($processes as [$process, $pipes]) {
                $output = trim((string) stream_get_contents($pipes[1]));
                $error = (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
                $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $output, $error);
                $this->assertSame(0, $exitCode, $error);
            }

            $current = $pdo->prepare("select count(*) as total from member_verification_assets where member_id = ? and type = 'profile_photo' and review_status = 'approved' and is_current = 1");
            $current->execute([$memberId]);
            $this->assertSame(1, (int) $current->fetch(PDO::FETCH_ASSOC)['total']);
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->prepare('delete from member_verification_assets where member_id = ?')->execute([$memberId]);
            $pdo->prepare('delete from members where id = ?')->execute([$memberId]);
            $pdo->prepare('delete from users where id = ?')->execute([$userId]);
        }
    }

    public function test_uuid_upgrade_preserves_legacy_users_and_sessions_and_blocks_unsafe_down(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mhcs-legacy-');
        $this->assertNotFalse($path);
        $connection = config('database.connections.sqlite');
        $default = config('database.default');
        config([
            'database.default' => 'migration_preservation',
            'database.connections.migration_preservation' => array_merge($connection, ['database' => $path]),
        ]);
        DB::purge('migration_preservation');

        try {
            Schema::create('users', function ($table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->string('account_status', 32)->default('active');
                $table->boolean('must_change_password')->default(false);
                $table->timestamps();
            });
            Schema::create('sessions', function ($table): void {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });

            $password = '$2y$04$legacy-password-hash';
            DB::table('users')->insert([
                'id' => 41,
                'name' => 'Legacy User',
                'email' => 'legacy@example.test',
                'email_verified_at' => null,
                'password' => $password,
                'remember_token' => 'legacy-remember-token',
                'account_status' => 'active',
                'must_change_password' => true,
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ]);
            DB::table('sessions')->insert([
                'id' => 'legacy-session',
                'user_id' => 41,
                'ip_address' => '198.51.100.10',
                'user_agent' => 'legacy-agent',
                'payload' => 'legacy-payload',
                'last_activity' => 1,
            ]);

            $migration = require database_path('migrations/2026_08_04_000007_migrate_users_to_uuid.php');
            $migration->up();

            $migrated = DB::table('users')->where('email', 'legacy@example.test')->first();
            $session = DB::table('sessions')->where('id', 'legacy-session')->first();
            $this->assertNotNull($migrated);
            $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $migrated->id);
            $this->assertSame($password, $migrated->password);
            $this->assertTrue((bool) $migrated->must_change_password);
            $this->assertSame($migrated->id, $session->user_id);
            $this->assertSame('legacy-payload', $session->payload);
            $this->assertFalse(Schema::hasTable('wp04_legacy_users'));
            $this->assertFalse(Schema::hasTable('wp04_legacy_sessions'));

            $this->expectException(RuntimeException::class);
            $migration->down();
        } finally {
            DB::disconnect('migration_preservation');
            config(['database.default' => $default]);
            DB::purge('migration_preservation');
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_mysql_uuid_upgrade_preserves_populated_legacy_users_and_sessions(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('The populated legacy UUID upgrade probe requires MySQL.');
        }

        $default = config('database.default');
        $connection = config('database.connections.mysql');
        $prefix = 'm'.substr(str_replace('-', '', (string) Str::uuid()), 0, 8).'_';
        config([
            'database.default' => 'migration_mysql',
            'database.connections.migration_mysql' => array_merge($connection, ['prefix' => $prefix]),
        ]);
        DB::purge('migration_mysql');

        try {
            Schema::create('users', function ($table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->string('account_status', 32)->default('active');
                $table->boolean('must_change_password')->default(false);
                $table->timestamps();
            });
            Schema::create('sessions', function ($table): void {
                $table->string('id')->primary();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
            DB::table('users')->insert([
                'id' => 73,
                'name' => 'MySQL Legacy User',
                'email' => 'mysql-legacy@example.test',
                'email_verified_at' => null,
                'password' => '$2y$04$mysql-legacy-password-hash',
                'remember_token' => 'mysql-remember-token',
                'account_status' => 'active',
                'must_change_password' => true,
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ]);
            DB::table('sessions')->insert([
                'id' => 'mysql-legacy-session',
                'user_id' => 73,
                'ip_address' => '198.51.100.11',
                'user_agent' => 'legacy-agent',
                'payload' => 'legacy-payload',
                'last_activity' => 1,
            ]);

            $migration = require database_path('migrations/2026_08_04_000007_migrate_users_to_uuid.php');
            $migration->up();
            $migrated = DB::table('users')->where('email', 'mysql-legacy@example.test')->first();
            $session = DB::table('sessions')->where('id', 'mysql-legacy-session')->first();
            $this->assertNotNull($migrated);
            $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $migrated->id);
            $this->assertSame($migrated->id, $session->user_id);
            $this->assertSame('$2y$04$mysql-legacy-password-hash', $migrated->password);
            $this->assertTrue((bool) $migrated->must_change_password);
            $this->assertSame('legacy-payload', $session->payload);

            try {
                $migration->down();
                $this->fail('The forward-only UUID migration must not claim reversibility.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        } finally {
            Schema::dropIfExists('sessions');
            Schema::dropIfExists('users');
            Schema::dropIfExists('wp04_legacy_sessions');
            Schema::dropIfExists('wp04_legacy_users');
            DB::disconnect('migration_mysql');
            config(['database.default' => $default]);
            DB::purge('migration_mysql');
        }
    }

    public function test_concurrent_mysql_asset_approval_keeps_one_approved_current_asset(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('The MySQL asset concurrency probe requires MySQL and proc_open.');
        }

        $pdo = $this->mysqlPdo();
        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $oldAssetId = (string) Str::uuid();
        $replacementIds = [(string) Str::uuid(), (string) Str::uuid()];
        $now = '2026-08-04 10:00:00';

        try {
            $this->insertPdo($pdo, 'insert into users (id, email, email_verified_at, password, remember_token, account_status, login_enabled, must_change_password, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $userId,
                'asset-concurrency-'.str_replace('-', '', $userId).'@example.test',
                null,
                'hash',
                null,
                'active',
                1,
                0,
                $now,
                $now,
            ]);
            $this->insertPdo($pdo, 'insert into members (id, user_id, family_id, medical_record_number, identity_status, identity_document_type, encrypted_nik, nik_lookup_digest, name, birth_date, administrative_gender, registration_source, phone, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $memberId,
                $userId,
                null,
                (string) Str::uuid(),
                'verified',
                'ktp',
                'synthetic',
                hash('sha256', $memberId),
                'Synthetic concurrency member',
                '1985-08-04',
                'unspecified',
                'administrator',
                null,
                $now,
                $now,
            ]);
            $assetInsert = 'insert into member_verification_assets (id, member_id, type, private_object_key, checksum, bytes, format, review_status, is_current, uploaded_by_user_id, reviewed_by_user_id, reviewed_at, replaces_id, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $this->insertPdo($pdo, $assetInsert, [
                $oldAssetId,
                $memberId,
                'profile_photo',
                'objects/old',
                str_repeat('a', 64),
                1,
                'image/jpeg',
                'approved',
                1,
                $userId,
                $userId,
                $now,
                null,
                $now,
                $now,
            ]);
            foreach ($replacementIds as $replacementId) {
                $this->insertPdo($pdo, $assetInsert, [
                    $replacementId,
                    $memberId,
                    'profile_photo',
                    'objects/'.$replacementId,
                    str_repeat('b', 64),
                    1,
                    'image/jpeg',
                    'pending',
                    0,
                    $userId,
                    null,
                    null,
                    $oldAssetId,
                    $now,
                    $now,
                ]);
            }

            $pdo->beginTransaction();
            $pdo->prepare('select id from members where id = ? for update')->execute([$memberId]);

            $worker = <<<'PHP'
$root = getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make('config')->set([
    'mhcs.security.identifier_key' => str_repeat('i', 32),
    'mhcs.security.object_key' => str_repeat('o', 32),
    'mhcs.security.grant_key' => str_repeat('g', 32),
    'mhcs.security.login' => [
        'pair_max_attempts' => 5,
        'origin_max_attempts' => 10,
        'identifier_max_attempts' => 20,
        'decay_seconds' => 60,
    ],
]);
$input = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$context = new \App\Shared\Context\AuthenticatedContext(
    actorId: \App\Shared\Identity\LocalId::fromString($input['actor_id']),
    operationId: new \App\Shared\Context\CorrelationId($input['operation_id']),
    roles: ['administrator'],
    permissions: ['member.identity.verify'],
    purpose: 'member.identity.verify',
);
$app->instance(\App\Shared\Context\AuthenticatedContextProvider::class, new class($context) implements \App\Shared\Context\AuthenticatedContextProvider {
    public function __construct(private readonly \App\Shared\Context\AuthenticatedContext $context) {}
    public function current(): \App\Shared\Context\AuthenticatedContext { return $this->context; }
});
echo "ready\n";
flush();
try {
    app(\App\Modules\Member\Application\Services\MemberVerificationAssetService::class)->review($input['asset_id'], true);
    echo "approved\n";
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    echo "failed\n";
    exit(1);
}
PHP;

            $processes = [];
            foreach ($replacementIds as $index => $assetId) {
                $input = base64_encode(json_encode([
                    'actor_id' => $userId,
                    'operation_id' => 'asset-concurrency-'.$index.'-'.str_replace('-', '', $memberId),
                    'asset_id' => $assetId,
                ], JSON_THROW_ON_ERROR));
                $pipes = [];
                $process = proc_open([PHP_BINARY, '-r', $worker, $input], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start the asset concurrency probe.');
                }
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as [$process, $pipes]) {
                $this->assertSame('ready', trim((string) fgets($pipes[1])));
            }
            $pdo->commit();

            foreach ($processes as [$process, $pipes]) {
                $output = trim((string) stream_get_contents($pipes[1]));
                $error = (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
                $this->assertSame('approved', $output, $error);
                $this->assertSame(0, $exitCode, $error);
            }

            $current = $pdo->prepare("select count(*) as total from member_verification_assets where member_id = ? and type = 'profile_photo' and review_status = 'approved' and is_current = 1");
            $current->execute([$memberId]);
            $approvedCurrent = (int) $current->fetch(PDO::FETCH_ASSOC)['total'];
            $this->assertSame(1, $approvedCurrent);
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->prepare('delete from member_verification_assets where member_id = ? and id <> ?')->execute([$memberId, $oldAssetId]);
            $pdo->prepare('delete from member_verification_assets where id = ?')->execute([$oldAssetId]);
            $pdo->prepare('delete from members where id = ?')->execute([$memberId]);
            $pdo->prepare('delete from users where id = ?')->execute([$userId]);
        }
    }

    public function test_concurrent_mysql_identity_document_approval_keeps_one_current_document(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('The identity-document concurrency probe requires MySQL and proc_open.');
        }

        $pdo = $this->mysqlPdo();
        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $oldKiaId = (string) Str::uuid();
        $replacementIds = [(string) Str::uuid(), (string) Str::uuid()];
        $now = '2026-08-04 10:00:00';

        try {
            $this->insertPdo($pdo, 'insert into users (id, email, email_verified_at, password, remember_token, account_status, login_enabled, must_change_password, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$userId, 'identity-concurrency-'.str_replace('-', '', $userId).'@example.test', null, 'hash', null, 'active', 1, 0, $now, $now]);
            $this->insertPdo($pdo, 'insert into members (id, user_id, family_id, medical_record_number, identity_status, identity_document_type, encrypted_nik, nik_lookup_digest, name, birth_date, administrative_gender, registration_source, phone, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$memberId, $userId, null, (string) Str::uuid(), 'pending_verification', 'ktp', 'synthetic', hash('sha256', $memberId), 'Synthetic identity concurrency member', '1985-08-04', 'unspecified', 'administrator', null, $now, $now]);
            $assetInsert = 'insert into member_verification_assets (id, member_id, type, private_object_key, checksum, bytes, format, review_status, is_current, uploaded_by_user_id, reviewed_by_user_id, reviewed_at, replaces_id, created_at, updated_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $this->insertPdo($pdo, $assetInsert, [$oldKiaId, $memberId, 'kia', 'objects/'.$oldKiaId, str_repeat('a', 64), 1, 'image/jpeg', 'approved', 1, $userId, $userId, $now, null, $now, $now]);
            foreach ($replacementIds as $replacementId) {
                $this->insertPdo($pdo, $assetInsert, [$replacementId, $memberId, 'ktp', 'objects/'.$replacementId, str_repeat('b', 64), 1, 'image/jpeg', 'pending', 0, $userId, null, null, $oldKiaId, $now, $now]);
            }

            $pdo->beginTransaction();
            $pdo->prepare('select id from members where id = ? for update')->execute([$memberId]);
            $worker = <<<'PHP'
$root = getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app->make('config')->set([
    'mhcs.security.identifier_key' => str_repeat('i', 32),
    'mhcs.security.object_key' => str_repeat('o', 32),
    'mhcs.security.grant_key' => str_repeat('g', 32),
]);
$input = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$context = new \App\Shared\Context\AuthenticatedContext(
    actorId: \App\Shared\Identity\LocalId::fromString($input['actor_id']),
    operationId: new \App\Shared\Context\CorrelationId($input['operation_id']),
    roles: ['administrator'],
    permissions: ['member.identity.verify'],
    purpose: 'member.identity.verify',
);
$app->instance(\App\Shared\Context\AuthenticatedContextProvider::class, new class($context) implements \App\Shared\Context\AuthenticatedContextProvider {
    public function __construct(private readonly \App\Shared\Context\AuthenticatedContext $context) {}
    public function current(): \App\Shared\Context\AuthenticatedContext { return $this->context; }
});
echo "ready\n";
flush();
try {
    app(\App\Modules\Member\Application\Services\MemberVerificationAssetService::class)->review($input['asset_id'], true);
    echo "approved";
} catch (\Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    echo "failed";
    exit(1);
}
PHP;
            $processes = [];
            foreach ($replacementIds as $index => $assetId) {
                $input = base64_encode(json_encode(['actor_id' => $userId, 'asset_id' => $assetId, 'operation_id' => 'identity-concurrency-'.$index.'-'.str_replace('-', '', $memberId)], JSON_THROW_ON_ERROR));
                $pipes = [];
                $process = proc_open([PHP_BINARY, '-r', $worker, $input], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($process)) {
                    throw new RuntimeException('Unable to start the identity concurrency probe.');
                }
                $processes[] = [$process, $pipes];
            }
            foreach ($processes as [$process, $pipes]) {
                $this->assertSame('ready', trim((string) fgets($pipes[1])));
            }
            $pdo->commit();
            foreach ($processes as [$process, $pipes]) {
                $output = trim((string) stream_get_contents($pipes[1]));
                $error = (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
                $this->assertSame('approved', $output, $error);
                $this->assertSame(0, $exitCode, $error);
            }

            $current = $pdo->prepare("select count(*) as total from member_verification_assets where member_id = ? and type in ('ktp', 'kia') and review_status = 'approved' and is_current = 1");
            $current->execute([$memberId]);
            $this->assertSame(1, (int) $current->fetch(PDO::FETCH_ASSOC)['total']);
            $ktp = $pdo->prepare("select count(*) as total from member_verification_assets where member_id = ? and type = 'ktp' and review_status = 'approved' and is_current = 1");
            $ktp->execute([$memberId]);
            $this->assertSame(1, (int) $ktp->fetch(PDO::FETCH_ASSOC)['total']);
            $metadata = $pdo->prepare('select identity_document_type from members where id = ?');
            $metadata->execute([$memberId]);
            $this->assertSame('ktp', $metadata->fetch(PDO::FETCH_ASSOC)['identity_document_type']);
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pdo->prepare('delete from member_verification_assets where member_id = ? and id <> ?')->execute([$memberId, $oldKiaId]);
            $pdo->prepare('delete from member_verification_assets where id = ?')->execute([$oldKiaId]);
            $pdo->prepare('delete from members where id = ?')->execute([$memberId]);
            $pdo->prepare('delete from users where id = ?')->execute([$userId]);
        }
    }

    /** @return array<string, mixed> */
    private function operationRow(string $operationId): array
    {
        return [
            'id' => (string) Str::uuid(),
            'operation_type' => 'database-conformance',
            'operation_id' => $operationId,
            'payload_hash' => hash('sha256', $operationId),
            'status' => 'pending',
            'result' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /** @param array<string, mixed> $row */
    private function insertOperation(PDO $pdo, array $row): void
    {
        $statement = $pdo->prepare(
            'insert into member_operations (id, operation_type, operation_id, payload_hash, status, result, created_at, updated_at) '.
            'values (:id, :operation_type, :operation_id, :payload_hash, :status, :result, :created_at, :updated_at)',
        );
        $statement->execute($row);
    }

    /** @param list<mixed> $values */
    private function insertPdo(PDO $pdo, string $sql, array $values): void
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($values);
    }

    private function mysqlPdo(): PDO
    {
        $config = config('database.connections.mysql');

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset'],
            ),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }
}
