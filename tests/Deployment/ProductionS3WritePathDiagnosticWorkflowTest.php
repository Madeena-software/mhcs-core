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
        $this->assertStringContainsString("\$radiographKey = 'objects/'.\$captureUuid.'/radiograph'", $workflow);
        $this->assertStringContainsString("\$gainKey = 'objects/'.\$captureUuid.'/gain'", $workflow);
        $this->assertStringContainsString('Utils::settle([$radiographPromise, $gainPromise])->wait()', $workflow);
        $this->assertStringContainsString('cleanup_known_keys_only=true', $workflow);
        $this->assertStringContainsString('cleanup_on_failure_present=true', $workflow);
        $this->assertStringContainsString('cleanup_verification_present=true', $workflow);
        $this->assertStringContainsString('overall_cleanup=FAIL', $workflow);
        foreach (['GetObject', 'ListObjects', 'getObject(', 'listObjects(', 'DB::', 'Google Drive', 'drive.google.com', 'goat', 'radiograph.npz', 'echo $endpoint', 'echo $bucket', 'getMessage()'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach (['none', 'authorization', 'not_found', 'transport', 'unsupported', 'unknown', 'sanitizeThrowableChain', 'http_status='] as $family) {
            $this->assertStringContainsString($family, $workflow);
        }
    }

    public function test_reviewed_write_path_defects_are_not_regressed(): void
    {
        $workflow = $this->workflow();

        $this->assertStringNotContainsString('->getAdapter()', $workflow);
        $this->assertStringContainsString('$configuredDisk = Storage::disk($privateDiskName)', $workflow);
        $this->assertStringContainsString('$configuredDisk->getConfig()', $workflow);
        $this->assertStringContainsString('use App\\Shared\\Identity\\LocalId;', $workflow);
        $this->assertStringContainsString('use App\\Shared\\Context\\CorrelationId;', $workflow);
        $this->assertStringContainsString('LocalId::fromString(', $workflow);
        $this->assertStringContainsString('CorrelationId::random()', $workflow);
        $this->assertStringContainsString('$context->actorId !== null', $workflow);
        $this->assertStringContainsString('$context->operationId !== null', $workflow);
        $this->assertStringNotContainsString('AuthenticatedContext::anonymous()', $workflow);
        $this->assertStringContainsString("\$purpose = 'production-s3-write-path-diagnostic'", $workflow);
        $this->assertStringContainsString('$context->purpose === $purpose', $workflow);
        $this->assertStringNotContainsString('catch (Throwable) { }', $workflow);
        $this->assertStringContainsString('finally', $workflow);
        $this->assertStringContainsString('sanitizeThrowableChain', $workflow);
        $this->assertStringContainsString('pair_small_radiograph=', $workflow);
        $this->assertStringContainsString('pair_small_gain=', $workflow);
        $this->assertStringContainsString('realistic_radiograph_size_match=', $workflow);
        $this->assertStringContainsString('realistic_gain_size_match=', $workflow);
        $this->assertStringNotContainsString('overall_cleanup=PASS', substr($workflow, strpos($workflow, 'overall_cleanup=FAIL')));
    }

    public function test_remediation_keeps_probe_state_and_finalization_truthful(): void
    {
        $workflow = $this->workflow();

        foreach (['startAsync(static fn', '$radiographPromise', '$gainPromise', 'Utils::settle([$radiographPromise, $gainPromise])->wait()', "['reason']", 'sanitizeThrowableChain'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['$probe1Cleanup', '$probe2Cleanup', '$probe3Cleanup', '$probe4Cleanup', '$probe5Cleanup', '$probe6Cleanup', '$probe7Cleanup', '$diagnosticBoundary', '$diagnosticFailed', '$continueProbes', '$emitFinalReport'] as $state) {
            $this->assertStringContainsString($state, $workflow);
        }
        foreach (['async_sdk', 'private_acl', 'real_npz_key_shape', 'concurrent_async_pair', 'realistic_size_stream', 'SKIPPED_CONFIG_LIMIT'] as $boundary) {
            $this->assertStringContainsString($boundary, $workflow);
        }
        $final = strpos($workflow, '$emitFinalReport');
        $this->assertNotFalse($final);
        $this->assertStringNotContainsString('overall_cleanup=PASS', substr($workflow, $final));
        $this->assertStringContainsString('$main[1] === 65536', $workflow);
        $this->assertStringContainsString('realistic_pair_error_family=none', $workflow);
    }

    public function test_fourth_remediation_guarantees_pair_cleanup_and_shared_realistic_namespace(): void
    {
        $workflow = $this->workflow();
        $pairKeys = strpos($workflow, '$pairKeys =');
        $pairWrite = strpos($workflow, '$store->putStreamAsync($r[0]', $pairKeys);
        $pairFinally = strpos($workflow, 'finally {', $pairWrite);

        $this->assertStringContainsString("\$rFamily = 'none'", $workflow);
        $this->assertStringContainsString("\$gFamily = 'none'", $workflow);
        $this->assertStringContainsString("\$rStatus = 'none'", $workflow);
        $this->assertStringContainsString("\$gStatus = 'none'", $workflow);
        $this->assertNotFalse($pairKeys);
        $this->assertNotFalse($pairWrite);
        $this->assertNotFalse($pairFinally);
        $this->assertLessThan($pairWrite, $pairKeys);
        $this->assertLessThan($pairFinally, $pairWrite);
        $this->assertStringContainsString("\$pairKeys = [\$radiographKey, \$radiographKey.'.meta.json', \$gainKey, \$gainKey.'.meta.json']", $workflow);
        $this->assertStringContainsString("\$probe6Cleanup = \$pairClean ? 'PASS' : 'FAIL'", $workflow);
        $this->assertStringContainsString('if (! $pairClean) $cleanupFailed = true', $workflow);

        $realistic = strpos($workflow, '$realistic =');
        $capture = strpos($workflow, '$captureUuid =', $realistic);
        $radiograph = strpos($workflow, "\$rk = 'objects/'.\$captureUuid.'/radiograph'", $capture);
        $gain = strpos($workflow, "\$gk = 'objects/'.\$captureUuid.'/gain'", $capture);
        $this->assertNotFalse($capture);
        $this->assertNotFalse($radiograph);
        $this->assertNotFalse($gain);
        $this->assertLessThan($radiograph, $capture);
        $this->assertLessThan($gain, $radiograph);
        $this->assertStringNotContainsString("Str::uuid().'/radiograph'", $workflow);
        $this->assertStringNotContainsString("Str::uuid().'/gain'", $workflow);
        $this->assertStringContainsString("\$probe7Cleanup = ! \$probe7Started ? 'SKIPPED'", $workflow);
        $this->assertStringContainsString('if ($probe7Started && ! $clean) $cleanupFailed = true', $workflow);
        $this->assertStringContainsString('$pairOk = $heads && ! $probe7Unexpected', $workflow);
        $this->assertStringContainsString('realistic_local_temp_cleanup=', $workflow);
        $this->assertStringContainsString("\$probe7Started ? 'realistic_size_stream' : 'unknown'", $workflow);
    }
}
