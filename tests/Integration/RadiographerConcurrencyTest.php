<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Modules\Operator\Application\Contracts\PendingRadiographerHandoff;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RadiographerFixtures;
use Tests\Support\SyntheticClinicalStatusSource;
use Tests\TestCase;

final class RadiographerConcurrencyTest extends TestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MHCS_RAD_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Requires disposable mhcs_rad_synthetic MySQL.');
        }
        config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.host' => 'mysql', 'database.connections.mysql.database' => 'mhcs_rad_synthetic', 'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '', 'database.connections.mysql.port' => 3306]);
        DB::purge('mysql');
        $this->assertSame('mhcs_rad_synthetic', DB::selectOne('select database() as db')->db);
        if (! self::$schemaReady) {
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            self::$schemaReady = true;
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (Schema::getTableListing(schemaQualified: false) as $table) {
                    if ($table !== 'migrations') {
                        DB::table($table)->delete();
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
        $disk = 'radiographer_mysql_'.getmypid();
        config(['mhcs.private_object_disk' => $disk, 'filesystems.disks.'.$disk => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/'.$disk)]]);
        Storage::fake($disk);
    }

    private function prepared(): array
    {
        $f = RadiographerFixtures::create();
        $this->actingAs($f['admin']);
        $service = app(RadiographerWorkspace::class);
        $w = $service->manage($f['reference'], 'issue', ['user' => $f['worker']->id]);
        $this->actingAs($f['worker']);
        $r = $service->read($w['id'], 1);
        $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);

        return [$f, $w, ['revision' => 1, 'binding' => $r['binding'], 'key' => 'concurrent']];
    }

    private function child(callable $operation): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($pair[0]);
            try {
                $result = $operation($pair[1]);
                fwrite($pair[1], json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR)."\n");
            } catch (\DomainException) {
                fwrite($pair[1], json_encode(['ok' => false])."\n");
            } catch (\Throwable $e) {
                fwrite($pair[1], json_encode(['error' => get_class($e)])."\n");
            }
            fclose($pair[1]);
            exit(0);
        }
        fclose($pair[1]);
        stream_set_timeout($pair[0], 20);

        return [$pid, $pair[0]];
    }

    private function finish(array $child): array
    {
        $data = fgets($child[1]);
        pcntl_waitpid($child[0], $status);
        fclose($child[1]);
        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertNotFalse($data, 'Child timed out');
        $result = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('error', $result);

        return $result;
    }

    public function test_two_connections_create_one_idempotent_handoff(): void
    {
        [$f,$w,$input] = $this->prepared();
        DB::disconnect();
        $operation = function ($pipe) use ($w, $input) {
            fwrite($pipe, "ready\n");
            fgets($pipe);

            return app(RadiographerWorkspace::class)->handoff($w['id'], $input, 1);
        };
        $a = $this->child($operation);
        $b = $this->child($operation);
        $this->assertSame("ready\n", fgets($a[1]));
        $this->assertSame("ready\n", fgets($b[1]));
        fwrite($a[1], "go\n");
        fwrite($b[1], "go\n");
        $one = $this->finish($a);
        $two = $this->finish($b);
        $this->assertTrue($one['ok']);
        $this->assertTrue($two['ok']);
        $this->assertSame($one['result'], $two['result']);
        $this->assertSame(1, DB::table('operator_radiographer_handoffs')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'radiographer-workspace.handed-off')->count());
    }

    public function test_committed_account_revocation_blocks_waiting_handoff(): void
    {
        [$f,$w,$input] = $this->prepared();
        DB::disconnect();
        $writer = $this->child(function ($pipe) use ($f) {
            return DB::transaction(function () use ($pipe, $f) {
                DB::table('users')->where('id', $f['worker']->id)->lockForUpdate()->first();
                DB::table('users')->where('id', $f['worker']->id)->update(['account_status' => 'suspended']);
                fwrite($pipe, "locked\n");
                fgets($pipe);

                return 'revoked';
            });
        });
        $this->assertSame("locked\n", fgets($writer[1]));
        $reader = $this->child(fn () => app(RadiographerWorkspace::class)->handoff($w['id'], $input, 1));
        $ready = [$reader[1]];
        $write = $except = [];
        $this->assertSame(0, stream_select($ready, $write, $except, 0, 250000), 'Handoff bypassed held revocation lock');
        fwrite($writer[1], "commit\n");
        $this->assertTrue($this->finish($writer)['ok']);
        $this->assertFalse($this->finish($reader)['ok']);
        $this->assertSame(0, DB::table('operator_radiographer_handoffs')->count());
    }

    public function test_same_actor_reassignment_blocks_a_waiting_old_generation_read(): void
    {
        [$f, $w] = $this->prepared();
        DB::disconnect();
        $writer = $this->child(function ($pipe) use ($f) {
            $this->actingAs($f['admin']);
            $this->app->instance(AuditStore::class, new class($pipe) implements AuditStore
            {
                public function __construct(private $pipe) {}

                public function append(AuditEvent $event): void
                {
                    (new DatabaseAuditStore)->append($event);
                    if ($event->action === 'radiographer-workspace.reassigned') {
                        fwrite($this->pipe, "locked\n");
                        fgets($this->pipe);
                    }
                }
            });

            return app(RadiographerWorkspace::class)->manage($f['reference'], 'reassign', ['user' => $f['worker']->id]);
        });
        $this->assertSame("locked\n", fgets($writer[1]));
        $reader = $this->child(fn () => app(RadiographerWorkspace::class)->read($w['id'], 1));
        $ready = [$reader[1]];
        $write = $except = [];
        $this->assertSame(0, stream_select($ready, $write, $except, 0, 250000));
        fwrite($writer[1], "commit\n");
        $this->assertTrue($this->finish($writer)['ok']);
        $this->assertFalse($this->finish($reader)['ok']);
        $this->assertSame(2, DB::table('operator_radiographer_workspaces')->value('generation'));
    }

    public function test_finalization_and_reopen_use_the_same_case_lock(): void
    {
        [$f,$w,$input] = $this->prepared();
        $h = app(RadiographerWorkspace::class)->handoff($w['id'], $input, 1);
        Schema::create('synthetic_clinical_status', function (Blueprint $t): void {
            $t->string('case_id', 36)->primary();
            $t->string('binding', 64);
            $t->string('handoff_id', 36);
            $t->string('status', 24);
            $t->unsignedInteger('revision');
        });
        DB::table('synthetic_clinical_status')->insert(['case_id' => $f['i']['booking'], 'binding' => $input['binding'], 'handoff_id' => $h['handoff'], 'status' => 'not_finalized', 'revision' => 1]);
        $this->app->instance(ClinicalStatusSource::class, new SyntheticClinicalStatusSource);
        DB::disconnect();
        $writer = $this->child(function ($pipe) use ($f, $input, $h) {
            return DB::transaction(function () use ($pipe, $f, $input, $h) {
                if (! app(PendingRadiographerHandoff::class)->isCurrent($h['handoff'], $input['binding'])) {
                    throw new \DomainException;
                }
                DB::table('doctor_clinical_guards')->where('case_id', $f['i']['booking'])->lockForUpdate()->first();
                DB::table('synthetic_clinical_status')->where('case_id', $f['i']['booking'])->update(['status' => 'finalized', 'revision' => 2]);
                fwrite($pipe, "locked\n");
                fgets($pipe);

                return 'finalized';
            });
        });
        $this->assertSame("locked\n", fgets($writer[1]));
        $reopen = $this->child(function () use ($f, $h) {
            $this->actingAs($f['admin']);

            return app(RadiographerWorkspace::class)->manage($f['reference'], 'reopen', ['reason' => 'synthetic-race', 'handoff' => $h['handoff']]);
        });
        $ready = [$reopen[1]];
        $write = $except = [];
        $this->assertSame(0, stream_select($ready, $write, $except, 0, 250000), 'Reopen bypassed clinical lock');
        fwrite($writer[1], "commit\n");
        $this->assertTrue($this->finish($writer)['ok']);
        $this->assertFalse($this->finish($reopen)['ok']);
        $this->assertSame(1, DB::table('operator_radiographer_workspaces')->value('cycle'));
        $this->assertSame($h['handoff'], DB::table('operator_radiographer_workspaces')->value('active_handoff'));
    }
}
