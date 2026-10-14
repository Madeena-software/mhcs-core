<?php

declare(strict_types=1);

// Copy this synthetic-only probe and SyntheticDicom.php into a disposable container.
require '/var/www/html/vendor/autoload.php';
require __DIR__.'/SyntheticDicom.php';

use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\Dicom\BoundedDicomVerifier;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Tests\Support\SyntheticDicom;

Container::setInstance(new Application('/var/www/html'));
$check = static function (bool $ok): void {
    if (! $ok) {
        throw new RuntimeException('Synthetic runtime check failed.');
    }
};
$check(function_exists('posix_geteuid') && posix_geteuid() === 33);
$lock = json_decode(file_get_contents('/var/www/html/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$required = [];
foreach ($lock['packages'] as $package) {
    foreach (array_keys($package['require'] ?? []) as $requirement) {
        if (str_starts_with($requirement, 'ext-')) {
            $required[] = substr($requirement, 4);
        }
    }
}
foreach (array_unique($required) as $extension) {
    $check(extension_loaded($extension));
}
echo 'PASS: all '.count(array_unique($required))." locked production PHP extension requirements\n";
$verifier = new BoundedDicomVerifier;
$identity = ['patientId' => 'SYNTHETIC-MRN', 'patientName' => 'SYNTHETIC^ONLY'];
foreach ([false, true] as $implicit) {
    $bytes = SyntheticDicom::bytes(implicit: $implicit);
    $hash = hash('sha256', $bytes);
    $result = $verifier->verify($bytes, $hash, strlen($bytes), $identity);
    $check($result['studyInstanceUid'] === '2.25.100' && $result['frames'] === 1 && hash('sha256', $bytes) === $hash);
}
foreach ([
    SyntheticDicom::bytes(['00020003' => ['UI', '2.25.999']]),
    SyntheticDicom::bytes(['00020010' => ['UI', '1.2.840.10008.1.2.1.99']]),
    substr(SyntheticDicom::bytes(), 0, -2),
    SyntheticDicom::bytes(extra: SyntheticDicom::element('00100020', 'LO', 'OTHER')),
] as $bytes) {
    try {
        $verifier->verify($bytes, hash('sha256', $bytes), strlen($bytes), $identity);
        throw new RuntimeException('Unsafe synthetic input was accepted.');
    } catch (ImageGatewayException $exception) {
        $check($exception->category === 'DICOM_UNVERIFIED' && $exception->getPrevious() === null);
    }
}
echo "PASS: target PHP-to-Node adapter, www-data uid=33, explicit/implicit synthetic Part-10, immutable hash, conflicting/unsupported/truncated/duplicate rejection\n";
$filter = (new ReflectionMethod(BoundedDicomVerifier::class, 'networkFilter'))->invoke($verifier);
try {
    $network = new Process([
        '/usr/bin/setpriv', '--no-new-privs', '--seccomp-filter', stream_get_meta_data($filter)['uri'], '--',
        'node', '-e', 'require("node:net").connect(9,"127.0.0.1").on("error",e=>{if(e.code!=="EPERM")process.exit(1)});',
    ]);
    $network->mustRun();
    $check($network->getOutput() === '' && $network->getErrorOutput() === '');
} finally {
    fclose($filter);
}
$cpu = new Process(['prlimit', '--cpu=1', '--core=0', '--', 'node', '--jitless', '-e', 'while(true){}'], timeout: 3);
try {
    $cpu->run();
} catch (ProcessSignaledException $exception) {
    $check(in_array($exception->getSignal(), [9, 24], true));
}
$check(! $cpu->isSuccessful());
$memory = new Process(['prlimit', '--as=1073741824', '--core=0', '--', 'node', '--jitless', '--max-old-space-size=64', '-e', 'Buffer.alloc(2*1024*1024*1024)']);
$memory->run();
$check(! $memory->isSuccessful());
$permissions = new Process(['node', '--permission', '-e', 'try {require("node:fs").readFileSync("/etc/hostname");process.exit(1)}catch(e){if(e.code!=="ERR_ACCESS_DENIED")process.exit(1)}']);
$permissions->mustRun();
echo "PASS: kernel socket denial, CPU termination, allocation limit, filesystem permission denial\n";
$version = new Process(['node', '--version']);
$version->mustRun();
echo 'Runtime: PHP '.PHP_VERSION.', Node '.trim($version->getOutput())."\n";
