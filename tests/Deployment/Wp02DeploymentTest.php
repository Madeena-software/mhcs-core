<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class Wp02DeploymentTest extends TestCase
{
    public function test_deployment_provenance_and_roles_are_versioned_without_live_actions(): void
    {
        $compose = file_get_contents(base_path('docker-compose.prod.yml'));
        $workflow = file_get_contents(base_path('.github/workflows/security-validation.yml'));
        $provenance = file_get_contents(base_path('deployment/README.md'));
        $environment = file_get_contents(base_path('.env.example'));
        $phpunit = file_get_contents(base_path('phpunit.xml'));
        $validation = file_get_contents(base_path('deployment/validate.sh'));

        $this->assertIsString($compose);
        $this->assertIsString($workflow);
        $this->assertIsString($provenance);
        $this->assertIsString($environment);
        $this->assertIsString($phpunit);
        $this->assertIsString($validation);
        $this->assertStringContainsString('569a30d4a089b0ee404ed6e963fdd2dfd96d3787', $provenance);
        $this->assertStringContainsString('scheduler:', $compose);
        $this->assertStringContainsString('image-worker:', $compose);
        $this->assertStringContainsString('external: true', $compose);
        $this->assertStringContainsString('MPIPS_NETWORK_NAME', $compose);
        $this->assertStringContainsString('MHCS_ENV_FILE', $compose);
        $this->assertStringContainsString('app_public:/var/www/public-files', $compose);
        $this->assertStringContainsString('app_cache:/var/www/html/bootstrap/cache', $compose);
        $this->assertStringContainsString('MHCS_IMAGE_WORKER_CPU_LIMIT', $compose);
        $this->assertStringContainsString('MHCS_IMAGE_WORKER_MEMORY_LIMIT', $compose);
        $this->assertStringContainsString('MHCS_IMAGE_WORKER_PIDS_LIMIT', $compose);
        $this->assertStringContainsString('MHCS_IMAGE_WORKER_EXECUTION_TIMEOUT_SECONDS', $compose);
        $this->assertStringContainsString('MHCS_IMAGE_WORKER_TMPFS_SIZE', $compose);
        $this->assertStringNotContainsString('  mpips:', $compose);
        $this->assertStringNotContainsString('MPIPS_IMAGE', $compose);
        $this->assertStringNotContainsString('\\${', $compose);
        $this->assertStringNotContainsString('ssh', strtolower($workflow));
        $this->assertStringNotContainsString('docker stack', strtolower($workflow));
        $this->assertStringContainsString('composer audit', $workflow);
        $this->assertStringContainsString('php artisan test', $workflow);
        $this->assertStringContainsString('npm run build', $workflow);
        $this->assertStringContainsString('docker compose', $validation);
        $this->assertStringContainsString('docker build', $validation);
        $this->assertStringContainsString('deployment/smoke.sh', $validation);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $environment);
        $this->assertStringContainsString('mysql:8.4', $environment);
        $this->assertStringContainsString('image: mysql:8.4', $compose);
        $this->assertStringContainsString('value="sqlite"', $phpunit);
        $this->assertStringContainsString('value=":memory:"', $phpunit);

        foreach ([
            'MHCS_IDENTIFIER_KEY',
            'MHCS_OBJECT_ENCRYPTION_KEY',
            'MHCS_ACCESS_GRANT_KEY',
            'MHCS_MANIFEST_KEY',
            'MHCS_MANIFEST_KEY_ID',
            'MHCS_LOGIN_PAIR_MAX_ATTEMPTS',
            'MHCS_LOGIN_ORIGIN_MAX_ATTEMPTS',
            'MHCS_LOGIN_IDENTIFIER_MAX_ATTEMPTS',
            'MHCS_LOGIN_DECAY_SECONDS',
            'MHCS_IMAGE_FILE_COUNT',
            'MHCS_IMAGE_PER_FILE_BYTES',
            'MHCS_IMAGE_TOTAL_BYTES',
            'MHCS_IMAGE_DECOMPRESSED_BYTES',
            'MHCS_IMAGE_MAX_WIDTH',
            'MHCS_IMAGE_MAX_HEIGHT',
            'MHCS_IMAGE_FIELD_COUNT',
            'MHCS_IMAGE_CPU_SECONDS',
            'MHCS_IMAGE_MEMORY_BYTES',
            'MHCS_IMAGE_EXECUTION_SECONDS',
            'MHCS_IMAGE_PROCESS_COUNT',
            'MHCS_IMAGE_TEMPORARY_STORAGE_BYTES',
            'MHCS_IMAGE_RECOVERY_WINDOW_SECONDS',
            'MHCS_IMAGE_MAX_ATTEMPTS',
            'MHCS_IMAGE_WORKER_CPU_LIMIT',
            'MHCS_IMAGE_WORKER_MEMORY_LIMIT',
            'MHCS_IMAGE_WORKER_PIDS_LIMIT',
            'MHCS_IMAGE_WORKER_EXECUTION_TIMEOUT_SECONDS',
            'MHCS_IMAGE_WORKER_TMPFS_SIZE',
        ] as $name) {
            $this->assertStringContainsString($name, $environment);
        }
    }
}
