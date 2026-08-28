<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionNormalizedRadiographBrowserValidationWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $workflow = file_get_contents(base_path('.github/workflows/validate-production-normalized-radiograph-browser.yml'));
        $this->assertIsString($workflow);

        return $workflow;
    }

    private function browserHarness(): string
    {
        $harness = file_get_contents(base_path('tests/JavaScript/production-normalized-radiograph-browser.test.mjs'));
        $this->assertIsString($harness);

        return $harness;
    }

    public function test_workflow_is_manual_only_least_privilege_and_serialized(): void
    {
        $workflow = $this->workflow();
        $this->assertStringContainsString("on:\n  workflow_dispatch:\n", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('concurrency:', $workflow);
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'workflow_run:', 'deploy:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_authorization_and_revision_guards_precede_fixture_or_upload(): void
    {
        $workflow = $this->workflow();
        foreach (['expected_application_revision:', 'authorization_marker:', 'operator_site_id:', 'GOVERNING_TASK_REVISION:', 'GITHUB_SHA', 'EXPECTED_APPLICATION_REVISION', 'AUTHORIZE_ONE_PRODUCTION_NORMALIZED_RADIOGRAPH_RUN', 'OPERATOR_EMAIL', 'OPERATOR_PASSWORD', 'APP_CONTAINER', 'application_container_resolved', 'docker service ps', 'docker exec', 'SERVICE_REVISION', 'CONTAINER_REVISION', 'VERSION_CURRENT', 'RAW_VERSION_CURRENT', 'CONTAINER_HEALTH', 'healthy', '/up'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['revision_mismatch', 'authorization', 'fixture_acquisition', 'radiograph_fixture_integrity', 'gain_fixture_integrity', 'production-normalized-radiograph-browser.test.mjs'] as $needle) {
            $this->assertStringContainsString($needle, $workflow);
        }
        $guard = strpos($workflow, 'if [ "$revision_match" != true ]');
        $fixture = strpos($workflow, 'fixture_acquisition');
        $upload = strpos($workflow, 'production-normalized-radiograph-browser.test.mjs');
        $this->assertNotFalse($guard);
        $this->assertNotFalse($fixture);
        $this->assertNotFalse($upload);
        $this->assertLessThan($fixture, $guard);
        $this->assertLessThan($upload, $fixture);
        $this->assertStringNotContainsString('radiograph_fixture_url:', $workflow);
        $this->assertStringNotContainsString('gain_fixture_url:', $workflow);
        $this->assertStringNotContainsString('/version', $workflow);
        $this->assertStringContainsString('[ -n "$OPERATOR_EMAIL" ]', $workflow);
        $this->assertStringContainsString('[ -n "$OPERATOR_PASSWORD" ]', $workflow);
    }

    public function test_workflow_is_single_upload_and_sanitized_with_cleanup(): void
    {
        $workflow = $this->workflow();
        $this->assertSame(1, substr_count($workflow, 'production-normalized-radiograph-browser.test.mjs'));
        foreach (['real_normalized_radiograph_submission_started=true', 'at_most_one_upload=true', 'trap ', 'rm -rf "$workspace"', 'sanitized_evidence=', 'harness_revision=', 'deployed_application_revision=', '1Ft3OALtx_d3ua-z0DSS34jJmywaXjLu2', 'TRX_1787726886830.npz', '73089445', '605540c9102867eda3a5b54f4f88566d067ba8705fcc20bf870e4a60f80262b9', '1kI99se2CjzCgo4qInMEGUuJ-ZJZE3iQY', 'TRX_1787726609597.npz', '17190412', '38918e436e5329e28b08c844e8df3766a1ab83a1fc3135c83df56370c480b2a9', 'stat -c', 'sha256sum'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['ssh ', 'Storage::', 'S3Client', 'MpipsClient', 'DB::insert', 'DB::update', 'DB::delete', '->insert(', '->update(', '->delete(', 'aws s3', 'queue:work', 'curl -X POST http', 'echo "$OPERATOR_PASSWORD"', 'echo "$AUTHORIZATION_MARKER"', 'source=', 'transmitted=', 'member_names', 'upload_telemetry[', 'radiograph_fixture_url:', 'gain_fixture_url:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_browser_output_uses_bounded_sanitization(): void
    {
        $harness = $this->browserHarness();
        $this->assertStringContainsString('sanitizeEvidence', $harness);
        foreach (['source_target_present', 'source_target_count_valid', 'transmitted_target_absent', 'radiograph_size_reduced', 'non_target_payloads_preserved', 'gain_identity_preserved', 'original_radiograph_bytes', 'transmitted_radiograph_bytes', 'request_count', 'failure_family', 'navigateToAuthorizedCapture', 'form:has(select[name="site_id"])', 'submitCaptureForm', '#capture-form', 'assertProductionEvidence', 'select[name="site_id"]', 'OPERATOR_SITE_ID', 'input[name="radiograph_npz"]', 'input[name="gain_npz"]'] as $required) {
            $this->assertStringContainsString($required, $harness);
        }
        $this->assertStringContainsString('upload_telemetry: evidence.upload_telemetry.at(-1)', $harness);
        $this->assertStringNotContainsString('original: source', $harness);
        $this->assertStringNotContainsString('console.log(JSON.stringify(observed', $harness);
        $this->assertStringNotContainsString("page.click('button[type=\"submit\"]')", $harness);
    }
}
