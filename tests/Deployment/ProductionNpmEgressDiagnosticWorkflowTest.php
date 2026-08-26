<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionNpmEgressDiagnosticWorkflowTest extends TestCase
{
    public function test_production_npm_egress_diagnostic_is_bounded_and_manual_only(): void
    {
        $path = base_path('.github/workflows/diagnose-production-npm-egress.yml');
        $this->assertFileExists($path);
        $workflow = file_get_contents($path);
        $this->assertIsString($workflow);

        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('timeout-minutes: 10', $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('group: production-npm-egress-diagnostic', $workflow);

        foreach (['push:', 'pull_request:', 'schedule:', 'secrets.', 'secrets[', 'environment:', 'docker stack deploy', 'docker service update', 'php artisan migrate', 'php artisan db:seed', 'mysql', 'mysqldump', 'AWS_', 'MPIPS_', 'MHCS_REAL_NPZ', 'mhcs-core-application-network', 'mhcs-mpips-integration-v1', 'docker builder prune', 'docker system prune', 'npm cache clean', 'npm run build', 'npm install', 'strict-ssl=false', 'NODE_TLS_REJECT_UNAUTHORIZED=0', 'registry='] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $this->assertStringContainsString('registry.npmjs.org', $workflow);
        $this->assertStringNotContainsString('workflow_dispatch.inputs', $workflow);
        $this->assertStringNotContainsString('github.event.inputs', $workflow);
        $this->assertSame(3, substr_count($workflow, 'https://registry.npmjs.org/'));
        $this->assertStringContainsString('for attempt in 1 2 3', $workflow);
        $this->assertStringContainsString('host_tarball_probe_$2', $workflow);
        $this->assertStringContainsString('tarball_1_result="$result"', $workflow);
        $this->assertStringContainsString('tarball_2_result="$result"', $workflow);
        $this->assertStringContainsString('tarball_3_result="$result"', $workflow);

        foreach (['host_dns_resolution=', 'host_dns_ipv4_count=', 'host_dns_ipv6_count=', 'host_https_ipv4_successes=', 'host_https_ipv6_status=', 'docker_default_dns=', 'docker_default_https_successes=', 'docker_hostnet_dns=', 'docker_hostnet_https_successes=', 'npm_reproduction=', 'npm_failure_code=', 'root_cause_classification=', 'production_mutation_performed=false', 'workspace_cleanup='] as $field) {
            $this->assertStringContainsString($field, $workflow);
        }

        $this->assertSame(2, substr_count($workflow, 'curl -4'));
        $this->assertStringContainsString('curl -6', $workflow);
        $this->assertStringContainsString('--connect-timeout 10', $workflow);
        $this->assertStringContainsString('--max-time 20', $workflow);
        $this->assertStringContainsString('64 KiB', $workflow);
        $this->assertStringContainsString('--range 0-65535', $workflow);
        $this->assertStringContainsString('--max-filesize 65536', $workflow);
        $this->assertStringContainsString('node:24-alpine@sha256:d32cdf619f63fe0471182d08996dd516c6275bb5fd31ae06e55a570bd9e1ad43', $workflow);
        $this->assertSame(1, substr_count($workflow, '--network host'));
        $this->assertGreaterThanOrEqual(2, substr_count($workflow, '--rm'));
        $this->assertStringNotContainsString('/var/run/docker.sock', $workflow);
        $this->assertStringNotContainsString('--privileged', $workflow);
        $this->assertStringContainsString('timeout 360s docker run', $workflow);
        $this->assertStringContainsString('--ignore-scripts', $workflow);
        $this->assertStringContainsString('NPM_CONFIG_FETCH_RETRIES=5', $workflow);
        $this->assertStringContainsString('NPM_CONFIG_FETCH_RETRY_FACTOR=2', $workflow);
        $this->assertStringContainsString('NPM_CONFIG_FETCH_RETRY_MINTIMEOUT=20000', $workflow);
        $this->assertStringContainsString('NPM_CONFIG_FETCH_RETRY_MAXTIMEOUT=120000', $workflow);
        $this->assertStringContainsString('NPM_CONFIG_FETCH_TIMEOUT=300000', $workflow);
        $this->assertStringContainsString('timeout 360s', $workflow);
        $this->assertStringContainsString('trap cleanup EXIT', $workflow);

        foreach (['HOST_NETWORK_FAILURE', 'DOCKER_DEFAULT_NETWORK_FAILURE', 'IPV6_PATH_DEGRADED', 'NPM_TARBALL_PATH_FAILURE', 'NPM_CI_NETWORK_FAILURE', 'NETWORK_INTERMITTENT', 'NO_NETWORK_FAILURE_OBSERVED', 'UNKNOWN'] as $classification) {
            $this->assertStringContainsString($classification, $workflow);
        }
        foreach (['NONE', 'ETIMEDOUT', 'ECONNRESET', 'EAI_AGAIN', 'ENETUNREACH', 'OTHER'] as $failureCode) {
            $this->assertStringContainsString($failureCode, $workflow);
        }
        $this->assertStringContainsString('recognized_npm_network_failure=false', $workflow);
        $this->assertStringContainsString('case "$npm_failure_code" in'."\n".'            ETIMEDOUT|ECONNRESET|EAI_AGAIN|ENETUNREACH)', $workflow);
        $this->assertStringContainsString('recognized_npm_network_failure=true', $workflow);
        $this->assertStringNotContainsString('OTHER|', $workflow);
        $this->assertMatchesRegularExpression(
            '/recognized_npm_network_failure=false.*case "\$npm_failure_code" in.*ETIMEDOUT\|ECONNRESET\|EAI_AGAIN\|ENETUNREACH\).*recognized_npm_network_failure=true/s',
            $workflow,
        );
        $this->assertMatchesRegularExpression(
            '/\[ "\$npm_reproduction" = FAIL \].*\[ "\$recognized_npm_network_failure" = true \].*\[ "\$lightweight_network_healthy" = true \].*classification=NPM_CI_NETWORK_FAILURE/s',
            $workflow,
        );
        $this->assertStringContainsString('[ "$host_https_ipv4_successes" -ge 2 ]', $workflow);
        $this->assertStringContainsString('[ "$tarball_1_result" = PASS ]', $workflow);
        $this->assertStringContainsString('[ "$tarball_2_result" = PASS ]', $workflow);
        $this->assertStringContainsString('[ "$tarball_3_result" = PASS ]', $workflow);
        $this->assertStringContainsString('[ "${docker_default_https_successes:-0}" -ge 2 ]', $workflow);
        $this->assertStringContainsString('[ "${docker_hostnet_https_successes:-0}" -ge 2 ]', $workflow);
        $this->assertStringNotContainsString(
            'elif [ "$npm_reproduction" = FAIL ] || [ "$npm_reproduction" = TIMEOUT ]; then\n            classification=NPM_CI_NETWORK_FAILURE',
            $workflow,
        );
    }
}
