<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Doctor\Application\Services\RadiologistLoginAccess;
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
        request()->session()->start();
        $s = app(RadiologistWorkspace::class);
        $s->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password']);
        if (! $assign) {
            return [$f, null, null];
        }
        $w = $s->manage($f['handoff'], 'issue', ['user' => $f['doctor']->id, 'password' => 'password']);
        $this->actingAs($f['doctor']);
        $this->get($w['url'])->assertRedirect();
        request()->session()->start();
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

    public static function managementChanges(): array
    {
        return [['radiographer', 'reassign'], ['radiographer', 'revoke'], ['radiologist', 'reassign'], ['radiologist', 'revoke']];
    }

    #[DataProvider('managementChanges')]
    public function test_management_response_withholds_after_an_independent_assignment_change(string $role, string $change): void
    {
        [$f] = $this->prepared();
        $this->actingAs($f['admin']);
        $locator = $f[$role === 'radiographer' ? 'reference' : 'handoff'];
        $response = $this->getJson('/admin/'.$role.'-workspaces/'.$locator)->assertOk()->baseResponse;
        DB::disconnect();
        $child = $this->child(function () use ($f, $role, $change, $locator) {
            request()->session()->start();
            $service = app($role === 'radiographer' ? RadiographerWorkspace::class : RadiologistWorkspace::class);
            $input = $change === 'reassign' ? ['user' => $f[$role === 'radiographer' ? 'worker' : 'doctor']->id] : [];
            if ($role === 'radiologist') {
                $input += ['generation' => 1, 'password' => 'password'];
            }
            $service->manage($locator, $change, $input);

            return null;
        });
        $this->assertTrue($this->finish($child)['ok']);
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString('signature=', $body);
        $this->assertStringNotContainsString($f['doctor']->email, $body);
        $this->assertStringNotContainsString($f['worker']->email, $body);
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
                return app(RadiologistWorkspace::class)->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'status' => 'revoked', 'eligibility' => DB::table('doctor_eligibility')->orderByDesc('revision')->value('id'), 'evidence' => 'SYNTHETIC-REVOCATION', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password']);
            }
            if ($change === 'reopen') {
                return app(RadiographerWorkspace::class)->manage($f['reference'], 'reopen', ['handoff' => $f['handoff'], 'reason' => 'Synthetic race']);
            }

            return app(RadiologistWorkspace::class)->manage($f['handoff'], $change, ['generation' => 1, 'user' => $f['doctor']->id, 'password' => 'password']);
        };
        [$a,$b] = $this->pair($finalize, $mutation);
        $this->assertTrue($a['ok'] || $b['ok']);
        if ($change === 'reassign') {
            $this->assertSame(1, (int) $a['ok'] + (int) $b['ok']);
        }
        if ($change === 'reopen') {
            $this->assertTrue($a['ok'], 'Unknown-coverage reopen must not abort valid finalization');
            $this->assertFalse($b['ok'], 'Unproven reporting coverage must deny reopen');
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
    public function test_committed_access_loss_withholds_prepared_response(string $suffix, string $change): void
    {
        [$f,$w] = $this->prepared();
        $response = $this->get('/radiologist/workspaces/'.$w['id'].$suffix)->assertOk()->baseResponse;
        DB::disconnect();
        $child = $this->child(function () use ($f, $change) {
            $this->actingAs($f['admin']);
            request()->session()->start();
            if ($change === 'credential') {
                return app(RadiologistWorkspace::class)->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'status' => 'revoked', 'eligibility' => DB::table('doctor_eligibility')->orderByDesc('revision')->value('id'), 'evidence' => 'SYNTHETIC-REVOKE', 'password' => 'password']);
            }

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
        return [['', 'reassign'], ['/viewer', 'reassign'], ['/dicom', 'reassign'], ['', 'credential'], ['/viewer', 'credential'], ['/dicom', 'credential']];
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

    #[DataProvider('loginMutations')]
    public function test_login_account_lock_precedes_management_operator_lock(string $action): void
    {
        [$f, $w] = $this->prepared();
        $eligibility = DB::table('doctor_eligibility')->value('id');
        $intended = parse_url($w['url'], PHP_URL_PATH).'?'.parse_url($w['url'], PHP_URL_QUERY);
        DB::disconnect();
        $login = $this->child(function ($pipe) use ($f, $intended) {
            return DB::transaction(function () use ($pipe, $f, $intended) {
                DB::table('users')->where('id', $f['doctor']->id)->lockForUpdate()->first();
                fwrite($pipe, "account-held\n");
                fgets($pipe);

                return app(RadiologistLoginAccess::class)->destination($f['doctor'], $intended);
            });
        });
        $this->assertSame("account-held\n", fgets($login[1]));
        $management = $this->child(function ($pipe) use ($f, $action, $eligibility) {
            $this->actingAs($f['admin']);
            $signalled = false;
            DB::connection()->beforeExecuting(function ($sql, $bindings) use ($pipe, &$signalled, $f) {
                if (! $signalled && str_contains($sql, '`users`') && str_contains($sql, 'for update') && in_array($f['doctor']->id, $bindings, true)) {
                    $signalled = true;
                    fwrite($pipe, "account-requested\n");
                }
            });
            if ($action === 'credential') {
                return app(RadiologistWorkspace::class)->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'eligibility' => $eligibility, 'status' => 'revoked', 'evidence' => 'SYNTHETIC-RACE', 'password' => 'password']);
            }

            return app(RadiologistWorkspace::class)->manage($f['handoff'], $action, ['generation' => 1, 'password' => 'password'] + ($action === 'reassign' ? ['user' => $f['doctor']->id] : []));
        });
        $this->assertSame("account-requested\n", fgets($management[1]));
        try {
            // Management must wait on the account without holding the upstream row login needs.
            DB::transaction(fn () => DB::table('operator_radiographer_workspaces')->where('id', $f['radiographer_workspace'])->lock('for update nowait')->first());
        } finally {
            fwrite($login[1], "continue\n");
        }
        $admitted = $this->finish($login);
        $changed = $this->finish($management);
        $this->assertTrue($admitted['ok']);
        $this->assertSame($intended, $admitted['result']);
        $this->assertTrue($changed['ok']);
        $this->actingAs($f['doctor']);
        $this->assertNull(app(RadiologistLoginAccess::class)->destination($f['doctor'], $intended));
    }

    public static function loginMutations(): array
    {
        return [['credential'], ['reassign'], ['revoke']];
    }

    public function test_mysql_migration_rollback_preserves_original_and_handoff(): void
    {
        [$f] = $this->prepared(false);
        $original = (array) DB::table('image_gateway_studies')->first();
        $handoff = (array) DB::table('operator_radiographer_handoffs')->first();
        $migration = require database_path('migrations/2026_10_16_000001_create_radiologist_workspace_tables.php');
        $migration->down();
        $this->assertSame($original, (array) DB::table('image_gateway_studies')->first());
        $this->assertSame($handoff, (array) DB::table('operator_radiographer_handoffs')->first());
        $migration->up();
        $this->assertSame(0, DB::table('doctor_workspaces')->count());
    }

    public function test_finalization_operator_lock_precedes_reopen_source_lock(): void
    {
        [$f, $w, $p] = $this->prepared();
        DB::disconnect();
        $finalize = $this->child(function ($pipe) use ($w, $p) {
            $paused = false;
            DB::connection()->beforeExecuting(function ($sql) use ($pipe, &$paused) {
                if (! $paused && str_contains($sql, 'insert ignore into `doctor_clinical_guards`')) {
                    $paused = true;
                    fwrite($pipe, "operator-held\n");
                    fgets($pipe);
                }
            });

            return app(RadiologistWorkspace::class)->command($w['id'], 'finalize', $p + ['password' => 'password', 'attest' => true, 'key' => 'ordered-final'], 1);
        });
        $this->assertSame("operator-held\n", fgets($finalize[1]));
        $reopen = $this->child(function ($pipe) use ($f) {
            $this->actingAs($f['admin']);
            $paused = false;
            DB::connection()->beforeExecuting(function ($sql) use ($pipe, &$paused) {
                if (! $paused && str_contains($sql, 'operator_radiographer_workspaces') && str_contains($sql, 'for update')) {
                    $paused = true;
                    fwrite($pipe, "operator-requested\n");
                    fgets($pipe);
                }
            });

            return app(RadiographerWorkspace::class)->manage($f['reference'], 'reopen', ['handoff' => $f['handoff'], 'reason' => 'Synthetic ordered reopen']);
        });
        $this->assertSame("operator-requested\n", fgets($reopen[1]));
        try {
            // Reopen must not hold Gateway rows while waiting for the Operator row.
            DB::transaction(fn () => DB::table('image_gateway_studies')->where('id', $f['study'])->lock('for update nowait')->first());
        } finally {
            fwrite($finalize[1], "continue\n");
            fwrite($reopen[1], "continue\n");
        }
        $this->assertTrue($this->finish($finalize)['ok']);
        $this->assertFalse($this->finish($reopen)['ok']);
        $this->assertSame(1, DB::table('doctor_finalizations')->count());
        $this->assertSame($f['handoff'], DB::table('operator_radiographer_workspaces')->value('active_handoff'));
    }
}
