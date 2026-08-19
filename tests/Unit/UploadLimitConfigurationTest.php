<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class UploadLimitConfigurationTest extends TestCase
{
    public function test_one_upload_setting_drives_each_file_pair_and_native_request_limits(): void
    {
        $fileBytes = 100 * 1024 * 1024;

        $this->assertSame(100, config('mhcs.upload.max_file_mb'));
        $this->assertSame($fileBytes, config('mhcs.upload.max_file_bytes'));
        $this->assertSame($fileBytes, config('mhcs.image_policy.per_file_bytes'));
        $this->assertSame($fileBytes * 2, config('mhcs.image_policy.total_bytes'));
        $this->assertSame(($fileBytes * 2) + (1024 * 1024), config('mhcs.upload.max_request_bytes'));
    }

    public function test_runtime_upload_limits_match_the_mhcs_upload_policy(): void
    {
        $maxFileMb = (int) config('mhcs.upload.max_file_mb');
        $maxRequestMb = intdiv((int) config('mhcs.upload.max_request_bytes'), 1024 * 1024);
        $nginx = file_get_contents(base_path('docker/nginx.conf'));
        $php = file_get_contents(base_path('docker/php.ini'));

        $this->assertIsString($nginx);
        $this->assertIsString($php);

        preg_match('/^\s*client_max_body_size\s+(\S+)\s*;/mi', $nginx, $nginxLimit);
        preg_match('/^\s*upload_max_filesize\s*=\s*(\S+)\s*$/mi', $php, $phpFileLimit);
        preg_match('/^\s*post_max_size\s*=\s*(\S+)\s*$/mi', $php, $phpRequestLimit);

        $this->assertSame($maxRequestMb.'m', $nginxLimit[1] ?? null);
        $this->assertSame($maxFileMb.'M', $phpFileLimit[1] ?? null);
        $this->assertSame($maxRequestMb.'M', $phpRequestLimit[1] ?? null);
    }

    public function test_environment_example_has_one_shared_upload_limit(): void
    {
        $environment = file_get_contents(base_path('.env.example'));

        $this->assertIsString($environment);
        $this->assertStringContainsString('MHCS_MAX_UPLOAD_MB=100', $environment);
        $this->assertStringNotContainsString('MHCS_PHP_POST_MAX_SIZE', $environment);
        $this->assertStringNotContainsString('MHCS_PHP_UPLOAD_MAX_FILESIZE', $environment);
        $this->assertStringNotContainsString('MHCS_IMAGE_PER_FILE_BYTES', $environment);
        $this->assertStringNotContainsString('MHCS_IMAGE_TOTAL_BYTES', $environment);
    }
}
