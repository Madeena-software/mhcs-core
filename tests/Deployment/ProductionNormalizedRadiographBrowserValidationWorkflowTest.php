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
        foreach (['expected_application_revision:', 'authorization_marker:', 'GOVERNING_TASK_REVISION:', 'GITHUB_SHA', 'EXPECTED_APPLICATION_REVISION', 'AUTHORIZE_ONE_PRODUCTION_NORMALIZED_RADIOGRAPH_RUN'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['revision_mismatch', 'authorization', 'fixture', 'production-normalized-radiograph-browser.test.mjs'] as $needle) {
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
    }

    public function test_workflow_is_single_upload_and_sanitized_with_cleanup(): void
    {
        $workflow = $this->workflow();
        $this->assertSame(1, substr_count($workflow, 'production-normalized-radiograph-browser.test.mjs'));
        foreach (['real_normalized_radiograph_submission_started=true', 'at_most_one_upload=true', 'trap ', 'rm -rf "$workspace"', 'sanitized_evidence=', 'harness_revision=', 'deployed_application_revision='] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['ssh ', 'docker exec', 'Storage::', 'S3Client', 'MpipsClient', 'DB::insert', 'DB::update', 'DB::delete', '->insert(', '->update(', '->delete(', 'aws s3', 'queue:work', 'curl -X POST http', 'echo "$OPERATOR_PASSWORD"', 'echo "$AUTHORIZATION_MARKER"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }
}
