<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionS3WritePathDiagnosticWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3-write-path.yml'));
        $this->assertIsString($workflow);

        return $workflow;
    }

    public function test_workflow_is_manual_guarded_and_uses_configured_storage(): void
    {
        $workflow = $this->workflow();
        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString("permissions:\n      contents: read", $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'set -x', 'dispatch'] as $forbidden) {
            if ($forbidden === 'dispatch') {
                continue;
            }
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $revision = 'b6232a158b3f6884fd9823bc875abc432676b781';
        $this->assertStringContainsString($revision, $workflow);
        foreach (['mhcs_core_app', '/var/www/html/VERSION-CURRENT', 'SERVICE_REVISION', 'CONTAINER_REVISION', 'EXPECTED_REVISION'] as $proof) {
            $this->assertStringContainsString($proof, $workflow);
        }
        $guard = strpos($workflow, 'if [ "$revision_match" != "true" ]; then');
        $diagnostic = strpos($workflow, 'docker exec -i "$APP_CONTAINER" php');
        $this->assertNotFalse($guard);
        $this->assertNotFalse($diagnostic);
        $this->assertLessThan($diagnostic, $guard);

        foreach (["config('mhcs.private_object_disk')", 'Storage::disk', 'AwsS3V3Adapter', 'getClient()', 'HeadBucket', 'putObjectAsync', 'PrivateObjectStore::putStream()', 'PrivateObjectStore::putStreamAsync()'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
    }

    public function test_all_write_probes_and_short_circuit_boundaries_are_present(): void
    {
        $workflow = $this->workflow();
        foreach ([
            'write_probe_head_bucket=', 'sync_private_store_small=', 'async_sdk_small_no_acl=',
            'async_sdk_small_private_acl=', 'async_sdk_real_radiograph_key_small=',
            'async_private_store_real_key_small=', 'async_private_store_pair_small=',
            'async_private_store_pair_realistic=', 'write_path_boundary=',
            'write_path_root_cause_confirmed=false',
        ] as $output) {
            $this->assertStringContainsString($output, $workflow);
        }
        foreach (['probe_1_cleanup=', 'probe_2_cleanup=', 'probe_3_cleanup=', 'probe_4_cleanup=', 'probe_5_cleanup=', 'probe_6_cleanup=', 'probe_7_cleanup=', 'overall_cleanup=FAIL'] as $output) {
            $this->assertStringContainsString($output, $workflow);
        }
        foreach (['89660664', '17713052', 'fopen(', 'hash_init', 'hash_update', 'rewind(', 'Utils::settle', 'ContentLength', 'representative_radiograph_within_configured_limit'] as $marker) {
            $this->assertStringContainsString($marker, $workflow);
        }
        $large = strpos($workflow, 'async_private_store_pair_realistic=');
        $smallPair = strpos($workflow, 'async_private_store_pair_small=');
        $this->assertNotFalse($large);
        $this->assertNotFalse($smallPair);
        $this->assertGreaterThan($smallPair, $large);
        $this->assertStringContainsString('$allPriorPassed', $workflow);
        $this->assertStringContainsString('SKIPPED', $workflow);
    }

    public function test_acl_key_concurrency_cleanup_and_error_safety_are_explicit(): void
    {
        $workflow = $this->workflow();
        $noAcl = strpos($workflow, 'async_sdk_small_no_acl');
        $acl = strpos($workflow, "'ACL' => 'private'");
        $this->assertNotFalse($noAcl);
        $this->assertNotFalse($acl);
        $this->assertStringContainsString("'ACL'=>'private'", $workflow);
        $this->assertStringContainsString('objects/$uuid/radiograph', $workflow);
        $this->assertStringContainsString('objects/$uuid/gain', $workflow);
        $this->assertStringContainsString('Utils::settle([$radiographPromise, $gainPromise])->wait()', $workflow);
        $this->assertStringContainsString('cleanup_known_keys_only=true', $workflow);
        $this->assertStringContainsString('cleanup_on_failure_present=true', $workflow);
        $this->assertStringContainsString('cleanup_verification_present=true', $workflow);
        $this->assertStringContainsString('overall_cleanup=FAIL', $workflow);
        foreach (['GetObject', 'ListObjects', 'getObject(', 'listObjects(', 'DB::', 'Google Drive', 'drive.google.com', 'goat', 'radiograph.npz', 'echo $endpoint', 'echo $bucket', 'getMessage()'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach (['none', 'authorization', 'not_found', 'transport', 'unsupported', 'unknown', 'sanitizeThrowable', 'http_status='] as $family) {
            $this->assertStringContainsString($family, $workflow);
        }
    }
}
