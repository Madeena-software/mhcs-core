<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionPrivateObjectAsyncPromiseValidationWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $workflow = file_get_contents(base_path('.github/workflows/validate-production-private-object-async-promise-fix.yml'));
        $this->assertIsString($workflow);

        return $workflow;
    }

    public function test_workflow_is_manual_only_and_revision_guarded(): void
    {
        $workflow = $this->workflow();

        $this->assertStringContainsString("on:\n  workflow_dispatch:\n", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString("permissions:\n      contents: read", $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $revision = '2d3de5920493001039b7d6a1c5641a835327ba83';
        $this->assertStringContainsString($revision, $workflow);
        foreach (['app_container_resolved=', 'version_current_match=', 'service_revision_match=', 'container_revision_match=', 'revision_match='] as $field) {
            $this->assertStringContainsString($field, $workflow);
        }

        $guard = strpos($workflow, 'if [ "$revision_match" != "true" ]; then');
        $probe = strpos($workflow, 'docker exec -i "$APP_CONTAINER" php');
        $this->assertNotFalse($guard);
        $this->assertNotFalse($probe);
        $this->assertLessThan($probe, $guard);
    }

    public function test_revision_parser_is_digest_aware_and_safe_under_strict_bash(): void
    {
        $workflow = $this->workflow();
        $start = strpos($workflow, 'image_revision() {');
        $end = strpos($workflow, 'SERVICE_REVISION=', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $helper = substr($workflow, $start, $end - $start);

        $this->assertStringContainsString('local image="${1-}"', $helper);
        $this->assertStringContainsString('local without_digest=""', $helper);
        $this->assertStringContainsString('local revision=""', $helper);
        $this->assertLessThan(strpos($helper, '"${without_digest##*:}"'), strpos($helper, '"${image%%@*}"'));
        $this->assertStringContainsString('if [[ "$revision" =~ ^[0-9a-f]{40}$ ]]; then', $helper);

        $script = "set -euo pipefail\n".$helper.<<<'BASH'
SERVICE_IMAGE='registry/repo:2d3de5920493001039b7d6a1c5641a835327ba83'
DIGESTED_IMAGE='registry/repo:2d3de5920493001039b7d6a1c5641a835327ba83@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
printf 'normal=%s\ndigested=%s\nempty=%s\nlatest=%s\ndigest_only=%s\n' \
  "$(image_revision "$SERVICE_IMAGE")" \
  "$(image_revision "$DIGESTED_IMAGE")" \
  "$(image_revision '')" \
  "$(image_revision 'registry/repo:latest')" \
  "$(image_revision 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')"
BASH;

        $process = proc_open(['/usr/bin/env', 'bash', '-c', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, $error);
        $this->assertSame("normal=2d3de5920493001039b7d6a1c5641a835327ba83\ndigested=2d3de5920493001039b7d6a1c5641a835327ba83\nempty=\nlatest=\ndigest_only=", trim($output));
        foreach (['echo "$SERVICE_IMAGE"', 'echo "$CONTAINER_IMAGE"', 'echo "$APP_CONTAINER"', 'echo "$TASK_ID"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_workflow_inspects_single_and_concurrent_private_object_promises(): void
    {
        $workflow = $this->workflow();

        foreach ([
            'use App\\Shared\\Storage\\PrivateObject;',
            'use App\\Shared\\Storage\\PrivateObjectStore;',
            'app(PrivateObjectStore::class)',
            'putStreamAsync(',
            'single_async_state=',
            'single_async_value_private_object=',
            'instanceof PrivateObject',
            '$radiographPromise =',
            '$gainPromise =',
            'Utils::settle([$radiographPromise, $gainPromise])->wait()',
            'radiograph_async_state=',
            'gain_async_state=',
            'radiograph_value_private_object=',
            'gain_value_private_object=',
            'headObject(',
            'single_object_head=',
            'single_metadata_head=',
            'radiograph_object_head=',
            'gain_object_head=',
            'radiograph_metadata_head=',
            'gain_metadata_head=',
            'single_size_match=',
            'radiograph_size_match=',
            'gain_size_match=',
            'promise_fix_runtime_validation=',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        $settle = strpos($workflow, 'Utils::settle([$radiographPromise, $gainPromise])->wait()');
        $radiograph = strpos($workflow, '$radiographPromise =', $settle - 1200);
        $gain = strpos($workflow, '$gainPromise =', $radiograph);
        $this->assertNotFalse($radiograph);
        $this->assertNotFalse($gain);
        $this->assertLessThan($settle, $radiograph);
        $this->assertLessThan($settle, $gain);
        $this->assertStringContainsString('$rState === \'fulfilled\'', $workflow);
        $this->assertStringContainsString('$gState === \'fulfilled\'', $workflow);
        $this->assertStringContainsString('$rValue instanceof PrivateObject', $workflow);
        $this->assertStringContainsString('$gValue instanceof PrivateObject', $workflow);
        $this->assertStringContainsString('$singleState === \'fulfilled\'', $workflow);
        $this->assertStringContainsString('$singleValue instanceof PrivateObject', $workflow);
    }

    public function test_cleanup_and_prohibited_operations_are_explicit(): void
    {
        $workflow = $this->workflow();

        foreach (['try {', 'finally {', 'single_cleanup=', 'pair_cleanup=', 'overall_cleanup=', 'cleanup_incident=true', 'deleteObject(', 'headObject(', 'if (! $overall) exit(1);'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['GetObject', 'ListObjects', 'ListObjectsV2', 'DB::', 'Google Drive', 'MPIPS', 'ProcessCaptureSet', 'docker service update', 'docker restart', 'docker stack deploy', 'echo "$bucket"', 'echo "$endpoint"', 'echo "$key"', 'getMessage()', 'getRequestId()'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        foreach (['validation_revision=', 'single_error_family=', 'radiograph_error_family=', 'gain_error_family=', 'none', 'authorization', 'not_found', 'transport', 'unsupported', 'unknown'] as $safe) {
            $this->assertStringContainsString($safe, $workflow);
        }
        $pass = strpos($workflow, "echo 'promise_fix_runtime_validation='");
        $cleanup = strpos($workflow, "echo 'overall_cleanup='");
        $this->assertNotFalse($pass);
        $this->assertNotFalse($cleanup);
        $this->assertLessThan($pass, $cleanup);
    }

    public function test_p1_failure_is_a_fail_closed_gate_before_any_pair_probe(): void
    {
        $workflow = $this->workflow();
        $singleGate = strpos($workflow, '$singlePassed =');
        $pairStart = strpos($workflow, '$radiographPromise =');
        $pairOutput = strpos($workflow, 'radiograph_async_state=NOT_EXECUTED', $singleGate);

        $this->assertNotFalse($singleGate);
        $this->assertNotFalse($pairStart);
        $this->assertNotFalse($pairOutput);
        $this->assertLessThan($pairStart, $singleGate);
        $this->assertLessThan($pairOutput, $singleGate);
        $this->assertStringContainsString('$singlePassed = $singleState === \'fulfilled\'', $workflow);
        $this->assertStringContainsString('if (! $singlePassed)', $workflow);
        $this->assertStringContainsString('$singleValue instanceof PrivateObject', $workflow);
        $this->assertStringContainsString('$singleCleanup === \'PASS\'', $workflow);

        $gate = substr($workflow, $singleGate, $pairStart - $singleGate);
        foreach ([
            'radiograph_async_state=NOT_EXECUTED',
            'gain_async_state=NOT_EXECUTED',
            'radiograph_value_private_object=NOT_EXECUTED',
            'gain_value_private_object=NOT_EXECUTED',
            'radiograph_object_head=NOT_EXECUTED',
            'gain_object_head=NOT_EXECUTED',
            'radiograph_metadata_head=NOT_EXECUTED',
            'gain_metadata_head=NOT_EXECUTED',
            'radiograph_size_match=NOT_EXECUTED',
            'gain_size_match=NOT_EXECUTED',
            'pair_result=NOT_EXECUTED',
            'pair_cleanup=NOT_EXECUTED',
            'promise_fix_runtime_validation=FAIL',
            'exit(1)',
        ] as $required) {
            $this->assertStringContainsString($required, $gate);
        }
    }

    public function test_cleanup_stabilizes_exact_keys_and_does_not_retry_probes(): void
    {
        $workflow = $this->workflow();
        $cleanupStart = strpos($workflow, 'function cleanup(');
        $cleanupEnd = strpos($workflow, 'function startAsync(', $cleanupStart);
        $cleanup = substr($workflow, $cleanupStart, $cleanupEnd - $cleanupStart);

        $this->assertStringContainsString('$knownKeys = array_values(array_unique($keys));', $cleanup);
        $this->assertStringContainsString('// Initial delete phase:', $cleanup);
        $this->assertStringContainsString('$deleteExact($knownKeys);', $cleanup);
        $this->assertStringContainsString('$reappeared = $inspect($knownKeys);', $cleanup);
        $this->assertStringContainsString('foreach ($targets as $key)', $cleanup);
        $this->assertStringContainsString('$cleanupFailed = true;', $cleanup);
        $this->assertStringContainsString('if ($reappeared !== [])', $cleanup);
        $this->assertStringContainsString('$deleteExact($reappeared);', $cleanup);
        $this->assertStringContainsString('for ($round = 0; $round < 4; $round++)', $cleanup);
        $this->assertStringContainsString('$stableChecks', $cleanup);
        $this->assertStringContainsString('usleep(250000)', $cleanup);
        $this->assertStringContainsString('deleteObject(', $cleanup);
        $this->assertStringContainsString('$stableChecks >= 2', $cleanup);
        $this->assertStringContainsString('headObject(', $cleanup);
        $this->assertStringContainsString('return ! $cleanupFailed;', $cleanup);
        $this->assertStringContainsString('return false;', $cleanup);

        $initialDelete = strpos($cleanup, '$deleteExact($knownKeys);');
        $stabilization = strpos($cleanup, '$stableChecks = 0;');
        $quiescence = strpos($cleanup, 'usleep(250000);', $stabilization);
        $stabilityHead = strpos($cleanup, '$reappeared = $inspect($knownKeys);', $quiescence);
        $this->assertLessThan($stabilization, $initialDelete);
        $this->assertLessThan($stabilityHead, $quiescence);
        $this->assertStringNotContainsString('$deleteExact($knownKeys);', substr($cleanup, $stabilization));

        $this->assertSame(3, substr_count($workflow, 'putStreamAsync('));
        $this->assertStringNotContainsString('putStreamAsync(', $cleanup);
        foreach (['ListObjects', 'ListObjectsV2', 'GetObject', 'deleteMatching', 'deletePrefix'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $cleanup);
        }
    }
}
