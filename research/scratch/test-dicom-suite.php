<?php

declare(strict_types=1);

# ═══════════════════════════════════════════════════════════════════════════════
# SIMAMA — Combined DICOM Diagnostic & S3 Integration Suite
# ═══════════════════════════════════════════════════════════════════════════════

echo "================================================================\n";
echo "      STARTING COMPLETE DICOM DIAGNOSTIC & S3 INTEGRATION SUITE  \n";
echo "================================================================\n";

// 1. Load Composer Autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo "❌ ERROR: Composer autoloader not found! Please run 'composer install' first.\n";
    exit(1);
}
require $autoloadPath;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// 2. Parse .env.local manually to get S3 credentials
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

// Verify configuration
$requiredKeys = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET'];
foreach ($requiredKeys as $key) {
    if (!isset($config[$key]) || empty($config[$key])) {
        echo "❌ ERROR: Missing required configuration '$key' in .env.local!\n";
        exit(1);
    }
}

$accessKey = $config['AWS_ACCESS_KEY_ID'];
$secretKey = $config['AWS_SECRET_ACCESS_KEY'];
$region = $config['AWS_DEFAULT_REGION'];
$bucket = $config['AWS_BUCKET'];
$endpoint = $config['AWS_ENDPOINT'] ?? 'http://127.0.0.1:9000';

echo "  ✅ AWS_ENDPOINT: $endpoint\n";
echo "  ✅ AWS_BUCKET:   $bucket\n";
echo "  ✅ AWS_REGION:   $region\n\n";

// 3. Initialize S3 Client
echo "[*] Initializing AWS S3 Client...\n";
$s3 = new S3Client([
    'version'     => 'latest',
    'region'      => $region,
    'endpoint'    => $endpoint,
    'use_path_style_endpoint' => true,
    'credentials' => [
        'key'    => $accessKey,
        'secret' => $secretKey,
    ],
    'http' => [
        'timeout'         => 300, // Generous response timeout for large file uploads
        'connect_timeout' => 10,
    ]
]);

// Ensure bucket exists
try {
    if (!$s3->doesBucketExist($bucket)) {
        echo "  ℹ️ Bucket does not exist. Creating '$bucket'...\n";
        $s3->createBucket(['Bucket' => $bucket]);
        echo "  ✅ Bucket created successfully.\n";
    } else {
        echo "  ✅ Bucket exists and is accessible.\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: Failed to access or create bucket: " . $e->getMessage() . "\n";
    exit(1);
}

// =========================================================================
// PHASE I: MULTI-SIZE CHUNK DIAGNOSTICS
// =========================================================================
echo "\n--- PHASE I: MULTI-SIZE CHUNK DIAGNOSTICS (DUMMY UPLOADS) ---\n";
$sizes = [
    '10KB'  => 10 * 1024,
    '100KB' => 100 * 1024,
    '1MB'   => 1024 * 1024,
    '5MB'   => 5 * 1024 * 1024,
];

foreach ($sizes as $label => $bytes) {
    echo "[*] Testing $label ($bytes bytes)... ";
    
    $content = str_repeat('A', $bytes);
    $key = "test/speedtest-$label.txt";
    
    $startTime = microtime(true);
    try {
        $s3->putObject([
            'Bucket'      => $bucket,
            'Key'         => $key,
            'Body'        => $content,
            'ContentType' => 'text/plain',
        ]);
        $duration = microtime(true) - $startTime;
        $speed = ($bytes / (1024 * 1024)) / $duration; // MB/s
        
        printf("✅ SUCCESS! Time: %.3f s | Speed: %.3f MB/s (%.1f KB/s)\n", $duration, $speed, $speed * 1024);
        
        // Clean up
        $s3->deleteObject([
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
        
    } catch (AwsException $e) {
        $duration = microtime(true) - $startTime;
        printf("❌ FAILED after %.3f s! Message: %s\n", $duration, substr($e->getMessage(), 0, 100));
    } catch (\Exception $e) {
        $duration = microtime(true) - $startTime;
        printf("❌ FAILED after %.3f s! Message: %s\n", $duration, substr($e->getMessage(), 0, 100));
    }
}

// =========================================================================
// PHASE II: 21MB REAL DICOM UPLOAD
// =========================================================================
echo "\n--- PHASE II: 21MB REAL DICOM UPLOAD ---\n";
$filePath = __DIR__ . '/../temp/3-WWI-03B_Thorax_PA.dcm';
if (!file_exists($filePath)) {
    echo "❌ ERROR: DICOM file not found at $filePath\n";
    echo "⚠️ Skipping Phase II and Phase III.\n";
    exit(0);
}
$fileSize = filesize($filePath);
$dicomKey = 'test/3-WWI-03B_Thorax_PA.dcm';

echo "[*] Uploading real DICOM file (" . number_format($fileSize / (1024 * 1024), 2) . " MB)... \n";
$startTime = microtime(true);
try {
    $s3->putObject([
        'Bucket'     => $bucket,
        'Key'        => $dicomKey,
        'SourceFile' => $filePath,
    ]);
    
    $duration = microtime(true) - $startTime;
    $speed = ($fileSize / (1024 * 1024)) / $duration; // MB/s
    
    printf("✅ SUCCESS! Uploaded to s3://%s/%s\n", $bucket, $dicomKey);
    printf("  Time taken:   %.2f seconds\n", $duration);
    printf("  Upload speed: %.2f MB/s (%.1f KB/s)\n", $speed, $speed * 1024);
    
} catch (AwsException $e) {
    $duration = microtime(true) - $startTime;
    printf("❌ FAILED after %.2f seconds! Message: %s\n", $duration, $e->getAwsErrorMessage());
    exit(1);
} catch (\Exception $e) {
    $duration = microtime(true) - $startTime;
    printf("❌ FAILED after %.2f seconds! Message: %s\n", $duration, $e->getMessage());
    exit(1);
}

// =========================================================================
// PHASE III: PRE-SIGNED DOWNLOAD LINK GENERATION
// =========================================================================
echo "\n--- PHASE III: PRE-SIGNED DOWNLOAD LINK GENERATION ---\n";
echo "[*] Generating pre-signed GET request (Expires: +24 hours)...\n";
try {
    $cmd = $s3->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key'    => $dicomKey,
    ]);

    $request = $s3->createPresignedRequest($cmd, '+24 hours');
    $presignedUrl = (string) $request->getUri();
    
    echo "\n================================================================\n";
    echo " 🎉 SUCCESS! Your DICOM Pre-Signed URL has been generated:\n";
    echo "================================================================\n";
    echo $presignedUrl . "\n";
    echo "================================================================\n";
    echo "💡 Copy and paste this URL into your browser to download/test.\n";
    echo "💡 Note: The file remains stored on MinIO at s3://$bucket/$dicomKey\n\n";

} catch (\Exception $e) {
    echo "❌ Failed to generate pre-signed URL: " . $e->getMessage() . "\n";
}

echo "════════════════════════════════════════════════════════════════\n";
echo "  🎉 COMPLETE DICOM INTEGRATION SUITE FINISHED SUCCESSFULLY!     \n";
echo "════════════════════════════════════════════════════════════════\n";
