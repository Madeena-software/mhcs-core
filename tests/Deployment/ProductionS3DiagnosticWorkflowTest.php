<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionS3DiagnosticWorkflowTest extends TestCase
{
    public function test_production_s3_diagnostic_is_read_only_and_sanitized(): void
    {
        $workflowPath = base_path('.github/workflows/diagnose-production-s3.yml');
        $this->assertFileExists($workflowPath);
        $workflow = file_get_contents($workflowPath);
        $this->assertIsString($workflow);

        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'set -x'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $revision = 'b6232a158b3f6884fd9823bc875abc432676b781';
        $this->assertStringContainsString($revision, $workflow);
        $this->assertStringContainsString('revision_match=false', $workflow);
        $this->assertStringContainsString('if [ "$revision_match" != "true" ]; then', $workflow);
        $revisionFailure = strpos($workflow, 'revision_match=false');
        $phpDiagnostic = strpos($workflow, 'docker exec -i "$APP_CONTAINER" php');
        $this->assertNotFalse($revisionFailure);
        $this->assertNotFalse($phpDiagnostic);
        $this->assertLessThan($phpDiagnostic, $revisionFailure);
        foreach (['/var/www/html/VERSION-CURRENT', 'SERVICE_REVISION', 'CONTAINER_REVISION', 'EXPECTED_REVISION'] as $guard) {
            $this->assertStringContainsString($guard, $workflow);
        }

        $autoload = strpos($workflow, "require 'vendor/autoload.php';");
        $bootstrap = strpos($workflow, "require 'bootstrap/app.php';");
        $kernel = strpos($workflow, '$app->make(Kernel::class)->bootstrap();');
        $this->assertNotFalse($autoload);
        $this->assertNotFalse($bootstrap);
        $this->assertNotFalse($kernel);
        $this->assertLessThan($bootstrap, $autoload);
        $this->assertLessThan($kernel, $bootstrap);

        foreach ([
            "config('mhcs.private_object_disk')",
            "config('filesystems.disks.s3')",
            'Storage::disk',
            'AwsS3V3Adapter',
            'currentClient->headBucket',
            'new \\Aws\\S3\\S3Client',
            'localClient->headBucket',
            'host.docker.internal:9000',
            'gethostbyname',
            'fsockopen',
            '/minio/health/live',
            'host_gateway_resolves=',
            'host_gateway_port_9000_tcp=',
            'host_gateway_minio_health_http_status=',
            'host_gateway_minio_health=',
            'host_gateway_head_bucket=',
            'current_endpoint_host_class=',
            'current_endpoint_port_is_9000=',
            'current_endpoint_head_bucket=',
            'intended_local_endpoint_viable=',
            'configured_endpoint_matches_intended_topology=',
            'root_cause_boundary=',
            "\$rootBoundary='s3_endpoint_topology'",
            's3_probe_executed=false',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        foreach ([
            'use Aws\\Exception\\AwsException;',
            'use Illuminate\\Contracts\\Console\\Kernel;',
            'use Illuminate\\Filesystem\\AwsS3V3Adapter;',
            'use Illuminate\\Support\\Facades\\Storage;',
        ] as $import) {
            $this->assertStringContainsString($import, $workflow);
        }
        foreach ([
            'use AwsExceptionAwsException;',
            'use IlluminateContractsConsoleKernel;',
            'use IlluminateFilesystemAwsS3V3Adapter;',
            'use IlluminateSupportFacadesStorage;',
        ] as $brokenImport) {
            $this->assertStringNotContainsString($brokenImport, $workflow);
        }

        $lowerWorkflow = strtolower($workflow);
        foreach ([
            'putstreamasync', 'putobject', 'getobject', 'deleteobject', 'listobjects',
            'putbucketpolicy', 'deletebucketpolicy', 'putbucketacl', 'deletebucketacl',
            'putbucketownershipcontrols', 'deletebucketownershipcontrols',
            'docker stack deploy', 'docker service update', 'docker compose up',
            'docker compose down', 'php artisan', 'artisan migrate', 'db:seed',
            'ssh ', 'prestige', 'config([', 'putenv(', 'getenv(', '$_server', '$_env',
            'aws_endpoint', 'print_r(', 'var_dump(', 'phpinfo(',
        ] as $forbiddenOperation) {
            $this->assertStringNotContainsString($forbiddenOperation, $lowerWorkflow);
        }
        foreach (['echo "bucket=', 'echo "endpoint=', 'echo "$bucket', 'echo "$endpoint'] as $disclosure) {
            $this->assertStringNotContainsString(strtolower($disclosure), $lowerWorkflow);
        }

        $this->assertStringContainsString("'endpoint'=>'http://host.docker.internal:9000'", $workflow);
        $this->assertStringContainsString("'use_path_style_endpoint'=>\$pathStyleEnabled", $workflow);
        $this->assertStringContainsString("'credentials'=>['key'=>\$s3Config['key'],'secret'=>\$s3Config['secret']]", $workflow);
        $this->assertStringContainsString("'follow_location'=>0", $workflow);
        $this->assertStringContainsString("\$status === '200'", $workflow);
    }
}
