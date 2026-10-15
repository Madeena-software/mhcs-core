<?php

declare(strict_types=1);
use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\SyntheticClinicalStatusSource;

if (getenv('APP_ENV') !== 'testing') {
    throw new RuntimeException('Synthetic testing environment required.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('framework/testing/radiographer-browser');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
// Share one current test instant across fixture commands and HTTP requests.
if (PHP_SAPI === 'cli' && ($argv[1] ?? null) === 'seed') {
    file_put_contents($directory.'/clock', gmdate('c'));
}
if (is_file($directory.'/clock')) {
    Carbon::setTestNow(trim(file_get_contents($directory.'/clock')));
    $app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
}
$database = $directory.'/radiographer-browser.sqlite';
if (! file_exists($database)) {
    touch($database);
}
config(['app.env' => 'testing', 'app.debug' => false, 'app.url' => 'http://127.0.0.1:18099',
    'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
    'database.connections.sqlite.url' => null, 'session.driver' => 'file', 'session.files' => $directory,
    'cache.default' => 'array', 'queue.default' => 'sync', 'mhcs.private_object_disk' => 'local',
    'filesystems.disks.local.root' => $directory.'/objects',
    'mhcs.security.identifier_key' => 'synthetic-identifier-key', 'mhcs.security.grant_key' => 'synthetic-grant-key',
    'mhcs.security.manifest_key' => str_repeat('m', 32), 'mhcs.security.manifest_key_id' => 'synthetic-key',
    'telescope.enabled' => false, 'pulse.enabled' => false]);
DB::purge('sqlite');
$app->bind(ClinicalStatusSource::class, SyntheticClinicalStatusSource::class);

return $app;
