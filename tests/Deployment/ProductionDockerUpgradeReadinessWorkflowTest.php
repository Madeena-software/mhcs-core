<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionDockerUpgradeReadinessWorkflowTest extends TestCase
{
    public function test_production_docker_upgrade_readiness_diagnostic_is_read_only_and_bounded(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-docker-upgrade-readiness.yml'));

        $this->assertIsString($workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertSame(1, substr_count($workflow, 'runs-on: self-hosted'));
        $this->assertStringContainsString('group: production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('timeout-minutes: 5', $workflow);
        $this->assertStringContainsString('contents: read', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        $this->assertStringContainsString('/etc/os-release', $workflow);
        $this->assertStringContainsString('OS_CODENAME', $workflow);
        $this->assertStringContainsString('dpkg-query', $workflow);
        $this->assertStringContainsString('containerd.io', $workflow);
        $this->assertStringContainsString('docker-buildx-plugin', $workflow);
        $this->assertStringContainsString('docker-compose-plugin', $workflow);
        $this->assertStringContainsString('INSTALLED', $workflow);
        $this->assertStringContainsString('NOT_INSTALLED', $workflow);
        $this->assertStringContainsString('HELD', $workflow);
        $this->assertStringContainsString('NOT_HELD', $workflow);
        $this->assertStringContainsString('UNKNOWN', $workflow);
        $this->assertStringContainsString('docker-ce-cli', $workflow);
        $this->assertStringContainsString('apt-cache policy', $workflow);
        $this->assertStringContainsString('apt-mark showhold', $workflow);
        $this->assertStringContainsString('https://download.docker.com/linux/ubuntu', $workflow);
        $this->assertStringContainsString('/etc/apt/sources.list.d/*.list', $workflow);
        $this->assertStringContainsString('/etc/apt/sources.list.d/*.sources', $workflow);
        $this->assertStringContainsString("'types'", $workflow);
        $this->assertStringContainsString("'uris'", $workflow);
        $this->assertStringContainsString('inspect_stanza', $workflow);
        $this->assertStringContainsString("if not line:", $workflow);
        $this->assertStringContainsString('DOCKER_APT_REPOSITORY=INDETERMINATE', $workflow);
        $this->assertStringContainsString('apt-cache madison', $workflow);
        $this->assertStringContainsString('29\\.7\\.2', $workflow);
        $this->assertStringContainsString('AVAILABLE', $workflow);
        $this->assertStringContainsString('NOT_OBSERVED', $workflow);
        $this->assertStringContainsString('package_key="${package^^}"', $workflow);
        $this->assertStringContainsString('DOCKER_APT_INDEX_LATEST_UTC', $workflow);
        $this->assertStringNotContainsString('echo "APT_INDEX_LATEST_UTC=', $workflow);
        $this->assertStringNotContainsString('find /var/lib/apt/lists -maxdepth 1 -type f -printf', $workflow);
        $this->assertStringNotContainsString('cat /etc/apt', $workflow);
        $this->assertStringContainsString('live-restore', $workflow);
        $this->assertStringContainsString('{{.Swarm.LocalNodeState}}', $workflow);
        $this->assertStringContainsString('docker node ls', $workflow);
        $this->assertStringContainsString('docker node ps self', $workflow);
        $this->assertStringContainsString('{{.Name}}', $workflow);
        $this->assertStringContainsString('LOCAL_RUNNING_SWARM_TASK_SERVICES', $workflow);
        $this->assertStringContainsString('systemctl is-active docker', $workflow);
        $this->assertStringContainsString('READY_FOR_UPGRADE_PLANNING', $workflow);
        $this->assertStringContainsString('NEEDS_PACKAGE_INDEX_REFRESH', $workflow);
        $this->assertStringContainsString('SWARM_MAINTENANCE_RISK', $workflow);
        $this->assertStringContainsString('UNSAFE_SINGLE_MANAGER', $workflow);
        $this->assertStringContainsString('UNSAFE_QUORUM', $workflow);
        $this->assertStringContainsString('SAFE_FOR_ONE_MANAGER_TEMPORARY_OUTAGE', $workflow);
        $this->assertStringContainsString('reachable_count - 1', $workflow);
        $this->assertStringContainsString('READINESS=READY_FOR_UPGRADE_PLANNING', $workflow);
        $this->assertStringContainsString('PACKAGE_SOURCE_BLOCKER', $workflow);
        $this->assertStringContainsString('INDETERMINATE', $workflow);

        foreach ([
            'apt update',
            'apt-get update',
            'apt upgrade',
            'apt-get upgrade',
            'apt install',
            'apt-get install',
            'apt remove',
            'apt-get remove',
            'dpkg -i',
            'docker pull',
            'docker push',
            'docker run',
            'docker image rm',
            'docker node update',
            'docker node promote',
            'docker node demote',
            'docker swarm leave',
            'docker swarm init',
            'docker service update',
            'docker service scale',
            'docker stack deploy',
            'systemctl restart docker',
            'systemctl reload docker',
            'daemon.json >',
            'iptables',
            'ip route add',
            'ip link set',
            'gh workflow run',
            'Phase G',
            'Phase H',
            'Phase I',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }
}
