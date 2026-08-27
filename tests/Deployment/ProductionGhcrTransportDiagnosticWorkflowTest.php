<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionGhcrTransportDiagnosticWorkflowTest extends TestCase
{
    public function test_production_ghcr_transport_diagnostic_is_bounded_and_read_only(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-ghcr-transport.yml'));

        $this->assertIsString($workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertSame(1, substr_count($workflow, 'runs-on: self-hosted'));
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        $this->assertStringContainsString('PROBE_ATTEMPTS=5', $workflow);
        $this->assertStringContainsString('for attempt in $(seq 1 "$PROBE_ATTEMPTS")', $workflow);
        $this->assertStringContainsString('https://ghcr.io/v2/', $workflow);
        $this->assertStringContainsString('--connect-timeout 5', $workflow);
        $this->assertStringContainsString('--max-time 15', $workflow);
        $this->assertStringContainsString('getent ahostsv4 ghcr.io', $workflow);
        $this->assertStringContainsString('--resolve "ghcr.io:443:${address}"', $workflow);
        $this->assertStringContainsString('--proto =https', $workflow);
        $this->assertStringContainsString('--cacert', $workflow);

        foreach ([
            'docker info',
            'docker system info',
            '/etc/docker/daemon.json',
            'systemctl show docker',
            'ip -4 addr',
            'ip -4 route',
            'ip -s link',
            'ss -s',
            'nstat',
            'tracepath',
            'journalctl -u docker',
            'max-concurrent-downloads',
            'max-download-attempts',
            'registry-mirrors',
            'mtu',
        ] as $observation) {
            $this->assertStringContainsString($observation, $workflow);
        }

        foreach ([
            'docker pull',
            'docker stack deploy',
            'docker service update',
            'docker service rm',
            'docker network create',
            'docker network rm',
            'docker system prune',
            'systemctl restart docker',
            'systemctl reload docker',
            'daemon.json >',
            'artisan migrate',
            'gh workflow run',
            'GHCR_READ_TOKEN',
            'GITHUB_TOKEN',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $this->assertStringContainsString('journalctl -u docker --since "2026-08-26T23:26:00Z" --until "2026-08-26T23:45:00Z"', $workflow);
        $this->assertStringContainsString('sed -E', $workflow);
        $this->assertStringNotContainsString('systemctl show docker -p Environment', $workflow);
        $this->assertStringNotContainsString('systemctl show docker -p ExecStart', $workflow);
        $this->assertStringContainsString('DOCKER_SERVICE_ENV="$(', $workflow);
        $this->assertStringContainsString('--property=Environment', $workflow);
        $this->assertStringContainsString('--value', $workflow);
        $this->assertStringContainsString('docker-service-${name}=SET', $workflow);
        $this->assertStringContainsString('docker-service-${name}=UNSET', $workflow);
        $this->assertStringNotContainsString('${!name:-}', $workflow);
        $this->assertStringNotContainsString('RegistryConfig={{json .RegistryConfig}}', $workflow);
        $this->assertStringContainsString('json.load(sys.stdin)', $workflow);
        $this->assertStringContainsString('print("daemon-" + key + "="', $workflow);
        $this->assertStringContainsString('DOCKER_SERVICE_ENV="$(systemctl show docker --property=Environment --value', $workflow);
        $this->assertStringContainsString('service_env_has() {', $workflow);
        foreach (['HTTP_PROXY', 'http_proxy', 'HTTPS_PROXY', 'https_proxy', 'NO_PROXY', 'no_proxy'] as $proxy) {
            $this->assertStringContainsString($proxy, $workflow);
        }
        $this->assertStringContainsString('echo "docker-${flag}-flag=SET"', $workflow);
        $this->assertStringNotContainsString('tracepath -4 -m 5 -w 5', $workflow);
        $this->assertStringContainsString('timeout 10s tracepath -4 -m 5 ghcr.io', $workflow);
    }
}
