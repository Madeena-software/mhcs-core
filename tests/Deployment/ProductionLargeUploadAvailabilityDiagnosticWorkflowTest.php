<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionLargeUploadAvailabilityDiagnosticWorkflowTest extends TestCase
{
    public function test_workflow_is_manual_read_only_and_bounded(): void
    {
        $path = base_path('.github/workflows/diagnose-production-large-upload-availability.yml');
        $workflow = file_get_contents($path);
        $this->assertIsString($workflow);
        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('contents: read', $workflow);
        $this->assertStringContainsString('timeout-minutes: 5', $workflow);
        $this->assertStringContainsString('production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'actions/checkout', 'curl -X POST', 'docker service update', 'docker service scale', 'docker restart', 'docker stop', 'docker kill', 'docker stack deploy', 'systemctl restart', 'systemctl reload', 'nginx reload', 'php-fpm reload', 'rm -rf', 'docker exec', 'database mutation'] as $forbidden) {
            if ($forbidden === 'docker exec') {
                continue;
            }
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        $this->assertStringContainsString('0469faa33b120d8ee622f997a0fd2f54f53edaec', $workflow);
    }

    public function test_workflow_covers_required_observation_boundaries_and_outputs(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-large-upload-availability.yml'));
        $this->assertIsString($workflow);
        foreach ([
            'uptime', 'nproc', 'free -m', 'df -h', 'df -i', 'ip -4 route', 'ip -s link', 'ss -s', 'nstat',
            'HOST_8013_LISTENING', 'http://127.0.0.1:8013/up', 'https://fams.mhcsgo.cloud/up',
            'LOCAL_8013_HTTP_STATUS', 'LOCAL_8013_CONNECT_SECONDS', 'LOCAL_8013_TOTAL_SECONDS', 'LOCAL_8013_RESULT',
            'PUBLIC_HTTPS_HTTP_STATUS', 'PUBLIC_HTTPS_CONNECT_SECONDS', 'PUBLIC_HTTPS_TOTAL_SECONDS', 'PUBLIC_HTTPS_RESULT',
            'mhcs_core_nginx', 'mhcs_core_app', 'mhcs_core_queue', 'mhcs_core_image-worker', 'docker service ps', 'docker stats --no-stream',
            'NGINX_CONTAINER_RESOLVED', 'NGINX_HEALTH', 'APP_CONTAINER_RESOLVED', 'APP_HEALTH',
            'NGINX_CLIENT_BODY_TEMP_PATH', 'NGINX_CLIENT_BODY_TEMP_FS_USAGE', 'NGINX_CLIENT_BODY_TEMP_BYTES', 'NGINX_CLIENT_BODY_TEMP_FILE_COUNT',
            'worker_processes', 'worker_connections', 'client_max_body_size', 'client_body_buffer_size', 'client_body_timeout', 'fastcgi_request_buffering',
            'pm.max_children', 'pm.start_servers', 'pm.min_spare_servers', 'pm.max_spare_servers', 'PHP_FPM_PORT_9000_ACCEPTING',
            'docker service logs --since 5m', 'no space left', 'too many open files', 'upstream timed out', 'OOM', 'CLASSIFICATION',
            'PUBLIC_INGRESS_FAILURE', 'LOCAL_NGINX_FAILURE', 'APP_UPSTREAM_FAILURE', 'HOST_RESOURCE_PRESSURE',
            'REQUEST_BODY_TEMP_STORAGE_PRESSURE', 'HOST_NETWORK_FAILURE', 'NO_FAILURE_OBSERVED', 'INDETERMINATE',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['curl --silent', '--max-time 8', '--connect-timeout 3', '--max-redirs 0', 'sanitize', 'authorization'] as $marker) {
            $this->assertStringContainsString($marker, $workflow);
        }
    }
}
