<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Http\Controllers\Member\ResultController;
use App\Modules\Member\Application\Contracts\ResultPublication;
use App\Modules\Member\Application\Services\MemberResultAccess;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MemberResultFixtures;
use Tests\TestCase;

final class MemberResultConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! in_array(getenv('MHCS_MEMBER_BACKEND'), ['mysql', 'sqlite'], true)) {
            $this->markTestSkipped('Explicit disposable Member backend required.');
        }
        $this->freezeTime();
        app()->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        if (getenv('MHCS_MEMBER_BACKEND') === 'mysql') {
            config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.host' => 'mysql', 'database.connections.mysql.database' => 'mhcs_rad_synthetic', 'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '', 'database.connections.mysql.port' => 3306]);
            DB::purge('mysql');
            $this->assertSame('mhcs_rad_synthetic', DB::selectOne('select database() as db')->db);
        } else {
            $directory = storage_path('framework/testing/member-races-'.bin2hex(random_bytes(6)));
            mkdir($directory, 0700, true);
            $file = $directory.'/member-races.sqlite';
            touch($file);
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $file, 'database.connections.sqlite.url' => null, 'database.connections.sqlite.busy_timeout' => 10000, 'database.connections.sqlite.journal_mode' => 'WAL']);
            DB::purge('sqlite');
        }
        $this->artisan(getenv('MHCS_MEMBER_BACKEND') === 'sqlite' ? 'migrate' : 'migrate:fresh', ['--force' => true])->assertExitCode(0);
        if (getenv('MHCS_MEMBER_BACKEND') === 'sqlite') {
            // Each race gets a new file; never replace a WAL database while a connection may retain it.
            $this->assertSame('wal', strtolower(DB::selectOne('PRAGMA journal_mode')->journal_mode));
        }
        $disk = 'member_result_'.getenv('MHCS_MEMBER_BACKEND');
        config(['mhcs.private_object_disk' => $disk, 'filesystems.disks.'.$disk => ['driver' => 'local', 'root' => storage_path('framework/testing/disks/'.$disk)]]);
        Storage::fake($disk);
        $this->get('/login');
        request()->session()->start();
    }

    private function fixture(): array
    {
        return MemberResultFixtures::finalize(MemberResultFixtures::prepare());
    }

    private function child(callable $operation): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($pair[0]);
            DB::disconnect();
            try {
                $result = $operation($pair[1]);
                fwrite($pair[1], json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR)."\n");
            } catch (\DomainException) {
                fwrite($pair[1], "{\"ok\":false}\n");
            } catch (\Throwable $e) {
                fwrite($pair[1], json_encode(['error' => get_class($e)])."\n");
            }
            fclose($pair[1]);
            exit(0);
        }
        fclose($pair[1]);
        stream_set_timeout($pair[0], 90);

        return [$pid, $pair[0]];
    }

    private function finish(array $child): array
    {
        $line = fgets($child[1]);
        pcntl_waitpid($child[0], $status);
        fclose($child[1]);
        $this->assertSame(0, pcntl_wexitstatus($status));
        $this->assertNotFalse($line, 'Child timeout');
        $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('error', $result);

        return $result;
    }

    private function pair(callable $a, callable $b): array
    {
        DB::disconnect();
        $start = fn ($op) => $this->child(function ($pipe) use ($op) {
            fwrite($pipe, "ready\n");
            fgets($pipe);

            return $op();
        });
        $one = $start($a);
        $two = $start($b);
        $this->assertSame("ready\n", fgets($one[1]));
        $this->assertSame("ready\n", fgets($two[1]));
        fwrite($one[1], "go\n");
        fwrite($two[1], "go\n");

        return [$this->finish($one), $this->finish($two)];
    }

    public function test_publish_publish_is_one_immutable_generation_and_retry(): void
    {
        $f = $this->fixture();
        $op = fn () => MemberResultFixtures::publish($f);
        $results = $this->pair($op, $op);
        $this->assertTrue($results[0]['ok'] || $results[1]['ok']);
        $this->assertSame(1, DB::table('member_result_publications')->count());
        $this->assertSame(1, DB::table('member_result_history')->count());
        $this->assertSame(1, DB::table('member_result_cases')->value('revision'));
    }

    public static function withdrawal(): array
    {
        return [['hold'], ['suspend'], ['revoke']];
    }

    #[DataProvider('withdrawal')]
    public function test_publish_withdrawal_cannot_both_use_same_revision(string $action): void
    {
        $f = $this->fixture();
        MemberResultFixtures::publish($f);
        app(MemberResultAccess::class)->manage($f['i']['booking'], 'hold', ['revision' => 1, 'key' => 'hold-initial']);
        $results = $this->pair(fn () => MemberResultFixtures::publish($f, 2, 'next'), fn () => app(MemberResultAccess::class)->manage($f['i']['booking'], $action, ['revision' => 2, 'key' => 'withdraw']));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])));
        $this->assertSame(3, DB::table('member_result_cases')->value('revision'));
        $this->assertSame(3, DB::table('member_result_history')->count());
    }

    public static function invalidation(): array
    {
        return array_map(fn ($s) => [$s], ['revoke', 'hold', 'suspend', 'identity', 'account', 'credential', 'lineage', 'expiry']);
    }

    #[DataProvider('invalidation')]
    public function test_prepared_response_after_independent_committed_invalidation_withholds(string $change): void
    {
        $f = $this->fixture();
        $p = MemberResultFixtures::publish($f);
        Auth::login($f['memberUser']);
        session()->put('member.result.credential', hash('sha256', $f['memberUser']->getAuthPassword()));
        session()->put(Auth::guard('web')->getName(), $f['memberUser']->id);
        session()->save();
        $request = Request::create($p['url']);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $f['memberUser']);
        $prepared = app(ResultController::class)->entry($request, $p['id'], app(ResultPublication::class));
        $this->assertSame(200, $prepared->getStatusCode());
        DB::disconnect();
        $child = $this->child(function () use ($f, $change, $p) {
            Auth::login($f['admin']);
            if (in_array($change, ['revoke', 'hold', 'suspend'], true)) {
                app(MemberResultAccess::class)->manage($f['i']['booking'], $change, ['revision' => 1, 'key' => 'withdraw']);
            } else {
                DB::transaction(function () use ($f, $change, $p) {
                    if (in_array($change, ['account', 'credential'], true)) {
                        DB::table('users')->where('id', $f['memberUser']->id)->lockForUpdate()->first();
                        DB::table('users')->where('id', $f['memberUser']->id)->update($change === 'account' ? ['account_status' => 'suspended'] : ['password' => bcrypt('synthetic-changed')]);
                    } elseif ($change === 'identity') {
                        DB::table('members')->where('id', $f['member'])->lockForUpdate()->first();
                        DB::table('member_verification_assets')->where('member_id', $f['member'])->update(['review_status' => 'rejected']);
                    } elseif ($change === 'lineage') {
                        DB::table('doctor_clinical_guards')->where('case_id', $f['i']['booking'])->lockForUpdate()->first();
                        DB::table('doctor_clinical_guards')->where('case_id', $f['i']['booking'])->update(['status' => 'unknown']);
                    } else {
                        DB::table('member_result_publications')->where('id', $p['id'])->update(['expires_at' => now()->subSecond()]);
                    }
                });
            }

            return true;
        });
        $this->assertTrue($this->finish($child)['ok']);
        ob_start();
        $prepared->sendContent();
        $body = ob_get_clean();
        $this->assertSame(403, $prepared->getStatusCode());
        $this->assertStringNotContainsString('Synthetic final findings.', $body);
    }

    public static function beforePublication(): array
    {
        return [['permission'], ['role'], ['facility'], ['lineage'], ['identity']];
    }

    #[DataProvider('beforePublication')]
    public function test_invalidation_committed_after_discovery_before_authorization_denies_publish(string $change): void
    {
        $f = $this->fixture();
        DB::disconnect();
        $child = $this->child(function ($pipe) use ($f) {
            $paused = false;
            DB::connection()->beforeExecuting(function ($sql) use ($pipe, &$paused) {
                if (! $paused && str_contains($sql, 'users') && str_contains($sql, 'order by')) {
                    $paused = true;
                    fwrite($pipe, "discovered\n");
                    fgets($pipe);
                }
            });

            return MemberResultFixtures::publish($f);
        });
        $this->assertSame("discovered\n", fgets($child[1]));
        DB::transaction(function () use ($f, $change) {
            if ($change === 'permission' || $change === 'role') {
                DB::table('users')->where('id', $f['admin']->id)->lockForUpdate()->first();
                if ($change === 'permission') {
                    DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'member.result-publication.publish')->update(['active' => false]);
                } else {
                    DB::table('authorization_role_assignments')->where('user_id', $f['admin']->id)->update(['active' => false]);
                }
            } elseif ($change === 'facility') {
                DB::table('member_result_managers')->where('user_id', $f['admin']->id)->update(['active' => false]);
            } elseif ($change === 'identity') {
                DB::table('members')->where('id', $f['member'])->update(['name' => 'Changed synthetic identity']);
            } else {
                DB::table('doctor_clinical_guards')->where('case_id', $f['i']['booking'])->update(['status' => 'unknown']);
            }
        });
        fwrite($child[1], "continue\n");
        $this->assertFalse($this->finish($child)['ok']);
        $this->assertSame(0, DB::table('member_result_publications')->count());
        $this->assertSame(0, DB::table('member_result_history')->count());
    }

    public function test_atomic_audit_commit_failure_rolls_back(): void
    {
        $f = $this->fixture();
        $fired = false;
        app('events')->listen(TransactionCommitting::class, function () use (&$fired) {
            if (! $fired && DB::table('member_result_history')->exists()) {
                $fired = true;
                throw new \RuntimeException('Synthetic commit failure');
            }
        });
        try {
            MemberResultFixtures::publish($f);
            $this->fail('Commit failure accepted');
        } catch (\Throwable) {
            $this->assertTrue($fired);
            $this->assertSame(0, DB::table('member_result_publications')->count());
            $this->assertSame(0, DB::table('member_result_history')->count());
        }
    }

    public function test_schema_teardown_and_recreation(): void
    {
        $migration = require database_path('migrations/2026_10_16_000002_create_member_result_publication_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('member_result_publications'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('member_result_publications'));
        $this->assertSame(0, DB::table('member_result_history')->count());
    }
}
