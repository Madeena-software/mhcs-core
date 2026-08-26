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
        foreach (['Storage::', 'S3Client', 'MpipsClient', 'ProcessCaptureSet', 'deleteObject', 'docker service update', 'docker restart', 'workflow_run:', 'retry', 'rerun'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach (['echo "$OPERATOR_PASSWORD"', 'echo "$AUTHORIZATION_MARKER"', 'echo "$APP_CONTAINER"', 'echo "$SERVICE_IMAGE"', 'echo "$CONTAINER_IMAGE"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_progression_is_bound_to_the_marker_owned_booking(): void
    {
        $workflow = $this->workflow();
        foreach ([
            'NonclinicalValidationContext::MARKER_NAMESPACE', 'mhcs.validation', 'real-npz-e2e-v1',
            'member_external_identifiers', 'where(\'status\', \'confirmed\')',
            'operator_arrivals', 'operator_identity_verifications', 'operator_paper_tickets',
            'operator_queue_admissions', 'image_gateway_capture_sets',
            'operator_site_assignments', 'operator_sites', 'RESOLVE_STAGE',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['->insert(', '->update(', '->delete(', 'DB::insert', 'DB::update', 'DB::delete', 'head -n 1'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach ([
            '/operator/site', '/operator/attendance/', '/operator/arrivals/confirm', '/operator/arrivals',
            '/operator/identity-verification/start', '/operator/identity-verification/$identity_case_id/decision',
            '/operator/check-in/$identity_case_id', '/operator/basic-examination-worklist',
            '/operator/xray-readiness-worklist', '/capture',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['vital-signs', 'questionnaire', 'paper-consent'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_every_operational_post_has_csrf_and_capture_is_single_submission(): void
    {
        $workflow = $this->workflow();
        $posts = preg_grep('/-X POST/', explode("\n", $workflow));
        $this->assertNotEmpty($posts);
        foreach ($posts as $post) {
            $this->assertStringContainsString('_token=$csrf_token', $post);
        }
        $this->assertSame(1, substr_count($workflow, '/capture"'));
        $this->assertStringContainsString('max_polls=78', $workflow);
        $this->assertStringContainsString('poll=$((poll + 1))', $workflow);
        $this->assertStringContainsString('sleep 5', $workflow);
        $this->assertStringNotContainsString('resubmit', strtolower($workflow));
        $this->assertStringNotContainsString('not_required', $workflow);
    }

    public function test_terminal_evidence_and_failure_reporting_preserve_observed_state(): void
    {
        $workflow = $this->workflow();
        foreach ([
            'CAPTURE_ID="$capture_id"', 'RESOLVE_STAGE=study', 'image_gateway_studies',
            '/operator/studies/$study_id/dicom', 'application/dicom', 'bs=1 skip=128 count=4',
            'DICM', 'ge 132', 'mpips_state=reached', 'mpips_state=failed',
            'report_failure()', 'radiograph_source_state=NOT_EXECUTED',
            'echo "radiograph_source_state=$radiograph_source_state"',
            'application_retention=RETAINED', 'cleanup_workspace',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $report = strpos($workflow, 'report_failure()');
        $sourceEcho = strpos($workflow, 'echo "radiograph_source_state=$radiograph_source_state"');
        $this->assertNotFalse($report);
        $this->assertNotFalse($sourceEcho);
        $this->assertLessThan($sourceEcho, $report);
    }

    public function test_exact_authorization_and_sanitized_output_contract_are_present(): void
    {
        $workflow = $this->workflow();
        $this->assertStringContainsString('[ "$AUTHORIZATION_MARKER" = AUTHORIZE_ONE_PRODUCTION_REAL_NPZ_RUN ]', $workflow);
        foreach (['validation_context_key=real-npz-e2e-v1', 'environment_guard=PASS', 'authorization_guard=PASS', 'operator_minimum_permissions=PASS', 'operator_site_assignment=PASS', 'operator_shift_assignment=PASS', 'validation_operator_login_ready=true', 'validation_context_provisioning=PASS'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['echo "$booking_id"', 'echo "$arrival_id"', 'echo "$identity_case_id"', 'echo "$ticket_id"', 'echo "$capture_id"', 'echo "$study_id"', 'set -x', 'docker exec "$APP_CONTAINER" php artisan queue:work'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }
}
