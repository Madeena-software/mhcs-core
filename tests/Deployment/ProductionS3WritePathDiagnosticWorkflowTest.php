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

    public function test_fifth_remediation_separates_cleanup_and_execution_failures(): void
    {
        $workflow = $this->workflow();
        $p6CleanupFailure = strpos($workflow, 'if (! $pairClean)');
        $p7Block = strpos($workflow, "\$limit = config('mhcs.upload.max_file_bytes')");

        $this->assertNotFalse($p6CleanupFailure);
        $this->assertNotFalse($p7Block);
        $this->assertLessThan($p7Block, $p6CleanupFailure);
        $this->assertStringContainsString("if (! \$pairClean) { if (! \$pairOk) { \$diagnosticBoundary = 'concurrent_async_pair'", $workflow);
        $this->assertStringContainsString("if (! \$pairClean) { if (! \$pairOk) { \$diagnosticBoundary = 'concurrent_async_pair'; \$diagnosticFailed = true; } \$emitFinalReport(); exit(1); }", $workflow);
        $this->assertStringContainsString("if (! \$pairOk) { \$diagnosticBoundary = 'concurrent_async_pair'", $workflow);
        $this->assertStringContainsString("if (! \$pairOk) { \$diagnosticBoundary = \$probe7Started ? 'realistic_size_stream' : 'unknown'; \$diagnosticFailed = true; }", $workflow);
        $this->assertStringContainsString("if (! \$localCleanup) { \$executionFailed = true; if (! \$diagnosticFailed) \$diagnosticBoundary = 'unknown'; }", $workflow);
        $this->assertStringNotContainsString('if (! $pairOk || ! $clean || ! $localCleanup)', $workflow);
        $this->assertStringContainsString('if ($probe7Started && ! $clean) $cleanupFailed = true', $workflow);
        $this->assertStringContainsString("overall_cleanup=\".(\$cleanupFailed ? 'FAIL' : 'PASS')", $workflow);
        $this->assertStringContainsString('if ($diagnosticFailed || $cleanupFailed || $executionFailed)', $workflow);
        $this->assertStringContainsString("\$probe7Cleanup = ! \$probe7Started ? 'SKIPPED'", $workflow);
        $this->assertStringContainsString('write_path_root_cause_confirmed=false', $workflow);
    }

    public function test_synthetic_generators_reject_partial_writes_and_clean_up_failures(): void
    {
        $workflow = $this->workflow();
        $streamStart = strpos($workflow, 'function streamOf(');
        $fileStart = strpos($workflow, 'function syntheticFile(');
        $headStart = strpos($workflow, 'function head(');
        $stream = substr($workflow, $streamStart, $fileStart - $streamStart);
        $file = substr($workflow, $fileStart, $headStart - $fileStart);

        foreach ([$stream, $file] as $helper) {
            $write = strpos($helper, '$actual = fwrite(');
            $validate = strpos($helper, '$actual === false || $actual !== $expected');
            $hash = strpos($helper, 'hash_update($hash, $part)');
            $increment = strpos($helper, '$written += $actual');
            $this->assertNotFalse($write);
            $this->assertNotFalse($validate);
            $this->assertNotFalse($hash);
            $this->assertNotFalse($increment);
            $this->assertLessThan($validate, $write);
            $this->assertLessThan($hash, $validate);
            $this->assertLessThan($increment, $hash);
            $this->assertStringNotContainsString('$written += strlen($part)', $helper);
            $this->assertStringContainsString('$written !== $bytes', $helper);
        }

        $this->assertStringContainsString('catch (Throwable $e) { fclose($stream); throw $e; }', $stream);
        $this->assertStringContainsString('if (is_resource($stream)) fclose($stream); if (is_file($path)) @unlink($path); throw $e;', $file);
        $this->assertStringContainsString('if ($stream === false) throw new RuntimeException(\'synthetic file open failed\')', $file);
        $this->assertStringContainsString('PK\\x03\\x04', $file);
        $this->assertStringContainsString("str_repeat('N', 1048572)", $file);
        $this->assertStringContainsString('if (rewind($stream) === false)', $stream);
        $this->assertStringContainsString('if (rewind($stream) === false)', $file);
        $this->assertStringContainsString('89660664', $workflow);
        $this->assertStringContainsString('17713052', $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringNotContainsString('GetObject', $workflow);
        $this->assertStringNotContainsString('ListObjects', $workflow);
        $this->assertStringNotContainsString('DB::', $workflow);
        $this->assertStringContainsString('write_path_root_cause_confirmed=false', $workflow);
    }

    public function test_revision_guard_parser_is_safe_and_digest_aware(): void
    {
        $workflow = $this->workflow();
        $helperStart = strpos($workflow, 'image_revision() {');
        $helperEnd = strpos($workflow, 'SERVICE_REVISION=', $helperStart);
        $helper = substr($workflow, $helperStart, $helperEnd - $helperStart);

        $this->assertStringNotContainsString('local image="$1" revision=', $helper);
        $this->assertStringContainsString('local image="${1-}"', $helper);
        $this->assertStringContainsString('local without_digest=""', $helper);
        $this->assertStringContainsString('local revision=""', $helper);
        $this->assertLessThan(strpos($helper, '"${without_digest##*:}"'), strpos($helper, '"${image%%@*}"'));
        $this->assertStringContainsString('if [[ "$revision" =~ ^[0-9a-f]{40}$ ]]; then', $helper);
        $this->assertStringContainsString('return 0', $helper);

        $script = "set -euo pipefail\n".$helper;
        $script .= <<<'BASH'
SERVICE_IMAGE='registry/repo:b6232a158b3f6884fd9823bc875abc432676b781'
CONTAINER_IMAGE='registry/repo:b6232a158b3f6884fd9823bc875abc432676b781@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
SERVICE_REVISION="$(image_revision "$SERVICE_IMAGE")"
CONTAINER_REVISION="$(image_revision "$CONTAINER_IMAGE")"
EMPTY_REVISION="$(image_revision '')"
INVALID_REVISION="$(image_revision 'registry/repo:latest')"
DIGEST_ONLY_REVISION="$(image_revision 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')"
printf 'valid=%s\ndigested=%s\nempty=%s\ninvalid=%s\ndigest_only=%s\n' "$SERVICE_REVISION" "$CONTAINER_REVISION" "$EMPTY_REVISION" "$INVALID_REVISION" "$DIGEST_ONLY_REVISION"
BASH;
        $pipes = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(['/usr/bin/env', 'bash', '-c', $script], $pipes, $handles);
        $this->assertIsResource($process);
        $output = stream_get_contents($handles[1]);
        $error = stream_get_contents($handles[2]);
        fclose($handles[1]);
        fclose($handles[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $error);
        $this->assertSame("valid=b6232a158b3f6884fd9823bc875abc432676b781\ndigested=b6232a158b3f6884fd9823bc875abc432676b781\nempty=\ninvalid=\ndigest_only=", trim($output));
        foreach (['app_container_resolved=', 'version_current_match=', 'service_revision_match=', 'container_revision_match=', 'revision_match='] as $safeOutput) {
            $this->assertStringContainsString($safeOutput, $workflow);
        }
        foreach (['echo "$SERVICE_IMAGE"', 'echo "$CONTAINER_IMAGE"', 'echo "$VERSION_CURRENT"', 'echo "$SERVICE_REVISION"', 'echo "$CONTAINER_REVISION"', 'echo "$APP_CONTAINER"', 'echo "$TASK_ID"'] as $forbiddenOutput) {
            $this->assertStringNotContainsString($forbiddenOutput, $workflow);
        }
    }
}
