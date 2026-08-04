<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
