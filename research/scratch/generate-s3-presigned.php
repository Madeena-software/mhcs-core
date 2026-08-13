<?php

declare(strict_types=1);

# ═══════════════════════════════════════════════════════════════════════════════
# SIMAMA — AWS S3 SDK Generate Pre-Signed URL Helper Script
# ═══════════════════════════════════════════════════════════════════════════════

echo "================================================================\n";
echo "      GENERATING AWS S3 PRE-SIGNED URL FOR DUMMY FILE           \n";
echo "================================================================\n";

# 1. Load Composer Autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "❌ ERROR: Composer autoloader not found! Please run 'composer install' first.\n";
    exit(1);
}
require $autoloadPath;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function resolveS3Endpoint(?string $parsedEndpoint = null): string
{
    if (is_string($parsedEndpoint) && trim($parsedEndpoint) !== '') {
        return trim($parsedEndpoint);
    }

    $configuredEndpoint = getenv('AWS_ENDPOINT');
    if (is_string($configuredEndpoint) && trim($configuredEndpoint) !== '') {
        return trim($configuredEndpoint);
    }

    return 'http://127.0.0.1:9000';
}

# 2. Parse .env.local manually to get S3 credentials
$envPath = __DIR__ . '/../.env.local';
if (!file_exists($envPath)) {
    echo "❌ ERROR: .env.local file not found at $envPath\n";
    exit(1);
}

echo "[*] Parsing S3 credentials from .env.local...\n";
$config = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) {
        continue;
    }
    if (str_contains($line, '=')) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (in_array($key, [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_DEFAULT_REGION',
            'AWS_BUCKET',
            'AWS_ENDPOINT'
        ])) {
            $config[$key] = $value;
        }
    }
}

# Verify configuration
$requiredKeys = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET'];
foreach ($requiredKeys as $key) {
    if (!isset($config[$key]) || empty($config[$key])) {
        echo "❌ ERROR: Missing required configuration '$key' in .env.local!\n";
        exit(1);
    }
}

$config['AWS_ENDPOINT'] = resolveS3Endpoint($config['AWS_ENDPOINT'] ?? null);

echo "  ✅ AWS_ENDPOINT: " . $config['AWS_ENDPOINT'] . "\n";
echo "  ✅ AWS_BUCKET:   " . $config['AWS_BUCKET'] . "\n";
echo "  ✅ AWS_REGION:   " . $config['AWS_DEFAULT_REGION'] . "\n\n";

# 3. Instantiate S3 Client using the official AWS SDK
echo "[*] Initializing AWS S3 Client...\n";
$s3 = new S3Client([
    'version'     => 'latest',
    'region'      => $config['AWS_DEFAULT_REGION'],
    'endpoint'    => $config['AWS_ENDPOINT'],
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => $config['AWS_ACCESS_KEY_ID'],
        'secret' => $config['AWS_SECRET_ACCESS_KEY'],
    ],
]);

$bucket = $config['AWS_BUCKET'];
$testKey = 'test/dummy.txt';
$dummyContent = "Hello! This is a test file successfully uploaded to MinIO S3 storage gateway (" . $bucket . ") for pre-signed link verification.\nTimestamp: " . date('Y-m-d H:i:s T') . "\n";

try {
    # A. Check/Create Bucket
    echo "[*] 1. Checking if bucket '$bucket' exists...\n";
    if (!$s3->doesBucketExist($bucket)) {
        echo "  ℹ️ Bucket does not exist. Creating '$bucket'...\n";
        $s3->createBucket(['Bucket' => $bucket]);
        echo "  ✅ Bucket created successfully.\n";
    } else {
        echo "  ✅ Bucket exists and is accessible.\n";
    }

    # B. Upload File
    echo "[*] 2. Uploading dummy file to 's3://$bucket/$testKey'...\n";
    $s3->putObject([
        'Bucket'        => $bucket,
        'Key'           => $testKey,
        'Body'          => $dummyContent,
        'ContentLength' => strlen($dummyContent),
        'ContentType'   => 'text/plain',
    ]);
    echo "  ✅ Upload successful.\n";

    # C. Generate Pre-Signed URL
    echo "[*] 3. Generating pre-signed GET request (Expires: +1 hour)...\n";
    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key'    => $testKey,
    ]);

    $request = $s3->createPresignedRequest($cmd, '+1 hour');
    $presignedUrl = (string) $request->getUri();

    echo "\n================================================================\n";
    echo " 🎉 SUCCESS! Your Pre-Signed URL has been generated:\n";
    echo "================================================================\n";
    echo $presignedUrl . "\n";
    echo "================================================================\n";
    echo "💡 copy & paste the URL into your browser to verify the result.\n";
    echo "💡 Note: The file will remain on the server for verification.\n\n";

} catch (AwsException $e) {
    echo "❌ AWS S3 EXCEPTION FAILED:\n";
    echo "  Message: " . $e->getAwsErrorMessage() . "\n";
    echo "  Code:    " . $e->getAwsErrorCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ GENERAL EXCEPTION FAILED:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    exit(1);
}
