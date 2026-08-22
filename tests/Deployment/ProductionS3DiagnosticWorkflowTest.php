<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionS3DiagnosticWorkflowTest extends TestCase
{
    public function test_production_s3_diagnostic_is_bounded_and_sanitized(): void
    {
        $workflowPath = base_path('.github/workflows/diagnose-production-s3.yml');
        $this->assertFileExists($workflowPath);

        $workflow = file_get_contents($workflowPath);
        $this->assertIsString($workflow);
        $objectStore = file_get_contents(base_path('app/Shared/Storage/PlainLocalObjectStore.php'));
        $this->assertIsString($objectStore);
        $productionVerifier = file_get_contents(base_path('.github/workflows/verify-production.yml'));
        $this->assertIsString($productionVerifier);

        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:'] as $trigger) {
            $this->assertStringNotContainsString($trigger, $workflow);
        }

        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        $this->assertStringNotContainsString('set -x', $workflow);

        $revision = 'b6232a158b3f6884fd9823bc875abc432676b781';
        $this->assertStringContainsString($revision, $workflow);
        $this->assertStringContainsString('revision_match=false', $workflow);
        $this->assertStringContainsString('if [ "$revision_match" != "true" ]; then', $workflow);
        $this->assertStringContainsString('/var/www/html/VERSION-CURRENT', $workflow);
        $this->assertStringContainsString('if [ -n "$APP_CONTAINER" ]; then', $workflow);
        $this->assertStringContainsString('&& [ "$VERSION_CURRENT" = "$EXPECTED_REVISION" ]', $workflow);
        foreach ([
            '&& [ -n "$SERVICE_REVISION" ]',
            '&& [ "$SERVICE_REVISION" = "$EXPECTED_REVISION" ]',
            '&& [ -n "$CONTAINER_REVISION" ]',
            '&& [ "$CONTAINER_REVISION" = "$EXPECTED_REVISION" ]',
        ] as $strictRevisionCheck) {
            $this->assertStringContainsString($strictRevisionCheck, $workflow);
        }
        $this->assertStringContainsString('exit 1', $workflow);
        $revisionFailure = strpos($workflow, 'revision_match=false');
        $phpDiagnostic = strpos($workflow, 'docker exec -i "$APP_CONTAINER" php');
        $probe = strpos($workflow, '->putStreamAsync(');
        $this->assertNotFalse($revisionFailure);
        $this->assertNotFalse($phpDiagnostic);
        $this->assertNotFalse($probe);
        $this->assertLessThan($phpDiagnostic, $revisionFailure);
        $this->assertLessThan($probe, $revisionFailure);

        $diagnosticAutoload = strpos($workflow, "require 'vendor/autoload.php';");
        $diagnosticBootstrapApp = strpos($workflow, "require 'bootstrap/app.php';");
        $diagnosticKernelBootstrap = strpos($workflow, '$app->make(Kernel::class)->bootstrap();');
        $verifierAutoload = strpos($productionVerifier, 'require "vendor/autoload.php";');
        $verifierBootstrapApp = strpos($productionVerifier, '$app = require "bootstrap/app.php";');
        $verifierKernelBootstrap = strpos($productionVerifier, '$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();');
        foreach ([$diagnosticAutoload, $diagnosticBootstrapApp, $diagnosticKernelBootstrap, $verifierAutoload, $verifierBootstrapApp, $verifierKernelBootstrap] as $bootstrapPosition) {
            $this->assertNotFalse($bootstrapPosition);
        }
        $this->assertLessThan($diagnosticBootstrapApp, $diagnosticAutoload);
        $this->assertLessThan($diagnosticKernelBootstrap, $diagnosticBootstrapApp);
        $this->assertLessThan($verifierBootstrapApp, $verifierAutoload);
        $this->assertLessThan($verifierKernelBootstrap, $verifierBootstrapApp);
        $this->assertStringContainsString('$bootstrapOk = true;', $workflow);

        foreach ([
            'mhcs_core_app',
            "config('mhcs.private_object_disk')",
            "=== 's3'",
            'PrivateObjectStore',
            'PlainLocalObjectStore',
            'AuthenticatedContext',
            'image-gateway.capture.submit',
            'putStreamAsync',
            "'ACL' => 'private'",
            'OpaqueObjectKey::fromString',
            'random_bytes',
            "fopen('php://temp'",
            "hash('sha256'",
            'grant(',
            'get(',
            'hash_equals',
            'endpoint_host_class=',
            'endpoint_port_is_9000=',
            'container_loopback_endpoint_conflict=',
            'host.docker.internal',
            'docker_service_name',
            'loopback_ip',
            's3_probe_executed=',
        ] as $requiredOperation) {
            $this->assertStringContainsString($requiredOperation, $workflow);
        }
        $this->assertStringContainsString("'ACL' => 'private'", $objectStore);
        $this->assertSame(1, substr_count($workflow, '->putStreamAsync('));

        $endpointClassification = strpos($workflow, '$endpointClassification = classifyEndpoint(');
        $bootstrapComplete = strpos($workflow, '$bootstrapOk = true;');
        $headBucket = strpos($workflow, '$client->headBucket');
        $this->assertNotFalse($endpointClassification);
        $this->assertNotFalse($bootstrapComplete);
        $this->assertNotFalse($headBucket);
        $this->assertLessThan($endpointClassification, $bootstrapComplete);
        $this->assertLessThan($headBucket, $endpointClassification);

        $loopbackGuard = strpos($workflow, 'if ($containerLoopbackEndpointConflict && $endpointPortIs9000)');
        $diskCreation = strpos($workflow, '$disk = Storage::disk($diskName);');
        $ownershipControls = strpos($workflow, '$client->getBucketOwnershipControls');
        $objectRead = strpos($workflow, '$objects->get(');
        $objectDelete = strpos($workflow, '$disk->delete(');
        $this->assertNotFalse($loopbackGuard);
        foreach ([$diskCreation, $ownershipControls, $objectRead, $objectDelete] as $s3Operation) {
            $this->assertNotFalse($s3Operation);
            $this->assertLessThan($s3Operation, $loopbackGuard);
        }
        $this->assertLessThan($headBucket, $loopbackGuard);
        $this->assertLessThan($probe, $loopbackGuard);
        foreach ([
            'echo \'endpoint_host_class=\'.$endpointHostClass.PHP_EOL;',
            'echo \'endpoint_port_is_9000=true\'.PHP_EOL;',
            'echo \'container_loopback_endpoint_conflict=true\'.PHP_EOL;',
            'echo \'head_bucket=SKIPPED\'.PHP_EOL;',
            'echo \'ownership_controls=SKIPPED\'.PHP_EOL;',
            'echo \'acl_private_put=SKIPPED\'.PHP_EOL;',
            'echo \'private_object_roundtrip=SKIPPED\'.PHP_EOL;',
            'echo \'cleanup_primary_object=NOT_REQUIRED\'.PHP_EOL;',
            'echo \'cleanup_metadata_object=NOT_REQUIRED\'.PHP_EOL;',
            'echo \'cleanup_verified=NOT_REQUIRED\'.PHP_EOL;',
            'echo \'root_cause_boundary=s3_endpoint_configuration\'.PHP_EOL;',
            'echo \'root_cause_class=container_loopback_endpoint_conflict\'.PHP_EOL;',
            'echo \'root_cause_confirmed=true\'.PHP_EOL;',
            'echo \'s3_probe_executed=false\'.PHP_EOL;',
            'exit(0);',
        ] as $loopbackOutcome) {
            $this->assertStringContainsString($loopbackOutcome, $workflow);
        }
        $loopbackBlock = substr($workflow, $loopbackGuard, $headBucket - $loopbackGuard);
        $this->assertStringNotContainsString('->delete(', $loopbackBlock);
        $this->assertStringContainsString('$s3ProbeExecuted = true;', $workflow);
        $this->assertStringContainsString('production_s3_roundtrip_passed', $workflow);

        foreach ([
            'headBucket',
            'getBucketOwnershipControls',
            'head_bucket=',
            'ownership_controls=',
            'object_ownership_mode=',
        ] as $capabilityCheck) {
            $this->assertStringContainsString($capabilityCheck, $workflow);
        }

        foreach ([
            "'.meta.json'",
            'finally',
            'cleanup_primary_object=',
            'cleanup_metadata_object=',
            'cleanup_verified=',
            '->delete((string) $key',
            '->delete((string) $metadataKey',
        ] as $cleanupRequirement) {
            $this->assertStringContainsString($cleanupRequirement, $workflow);
        }
        $this->assertStringContainsString('root_cause_boundary=', $workflow);
        $this->assertStringContainsString('root_cause_class=', $workflow);
        $this->assertStringContainsString('root_cause_confirmed=', $workflow);
        $this->assertStringContainsString('acl_not_supported', $workflow);
        $this->assertStringContainsString('production_s3_roundtrip_passed', $workflow);

        $lowerWorkflow = strtolower($workflow);
        foreach ([
            'docker stack deploy',
            'docker service update',
            'docker compose up',
            'docker compose down',
            'php artisan',
            'artisan migrate',
            'db:seed',
            'ssh ',
            'verify-production',
            'prestige',
            'db::',
            '->table(',
            'putbucketpolicy',
            'deletebucketpolicy',
            'putbucketownershipcontrols',
            'deletebucketownershipcontrols',
            'putbucketacl',
            'deletebucketacl',
        ] as $forbiddenOperation) {
            $this->assertStringNotContainsString($forbiddenOperation, $lowerWorkflow);
        }

        foreach ([
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_BUCKET',
            'AWS_ENDPOINT',
            'AWS_DEFAULT_REGION',
        ] as $secretName) {
            $this->assertStringNotContainsString($secretName, $workflow);
            $this->assertStringNotContainsString('echo "$'.$secretName, $workflow);
            $this->assertStringNotContainsString('printf "$'.$secretName, $workflow);
        }
        foreach (['print_r(', 'var_dump(', 'phpinfo(', 'getenv(', '$_SERVER', '$_ENV'] as $disclosure) {
            $this->assertStringNotContainsString($disclosure, $workflow);
        }
        $this->assertStringNotContainsString('echo "bucket=', strtolower($workflow));
        $this->assertStringNotContainsString('echo "endpoint=', strtolower($workflow));
        $this->assertStringNotContainsString('echo "key=', strtolower($workflow));
        $this->assertStringNotContainsString('echo "$key', $workflow);
        $this->assertStringNotContainsString('echo "$metadataKey', $workflow);
        foreach (['putenv(', 'setenv(', 'config([', 'AWS_ENDPOINT'] as $configurationMutation) {
            $this->assertStringNotContainsString($configurationMutation, $workflow);
        }
    }
}
