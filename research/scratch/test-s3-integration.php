<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use Dotenv\Dotenv;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

Dotenv::createImmutable($root)->safeLoad();

$value = static function (string $key): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && trim($value) !== '' ? trim($value) : null;
};

$required = [
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_DEFAULT_REGION',
    'AWS_BUCKET',
];

foreach ($required as $key) {
    if ($value($key) === null) {
        echo "S3_PROBE=CONFIG_MISSING\n";
        exit(1);
    }
}

$options = [
    'version' => 'latest',
    'region' => $value('AWS_DEFAULT_REGION'),
    'use_path_style_endpoint' => in_array(strtolower((string) $value('AWS_USE_PATH_STYLE_ENDPOINT')), ['1', 'true', 'on', 'yes'], true),
    'credentials' => [
        'key' => $value('AWS_ACCESS_KEY_ID'),
        'secret' => $value('AWS_SECRET_ACCESS_KEY'),
    ],
    'http' => [
        'connect_timeout' => 10,
        'timeout' => 30,
    ],
];

if (($endpoint = $value('AWS_ENDPOINT')) !== null) {
    $options['endpoint'] = $endpoint;
}

$s3 = new S3Client($options);
$bucket = $value('AWS_BUCKET');
$key = 'scratch/s3-probe-'.bin2hex(random_bytes(16)).'.txt';
$payload = 'mhcs-s3-probe-'.bin2hex(random_bytes(16));
$created = false;
$passed = false;
$cleaned = false;

try {
    $s3->putObject([
        'Bucket' => $bucket,
        'Key' => $key,
        'Body' => $payload,
        'ContentLength' => strlen($payload),
        'ContentType' => 'text/plain',
    ]);
    $created = true;

    $result = $s3->getObject([
        'Bucket' => $bucket,
        'Key' => $key,
    ]);
    $passed = (string) $result['Body'] === $payload;
} catch (Throwable) {
    $passed = false;
} finally {
    if ($created) {
        try {
            $s3->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
            $cleaned = true;
        } catch (Throwable) {
            $cleaned = false;
        }
    }
}

echo 'S3_PROBE='.($passed ? 'PASS' : 'FAIL').' CLEANUP='.($cleaned ? 'PASS' : ($created ? 'FAIL' : 'NOT_REQUIRED'))."\n";
exit($passed && $cleaned ? 0 : 1);
