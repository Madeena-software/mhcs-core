<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionPrestigeUploadDiagnosticProvisioningWorkflowTest extends TestCase
{
    public function test_workflow_resolves_exact_container_and_rechecks_after_provisioning(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/provision-production-prestige-upload-diagnostic-members.yml'));
        $this->assertIsString($workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('timeout-minutes: 10', $workflow);
        $this->assertStringContainsString('AUTHORIZE_ONE_PRESTIGE_UPLOAD_DIAGNOSTIC_MEMBER_PROVISION', $workflow);
        $this->assertStringContainsString('docker service ps', $workflow);
        $this->assertStringContainsString('{{.Config.Image}}', $workflow);
        $this->assertStringContainsString('VERSION-CURRENT', $workflow);
        $this->assertStringContainsString('docker exec "$APP_CONTAINER" php artisan mhcs:provision-prestige-upload-diagnostic-members', $workflow);
        $this->assertStringContainsString('production_revision_verified=true', $workflow);
        $this->assertStringContainsString('cat "$output"', $workflow);
        $this->assertStringNotContainsString('org.opencontainers.image.revision', $workflow);
        $this->assertStringNotContainsString('php artisan mhcs:provision-prestige-upload-diagnostic-members\n', preg_replace('/docker exec.*\n/', '', $workflow));
        $this->assertStringNotContainsString('retry', strtolower($workflow));
        $this->assertStringNotContainsString('rerun', strtolower($workflow));
    }

    public function test_console_service_delegates_point_and_eligible_shift_ownership(): void
    {
        $service = file_get_contents(base_path('app/Console/Services/PrestigeUploadDiagnosticProvisioningService.php'));
        $this->assertIsString($service);
        $this->assertStringContainsString('ensurePrestigeUploadDiagnosticBookingFunding', $service);
        $this->assertStringContainsString('EligibleShiftIntakeService', $service);
        $this->assertStringNotContainsString("DB::table('point_ledger_entries')->insert", $service);
        $this->assertStringNotContainsString("'production_revision_verified'", $service);
    }
}
