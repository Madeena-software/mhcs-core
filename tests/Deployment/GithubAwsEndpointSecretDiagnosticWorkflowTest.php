<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class GithubAwsEndpointSecretDiagnosticWorkflowTest extends TestCase
{
    public function test_workflow_is_manual_hosted_and_secret_safe(): void
    {
        $path = base_path('.github/workflows/diagnose-aws-endpoint-secret.yml');
        $this->assertFileExists($path);
        $workflow = file_get_contents($path);
        $this->assertIsString($workflow);

        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: ubuntu-latest', $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('AWS_ENDPOINT_VALUE: ${{ secrets.AWS_ENDPOINT }}', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        $this->assertStringNotContainsString('actions/checkout', $workflow);

        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'set -x'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $this->assertSame(1, substr_count($workflow, 'secrets.AWS_ENDPOINT'));
        $this->assertStringNotContainsString('echo "$AWS_ENDPOINT_VALUE"', $workflow);
        $this->assertStringNotContainsString('print(AWS_ENDPOINT_VALUE', $workflow);
        $this->assertStringNotContainsString('hashlib', $workflow);
        $this->assertStringNotContainsString('base64', strtolower($workflow));
        $this->assertStringNotContainsString('substring', strtolower($workflow));

        foreach ([
            'curl', 'wget', 'ping', 'nc ', 'telnet',
            'boto', 'requests', 'http://', 'https://', 'socket', 'gethostbyname',
            's3api', 'database', 'mysql', 'psql', 'php artisan', 'git push',
        ] as $forbiddenOperation) {
            $this->assertStringNotContainsString($forbiddenOperation, strtolower($workflow));
        }

        foreach ([
            'aws_endpoint_present=',
            'aws_endpoint_host_is_s3_mhcsgo_cloud=',
            'aws_endpoint_uses_explicit_port_9000=',
            'aws_endpoint_scheme_class=',
            'aws_endpoint_host_class=',
            'aws_endpoint_class=',
            's3.mhcsgo.cloud',
            'parsed.port',
            'urllib.parse',
            'ipaddress',
            'host.docker.internal',
            '127.0.0.0/8',
            '192.168.0.0/16',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        foreach ([
            'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET',
            'secrets.GITHUB_',
            'gh secret set', 'gh secret delete', 'gh secret edit',
        ] as $forbiddenSecretReference) {
            $this->assertStringNotContainsString($forbiddenSecretReference, $workflow);
        }
    }
}
