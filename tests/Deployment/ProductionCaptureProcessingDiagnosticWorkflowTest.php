<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionCaptureProcessingDiagnosticWorkflowTest extends TestCase
{
    public function test_workflow_is_manual_read_only_and_bounded(): void
    {
        $path = base_path('.github/workflows/diagnose-production-capture-processing.yml');
        $this->assertFileExists($path);

        $workflow = file_get_contents($path);
        $this->assertIsString($workflow);

        $this->assertStringContainsString('workflow_dispatch:', $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('contents: read', $workflow);
        $this->assertStringContainsString('timeout-minutes: 10', $workflow);
        $this->assertStringContainsString('production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);

        // Inputs strictly bounded to expected_application_revision and capture_id
        $this->assertStringContainsString('expected_application_revision:', $workflow);
        $this->assertStringContainsString('capture_id:', $workflow);
        $this->assertSame(1, substr_count($workflow, 'expected_application_revision:'));
        $this->assertSame(1, substr_count($workflow, 'capture_id:'));

        // No forbidden mutation commands
        foreach ([
            'pull_request:',
            'schedule:',
            'cron:',
            'docker service update',
            'docker service scale',
            'docker restart',
            'docker stop',
            'docker kill',
            'docker stack deploy',
            'systemctl restart',
            'systemctl reload',
            'nginx reload',
            'php-fpm reload',
            'artisan migrate',
            'artisan queue:restart',
            'artisan cache:clear',
            'artisan config:clear',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_workflow_covers_all_required_diagnostic_boundaries(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-capture-processing.yml'));
        $this->assertIsString($workflow);

        foreach ([
            'd0f99ecd604a2b0aa0f31a62396790b04028c9eb',
            'mhcs_core_app',
            'mhcs_core_image-worker',
            'mhcs-core-application-network',
            'mhcs-mpips-integration-v1',
            'mpips-api',
            'app_service_revision_match',
            'running_container_revision_match',
            'version_current_file_match',
            'revision_match',
            'capture_exists',
            'processing_status',
            'radiograph_status',
            'gain_status',
            'mpips_status',
            'dicom_status',
            'attempts',
            'last_error_code',
            'last_response_status',
            'accepted_at_present',
            'failed_at_present',
            'completed_at_present',
            'processing_claim_present',
            'processing_lease_state',
            'study_count',
            'object_type',
            'byte_count',
            'row_count',
            'jobs_queue_image_gateway_count',
            'jobs_capture_attributed_count',
            'jobs_capture_reserved_count',
            'failed_jobs_capture_attributed_count',
            'failed_jobs_total_count',
            'image_worker_service_revision',
            'image_worker_replicas',
            'image_worker_container_health',
            'image_worker_consumes_image_gateway_queue',
            'mpips_container_running',
            'mpips_container_health',
            'mpips_integration_network_present',
            'image_worker_attached_to_mpips_network',
            'retry_technically_eligible',
            'failure_classification',
            'production_mutations_count=0',
            'phi_or_secrets_exposed=false',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
    }
}
