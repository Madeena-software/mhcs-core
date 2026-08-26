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
            'host_gateway_address_resolved=',
            'host_gateway_address_family=',
            'minio_listener_matches_host_gateway=',
            'minio_listener_host_gateway_match_basis=',
            'host_to_host_gateway_9000_tcp_checked=',
            'host_to_host_gateway_9000_tcp=',
            'host_firewall_inspection=',
            'docker_gateway_9000_explicit_reject_detected=',
            'docker_gateway_9000_explicit_accept_detected=',
            'host_gateway_connectivity_root_boundary=',
            'host_gateway_connectivity_root_class=',
            'host_gateway_connectivity_root_confirmed=',
            'host_gateway_minio_health_http_status=',
            'host_gateway_minio_health=',
            'host_gateway_head_bucket=',
            'current_endpoint_host_class=',
            'current_endpoint_port_is_9000=',
            'current_endpoint_head_bucket=',
            'container_loopback_endpoint_conflict=',
            'intended_local_endpoint_viable=',
            'configured_endpoint_matches_intended_topology=',
            'configured_endpoint_root_cause_boundary=',
            'root_cause_boundary=',
            "\$rootBoundary='s3_endpoint_topology'",
            's3_probe_executed=false',
            'host_port_9000_listener_present=',
            'host_port_9000_bind_class=',
            'host_loopback_minio_health_http_status=',
            'host_loopback_minio_health=',
            'host_port_9000_owner_class=',
            'docker_port_9000_published=',
            'host_local_minio_confirmed=',
            'container_host_gateway_failure_explained=',
            'host_listener_inspection=PASS',
            'host_listener_inspection=FAIL',
            'host_listener_root_boundary=',
            'host_listener_root_class=',
            'host_listener_root_confirmed=',
            'ss -ltnH',
            'ss -ltnpH',
            'docker ps --filter publish=9000',
            '127.0.0.1:9000/minio/health/live',
            '--max-redirs 0',
            '--request GET',
            'loopback_ipv4',
            'loopback_ipv6',
            'all_ipv4',
            'all_ipv6',
            'nonloopback_specific',
            'minio_host_process',
            'docker_published',
            'no_host_port_9000_listener',
            'port_9000_not_confirmed_as_minio',
            'minio_not_bound_to_docker_host_gateway',
            'matching_listener_but_host_tcp_failed',
            'host_reachable_but_container_tcp_failed',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $this->assertStringContainsString("\$containerLoopbackEndpointConflict = in_array(\n                  \$currentEndpointHostClass,\n                  ['localhost', 'loopback_ip'],\n                  true,\n              );", $workflow);

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
            'systemctl', 'service restart', 'ufw ', 'iptables', 'firewall-cmd',
            'docker network connect', 'docker network disconnect', 'docker network rm',
            'docker restart', 'docker stop', 'docker kill', 'docker rm',
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
        $this->assertStringContainsString('host_local_minio_confirmed=false', $workflow);
        $this->assertStringContainsString('container_host_gateway_failure_explained=false', $workflow);
        $this->assertStringContainsString('host_local_minio_confirmed=true', $workflow);
        $this->assertStringContainsString('container_host_gateway_failure_explained=true', $workflow);
        $this->assertStringNotContainsString('host_local_minio_confirmed=$host_listener_root_confirmed', $workflow);
        $this->assertStringNotContainsString('container_host_gateway_failure_explained=$host_listener_root_confirmed', $workflow);
        $this->assertStringContainsString('host_listener_inspection=FAIL', $workflow);
        $this->assertStringContainsString('host_listener_root_class=host_listener_inspection_unavailable', $workflow);
        $this->assertStringContainsString('host_port_9000_owner_class=unknown', $workflow);
        $this->assertStringContainsString('host_listener_root_class=no_host_port_9000_listener', $workflow);
        $this->assertStringContainsString('host_listener_root_confirmed=true', $workflow);
        $this->assertStringContainsString('host_listener_root_class=minio_not_bound_to_docker_host_gateway', $workflow);
        $this->assertStringContainsString('host_listener_root_class=matching_listener_but_host_tcp_failed', $workflow);
        $this->assertStringContainsString('host_listener_root_class=host_reachable_but_container_tcp_failed', $workflow);
        $noListener = strpos($workflow, 'host_listener_root_class=no_host_port_9000_listener');
        $healthBranch = strpos($workflow, 'elif [ "$host_loopback_minio_health" = PASS ]; then');
        $this->assertNotFalse($noListener);
        $this->assertNotFalse($healthBranch);
        $noListenerBlock = substr($workflow, $noListener - 180, $healthBranch - ($noListener - 180));
        $this->assertStringContainsString('host_local_minio_confirmed=false', $noListenerBlock);
        $this->assertStringContainsString('container_host_gateway_failure_explained=false', $noListenerBlock);
        $this->assertStringNotContainsString('echo "$listener_address"', $workflow);
        $this->assertStringNotContainsString('echo "$listener_process_lines"', $workflow);
        $this->assertStringNotContainsString('echo "$listener_addresses"', $workflow);
    }

    public function test_bind_scope_is_derived_before_root_cause_classification(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        foreach ([
            'host_port_9000_has_loopback_ipv4',
            'host_port_9000_has_loopback_ipv6',
            'host_port_9000_has_all_ipv4',
            'host_port_9000_has_all_ipv6',
            'host_port_9000_has_nonloopback_specific',
            'host_port_9000_loopback_only',
            'host_port_9000_has_nonloopback_bind',
        ] as $output) {
            $this->assertStringContainsString($output, $workflow);
        }

        $scopeBlock = substr($workflow, strpos($workflow, 'bind_classes=()'), strpos($workflow, 'unique_bind_classes=') - strpos($workflow, 'bind_classes=()'));
        $this->assertStringContainsString('loopback_ipv4', $scopeBlock);
        $this->assertStringContainsString('loopback_ipv6', $scopeBlock);
        $this->assertStringContainsString('all_ipv4', $scopeBlock);
        $this->assertStringContainsString('all_ipv6', $scopeBlock);
        $this->assertStringContainsString('nonloopback_specific', $scopeBlock);
        $this->assertStringContainsString('host_port_9000_loopback_only=true', $workflow);
        $this->assertStringContainsString('host_port_9000_has_nonloopback_bind=true', $workflow);

        $classification = substr($workflow, strpos($workflow, 'if [ "$host_gateway_port_9000_tcp_checked" != true ]'));
        $this->assertStringContainsString('[ "$host_gateway_address_resolved" = true ]', $classification);
        $this->assertStringContainsString('[ "$minio_listener_matches_host_gateway" = false ]', $classification);
        $this->assertStringContainsString('minio_not_bound_to_docker_host_gateway', $classification);
        $this->assertStringContainsString('matching_listener_but_host_tcp_failed', $classification);
        $this->assertStringContainsString('host_reachable_but_container_tcp_failed', $classification);
        $this->assertStringNotContainsString('host_listener_root_class=host_listener_reachable_scope_but_container_connection_failed', $classification);

        foreach (['echo "$listener_address"', 'echo "$listener_process_lines"', 'echo "$listener_addresses"'] as $disclosure) {
            $this->assertStringNotContainsString($disclosure, $workflow);
        }
    }

    public function test_tcp_root_cause_requires_an_executed_gateway_check(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        $this->assertStringContainsString('$hostGatewayPort9000TcpChecked=false', $workflow);
        $this->assertStringContainsString('host_gateway_port_9000_tcp_checked=', $workflow);
        $this->assertStringContainsString('host_gateway_port_9000_tcp_checked=false', $workflow);

        $checked = strpos($workflow, '$hostGatewayPort9000TcpChecked=true;');
        $fsockopen = strpos($workflow, "\$socket=@fsockopen('host.docker.internal',9000");
        $this->assertNotFalse($checked);
        $this->assertNotFalse($fsockopen);
        $this->assertLessThan($fsockopen, $checked);
        $between = substr($workflow, $checked + strlen('$hostGatewayPort9000TcpChecked=true;'), $fsockopen - ($checked + strlen('$hostGatewayPort9000TcpChecked=true;')));
        $this->assertSame('', trim($between));

        $this->assertStringNotContainsString('[ -n "$host_gateway_port_9000_tcp" ] || host_gateway_port_9000_tcp=FAIL', $workflow);
        $this->assertStringContainsString('host_gateway_port_9000_tcp=none', $workflow);
        $this->assertStringContainsString('host_listener_root_class=container_tcp_check_not_executed', $workflow);

        $caseA = strpos($workflow, 'minio_not_bound_to_docker_host_gateway');
        $this->assertNotFalse($caseA);
        $caseABlock = substr($workflow, $caseA - 700, 900);
        foreach (['host_gateway_address_resolved" = true', 'host_listener_inspection" = PASS', 'minio_listener_matches_host_gateway" = false', 'host_gateway_port_9000_tcp" = FAIL'] as $condition) {
            $this->assertStringContainsString($condition, $caseABlock);
        }
    }

    public function test_gateway_probe_and_firewall_classification_are_sanitized(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);
        $this->assertStringContainsString('docker exec "$APP_CONTAINER" getent ahosts host.docker.internal', $workflow);
        $this->assertStringNotContainsString('echo "$host_gateway_address"', $workflow);
        $this->assertStringNotContainsString('echo "$listener_address"', $workflow);
        $this->assertStringContainsString('normalize_listener_address', $workflow);
        $this->assertStringContainsString('nft -a list ruleset', $workflow);
        foreach (['iptables', 'echo "$firewall_rules"', 'printf "%s\\n" "$firewall_rules"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($workflow));
        }
    }
}
