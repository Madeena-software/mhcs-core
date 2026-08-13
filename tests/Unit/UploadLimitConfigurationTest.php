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
