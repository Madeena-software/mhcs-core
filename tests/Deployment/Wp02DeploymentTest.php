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

        $this->assertIsString($compose);
        $this->assertIsString($workflow);
        $this->assertIsString($provenance);
        $this->assertStringContainsString('569a30d4a089b0ee404ed6e963fdd2dfd96d3787', $provenance);
        $this->assertStringContainsString('scheduler:', $compose);
        $this->assertStringContainsString('image-worker:', $compose);
        $this->assertStringContainsString('external: true', $compose);
        $this->assertStringContainsString('MPIPS_NETWORK_NAME', $compose);
        $this->assertStringContainsString('MHCS_ENV_FILE', $compose);
        $this->assertStringContainsString('app_public:/var/www/public-files', $compose);
        $this->assertStringNotContainsString('  mpips:', $compose);
        $this->assertStringNotContainsString('MPIPS_IMAGE', $compose);
        $this->assertStringNotContainsString('\\${', $compose);
        $this->assertStringNotContainsString('ssh', strtolower($workflow));
        $this->assertStringNotContainsString('docker stack', strtolower($workflow));
        $this->assertStringContainsString('composer audit', $workflow);
        $this->assertStringContainsString('php artisan test', $workflow);
        $this->assertStringContainsString('npm run build', $workflow);
    }
}
