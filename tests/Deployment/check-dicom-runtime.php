<?php

declare(strict_types=1);

// Copy this synthetic-only probe and SyntheticDicom.php into a disposable container.
require '/var/www/html/vendor/autoload.php';
require __DIR__.'/SyntheticDicom.php';

use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\Dicom\BoundedDicomVerifier;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Tests\Support\SyntheticDicom;

Container::setInstance(new Application('/var/www/html'));
$check = static function (bool $ok): void {
    if (! $ok) {
        throw new RuntimeException('Synthetic runtime check failed.');
    }
};
$check(function_exists('posix_geteuid') && posix_geteuid() === 33);
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
