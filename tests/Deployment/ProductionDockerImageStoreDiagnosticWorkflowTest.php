<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionDockerImageStoreDiagnosticWorkflowTest extends TestCase
{
    public function test_production_image_store_diagnostic_is_bounded_and_read_only(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-docker-image-store.yml'));

        $this->assertIsString($workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertSame(1, substr_count($workflow, 'runs-on: self-hosted'));
        $this->assertStringContainsString('group: production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('timeout-minutes: 5', $workflow);
        $this->assertStringContainsString('contents: read', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        $this->assertStringContainsString("docker version --format 'Client={{.Client.Version}} Server={{.Server.Version}}'", $workflow);
        $this->assertStringContainsString("docker info --format '{{.Driver}}'", $workflow);
        $this->assertStringContainsString("docker info --format 'DriverStatus={{json .DriverStatus}}'", $workflow);
        $this->assertStringContainsString('STORAGE_DRIVER="$(docker info --format', $workflow);
        $this->assertStringContainsString('StorageDriver=${STORAGE_DRIVER}', $workflow);
        $this->assertStringContainsString('io.containerd.snapshotter.v1', $workflow);
        $this->assertStringContainsString('CONTAINERD_IMAGE_STORE', $workflow);
        $this->assertStringContainsString('CLASSIC_IMAGE_STORE', $workflow);
        $this->assertStringContainsString('INDETERMINATE', $workflow);
        $this->assertStringContainsString('if [ "$STORAGE_DRIVER" = "overlay2" ]; then', $workflow);
        $this->assertStringNotContainsString('grep -Fq \'overlay2\' <<< "$DRIVER_STATUS"', $workflow);
        $this->assertStringContainsString('else', $workflow);
        $this->assertStringContainsString('json.load(sys.stdin)', $workflow);
        $this->assertStringContainsString('features', $workflow);
        $this->assertStringContainsString('containerd-snapshotter', $workflow);
        $this->assertStringContainsString('daemon-containerd-snapshotter=TRUE', $workflow);
        $this->assertStringContainsString('daemon-containerd-snapshotter=FALSE', $workflow);
        $this->assertStringContainsString('daemon-containerd-snapshotter=UNSET', $workflow);
        $this->assertStringContainsString('daemon-json=UNREADABLE', $workflow);
        $this->assertStringContainsString('daemon-json=INVALID', $workflow);

        foreach ([
            'docker pull',
            'docker push',
            'docker run',
            'docker image rm',
            'docker system prune',
            'docker stack deploy',
            'docker service update',
            'docker service rm',
            'systemctl reload',
            'systemctl restart',
            'kill',
            'SIGHUP',
            'daemon.json >',
            'apt install',
            'apt-get install',
            'docker network create',
            'docker network rm',
            'iptables',
            'ip route add',
            'ip link set',
            'artisan migrate',
            'gh workflow run',
            'Phase H',
            'Phase I',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $this->assertStringNotContainsString('docker info\n', $workflow);
        $this->assertStringNotContainsString('docker system info\n', $workflow);
    }
}
