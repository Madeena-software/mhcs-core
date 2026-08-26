<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionRealNpzEndToEndValidationWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $workflow = file_get_contents(base_path('.github/workflows/validate-production-real-npz-end-to-end.yml'));
        $this->assertIsString($workflow);

        return $workflow;
    }

    public function test_workflow_is_manual_only_revision_guarded_and_least_privilege(): void
    {
        $workflow = $this->workflow();
        $this->assertStringContainsString("on:\n  workflow_dispatch:\n", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('expected_application_revision:', $workflow);
        $this->assertStringContainsString('authorization_marker:', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'workflow_run:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach (['app_container_resolved=', 'version_current_match=', 'service_revision_match=', 'container_revision_match=', 'revision_match='] as $field) {
            $this->assertStringContainsString($field, $workflow);
        }
        $guard = strpos($workflow, 'if [ "$revision_match" != true ]; then');
        $fixture = strpos($workflow, 'drive.usercontent.google.com');
        $this->assertNotFalse($guard);
        $this->assertNotFalse($fixture);
        $this->assertLessThan($fixture, $guard);
    }

    public function test_pinned_fixtures_are_integrity_gated_before_submission(): void
    {
        $workflow = $this->workflow();
        foreach ([
            '1Ft3OALtx_d3ua-z0DSS34jJmywaXjLu2', 'TRX_1787726886830.npz', '73089445',
            '605540c9102867eda3a5b54f4f88566d067ba8705fcc20bf870e4a60f80262b9',
            '1kI99se2CjzCgo4qInMEGUuJ-ZJZE3iQY', 'TRX_1787726609597.npz', '17190412',
            '38918e436e5329e28b08c844e8df3766a1ab83a1fc3135c83df56370c480b2a9',
            'stat -c', 'sha256sum', 'radiograph_fixture_integrity=', 'gain_fixture_integrity=',
            'real_npz_submission_started=true',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $integrity = strpos($workflow, 'if [ "$radiograph_fixture_integrity" != PASS ]');
        $submit = strpos($workflow, 'radiograph_npz=@');
        $this->assertNotFalse($integrity);
        $this->assertNotFalse($submit);
        $this->assertLessThan($submit, $integrity);
    }

    public function test_workflow_crosses_normal_authenticated_capture_boundary_and_reports_sanitized_states(): void
    {
        $workflow = $this->workflow();
        foreach ([
            'mhcs:provision-nonclinical-validation-context', 'real-npz-e2e-v1',
            '/operator/login', '/operator/xray-readiness-worklist/', '/claim', '/call',
            'ImageGatewayController::captureStore()', 'submission_id=', 'radiograph_npz=@', 'gain_npz=@',
            'capture_sources_complete=', 'processing_handoff_observed=', 'processing_job_state=',
            'mpips_state=', 'terminal_application_state=', 'failure_family=',
            'application_retention=RETAINED', 'workspace_cleanup=',
            'fixture_acquisition_duration_ms=', 'radiograph_submission_duration_ms=',
            'gain_submission_duration_ms=', 'source_completion_duration_ms=',
            'processing_handoff_duration_ms=', 'terminal_processing_duration_ms=',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['DB::', 'Storage::', 'S3Client', 'MpipsClient', 'ProcessCaptureSet', 'deleteObject', 'docker service update', 'docker restart', 'workflow_run:', 'retry', 'rerun'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach (['echo "$OPERATOR_PASSWORD"', 'echo "$AUTHORIZATION_MARKER"', 'echo "$APP_CONTAINER"', 'echo "$SERVICE_IMAGE"', 'echo "$CONTAINER_IMAGE"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }
}
