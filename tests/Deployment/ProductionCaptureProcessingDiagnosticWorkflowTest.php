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
            'deployed_mhcs_revision_match',
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

    public function test_workflow_enforces_slice2_mpips_runtime_and_safety_boundaries(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-capture-processing.yml'));
        $this->assertIsString($workflow);

        // 1. Bounded timestamp window
        $this->assertStringContainsString('WINDOW_START="2026-09-08T00:06:30Z"', $workflow);
        $this->assertStringContainsString('WINDOW_END="2026-09-08T00:11:30Z"', $workflow);
        $this->assertStringContainsString('bounded_incident_window_start', $workflow);
        $this->assertStringContainsString('bounded_incident_window_end', $workflow);

        // 2. No raw docker logs emitted directly (always redirected to restricted temp file)
        $this->assertStringContainsString('> "$MPIPS_LOG_FILE" 2>&1', $workflow);
        $this->assertStringNotContainsString('echo "$(docker logs', $workflow);

        // 3. No cat or tail of raw logs
        $this->assertStringNotContainsString('cat "$MPIPS_LOG_FILE"', $workflow);
        $this->assertStringNotContainsString('tail "$MPIPS_LOG_FILE"', $workflow);
        $this->assertStringNotContainsString('cat $MPIPS_LOG_FILE', $workflow);
        $this->assertStringNotContainsString('tail $MPIPS_LOG_FILE', $workflow);

        // 4. No artifact upload
        $this->assertStringNotContainsString('upload-artifact', $workflow);
        $this->assertStringNotContainsString('actions/upload-artifact', $workflow);

        // 5. Allowlisted error-code parser only
        foreach ([
            'CONVERSION_WORKER_FAILURE',
            'TIFF_VALIDATION_ERROR',
            'NPZ_VALIDATION_ERROR',
            'MANIFEST_OR_DATA_ERROR',
            'CONVERSION_TIMEOUT',
            'CALIBRATION_ARTIFACT_MISSING',
            'UPLOAD_SIZE_EXCEEDED',
            'CONCURRENCY_LIMIT_EXCEEDED',
            'IDEMPOTENCY_IN_PROGRESS',
            'IDEMPOTENCY_CONFLICT',
        ] as $code) {
            $this->assertStringContainsString($code, $workflow);
        }

        // 6. No patient object reads
        $this->assertStringNotContainsString('getObject', $workflow);
        $this->assertStringNotContainsString('s3://', $workflow);
        $this->assertStringNotContainsString('aws s3', $workflow);

        // 7. No POST to MPIPS conversion endpoint
        $this->assertStringNotContainsString('curl -X POST', $workflow);
        $this->assertStringNotContainsString('curl -F', $workflow);
        $this->assertStringNotContainsString('convertStreams', $workflow);

        // 8. No retry / requeue
        $this->assertStringNotContainsString('ProcessCaptureSet::dispatch', $workflow);
        $this->assertStringNotContainsString('retryCaptureSet', $workflow);
        $this->assertStringContainsString('retry_execution_status=FORBIDDEN_BY_INCIDENT_POLICY', $workflow);
        $this->assertStringContainsString('retry_executed=false', $workflow);

        // 9. No docker restart or service updates
        $this->assertStringNotContainsString('docker restart', $workflow);
        $this->assertStringNotContainsString('docker service update', $workflow);
        $this->assertStringNotContainsString('systemctl restart', $workflow);

        // 10. No DB writes
        $this->assertStringNotContainsString('->update(', $workflow);
        $this->assertStringNotContainsString('->insert(', $workflow);
        $this->assertStringNotContainsString('->delete(', $workflow);
        $this->assertStringNotContainsString('DB::statement', $workflow);

        // 11. No Redis writes
        $this->assertStringNotContainsString('Redis::set', $workflow);
        $this->assertStringNotContainsString('Redis::del', $workflow);
        $this->assertStringNotContainsString('redis-cli', $workflow);

        // 12. Revision pinning remains present
        $this->assertStringContainsString('f2bf7b9980f9af7649e1a6c45c46aaee7a55a36a', $workflow);
        $this->assertStringContainsString('d0f99ecd604a2b0aa0f31a62396790b04028c9eb', $workflow);
        $this->assertStringContainsString('MPIPS_RUNTIME_MATCHES_DEPLOYED_BASELINE', $workflow);
        $this->assertStringContainsString('MPIPS_RUNTIME_DIFFERS_FROM_DEPLOYED_BASELINE', $workflow);
        $this->assertStringContainsString('MPIPS_RUNTIME_REVISION_UNPROVEN', $workflow);

        // 13. Host launcher evidence fields
        foreach ([
            'launcher_runtime_present',
            'launcher_socket_present',
            'launcher_service_present',
            'launcher_service_active',
            'launcher_reachable',
            'mpips_worker_image_present',
            'mpips_worker_image_revision',
        ] as $launcherField) {
            $this->assertStringContainsString($launcherField, $workflow);
        }

        // 14. Calibration evidence fields
        foreach ([
            'calibration_root_present',
            'trx_calibration_present',
            'trx_calibration_metadata_present',
            'trx_calibration_remap_present',
            'trx_calibration_validated',
            'trx_calibration_detector_mode_match',
        ] as $calField) {
            $this->assertStringContainsString($calField, $workflow);
        }

        // 15. Classification & recommendation fields
        foreach ([
            'mpips_dicom_http_500_count',
            'safe_error_code',
            'safe_error_code_count',
            'correlation_confidence',
            'primary_root_cause_classification',
            'primary_root_cause_code',
            'retry_recommendation',
            'raw_logs_exposed=false',
            'artifact_contents_exposed=false',
        ] as $diagField) {
            $this->assertStringContainsString($diagField, $workflow);
        }
    }
}
