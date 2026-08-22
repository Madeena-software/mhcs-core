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
        $this->assertStringContainsString('exit 1', $workflow);
        $revisionFailure = strpos($workflow, 'revision_match=false');
        $probe = strpos($workflow, 'putStreamAsync');
        $this->assertNotFalse($revisionFailure);
        $this->assertNotFalse($probe);
        $this->assertLessThan($probe, $revisionFailure);

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
        ] as $requiredOperation) {
            $this->assertStringContainsString($requiredOperation, $workflow);
        }
        $this->assertStringContainsString("'ACL' => 'private'", $objectStore);
        $this->assertSame(1, substr_count($workflow, '->putStreamAsync('));

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
    }
}
