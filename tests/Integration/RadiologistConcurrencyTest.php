<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Doctor\Application\Services\RadiologistWorkspace;
use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RadiologistFixtures;
use Tests\TestCase;

final class RadiologistConcurrencyTest extends TestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
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

    private function prepared(bool $assign = true): array
    {
        $f = RadiologistFixtures::create();
        $this->actingAs($f['admin']);
        $this->get('/login');
        $s = app(RadiologistWorkspace::class);
        $s->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password']);
        if (! $assign) {
            return [$f, null, null];
        }
        $w = $s->manage($f['handoff'], 'issue', ['user' => $f['doctor']->id, 'password' => 'password']);
        $this->actingAs($f['doctor']);
        $this->get($w['url'])->assertRedirect();
        $p = ['revision' => 0, 'binding' => $f['binding'], 'handoff' => $f['handoff']];
        $s->command($w['id'], 'quality', $p + ['decision' => 'usable'], 1);
        $p['revision'] = 1;
        $s->command($w['id'], 'draft', $p + ['findings' => 'Synthetic findings', 'conclusion' => 'Synthetic conclusion', 'recommendations' => ''], 1);
        $p['revision'] = 2;

        return [$f, $w, $p];
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

    private function pair(callable $a, callable $b): array
    {
        DB::disconnect();
        $start = function ($operation) {
            return $this->child(function ($pipe) use ($operation) {
                fwrite($pipe, "ready\n");
                fgets($pipe);

                return $operation();
            });
        };
        $one = $start($a);
        $two = $start($b);
        $this->assertSame("ready\n", fgets($one[1]));
        $this->assertSame("ready\n", fgets($two[1]));
        fwrite($one[1], "go\n");
        fwrite($two[1], "go\n");

        return [$this->finish($one), $this->finish($two)];
    }

    public function test_assign_assign_creates_one_generation(): void
    {
        [$f] = $this->prepared(false);
        $op = fn () => app(RadiologistWorkspace::class)->manage($f['handoff'], 'issue', ['user' => $f['doctor']->id, 'password' => 'password']);
        [$a,$b] = $this->pair($op, $op);
        $this->assertSame(1, (int) $a['ok'] + (int) $b['ok']);
        $this->assertSame(1, DB::table('doctor_workspaces')->count());
    }

    public function test_save_save_preserves_one_revision(): void
    {
        [$f,$w,$p] = $this->prepared();
        $op = fn () => app(RadiologistWorkspace::class)->command($w['id'], 'draft', $p + ['findings' => 'Concurrent findings', 'conclusion' => 'Conclusion', 'recommendations' => ''], 1);
        [$a,$b] = $this->pair($op, $op);
        $this->assertSame(1, (int) $a['ok'] + (int) $b['ok']);
        $this->assertSame(2, DB::table('doctor_report_versions')->count());
    }

    public function test_finalize_finalize_is_idempotent(): void
    {
        [$f,$w,$p] = $this->prepared();
        $op = fn () => app(RadiologistWorkspace::class)->command($w['id'], 'finalize', $p + ['password' => 'password', 'attest' => true, 'key' => 'same-operation'], 1);
        [$a,$b] = $this->pair($op, $op);
        $this->assertTrue($a['ok']);
        $this->assertTrue($b['ok']);
        $this->assertSame(1, DB::table('doctor_finalizations')->count());
    }

    #[DataProvider('conflicts')]
    public function test_finalization_serializes_with_revocation_reassignment_and_reopen(string $change): void
    {
        [$f,$w,$p] = $this->prepared();
        $finalize = fn () => app(RadiologistWorkspace::class)->command($w['id'], 'finalize', $p + ['password' => 'password', 'attest' => true, 'key' => 'race-final'], 1);
        $mutation = function () use ($f, $change) {
            if ($change === 'account') {
                return DB::table('users')->where('id', $f['doctor']->id)->update(['account_status' => 'suspended']);
            }
            $this->actingAs($f['admin']);
            if ($change === 'eligibility') {
                return app(RadiologistWorkspace::class)->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'status' => 'revoked', 'evidence' => 'SYNTHETIC-REVOCATION', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password']);
            }
            if ($change === 'reopen') {
                return app(RadiographerWorkspace::class)->manage($f['reference'], 'reopen', ['handoff' => $f['handoff'], 'reason' => 'Synthetic race']);
            }

            return app(RadiologistWorkspace::class)->manage($f['handoff'], $change, ['generation' => 1, 'user' => $f['doctor']->id, 'password' => 'password']);
        };
        [$a,$b] = $this->pair($finalize, $mutation);
        $this->assertTrue($a['ok'] || $b['ok']);
        if ($change === 'reassign' || $change === 'reopen') {
            $this->assertSame(1, (int) $a['ok'] + (int) $b['ok']);
        }
        $this->assertSame($a['ok'] ? 1 : 0, DB::table('doctor_finalizations')->count());
        if ($a['ok']) {
            $this->assertSame($f['handoff'], DB::table('operator_radiographer_workspaces')->value('active_handoff'));
        }
    }

    public static function conflicts(): array
    {
        return [['reassign'], ['reopen'], ['account'], ['eligibility']];
    }

    #[DataProvider('disclosures')]
    public function test_committed_reassignment_withholds_prepared_response(string $suffix): void
    {
        [$f,$w] = $this->prepared();
        $response = $this->get('/radiologist/workspaces/'.$w['id'].$suffix)->assertOk()->baseResponse;
        DB::disconnect();
        $child = $this->child(function () use ($f) {
            $this->actingAs($f['admin']);

            return app(RadiologistWorkspace::class)->manage($f['handoff'], 'reassign', ['generation' => 1, 'user' => $f['doctor']->id, 'password' => 'password']);
        });
        $this->assertTrue($this->finish($child)['ok']);
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('SYNTHETIC-MRN', $body);
        $this->assertStringNotContainsString('DICM', $body);
    }

    public static function disclosures(): array
    {
        return [[''], ['/viewer'], ['/dicom']];
    }

    #[DataProvider('atomicFailures')]
    public function test_required_audit_or_commit_failure_rolls_back_finalization(string $failure): void
    {
        [$f,$w,$p] = $this->prepared();
        $before = DB::table('doctor_workspace_history')->count();
        if ($failure === 'append') {
            $store = app(AuditStore::class);
            $this->app->instance(AuditStore::class, new class($store) implements AuditStore
            {
                public function __construct(private AuditStore $store) {}

                public function append(AuditEvent $event): void
                {
                    if ($event->action === 'radiologist-workspace.finalize-committed') {
                        throw new \RuntimeException('Synthetic append failure');
                    }
                    $this->store->append($event);
                }
            });
        } else {
            $this->app['events']->listen(TransactionCommitting::class, function () {
                if (DB::table('doctor_finalizations')->count() > 0) {
                    throw new \RuntimeException('Synthetic commit failure');
                }
            });
        }
        try {
            app(RadiologistWorkspace::class)->command($w['id'], 'finalize', $p + ['password' => 'password', 'attest' => true, 'key' => 'atomic-failure'], 1);
            $this->fail('Failure committed final report');
        } catch (\DomainException) {
        }
        $this->assertSame(0, DB::table('doctor_finalizations')->count());
        $this->assertSame($before, DB::table('doctor_workspace_history')->count());
        $this->assertSame('not_finalized', DB::table('doctor_workspaces')->value('clinical_status'));
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
    }

    public static function atomicFailures(): array
    {
        return [['append'], ['commit']];
    }
}
