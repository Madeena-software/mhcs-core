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
            'host_gateway_minio_health_checked=',
            'host_gateway_minio_health_http_status=',
            'host_gateway_minio_health=',
            'container_host_gateway_route_checked=',
            'container_host_gateway_route=',
            'container_host_gateway_minio_health=',
            'packet_path_container_tcp_probe_triggered=',
            'packet_path_container_tcp_probe_result=',
            'packet_path_observation_available=',
            'packet_path_observation_tool=',
            'packet_path_observation_started_before_probe=',
            'packet_observer_covered_probe=',
            'packet_observer_start_class=',
            'packet_observer_exited_before_ready=',
            'container_syn_reaches_host=',
            'host_synack_observed=',
            'host_rst_observed=',
            'container_ack_after_synack_observed=',
            'packet_classifier_completed=',
            'packet_classification_valid=',
            'host_tcp9000_drop_reject_candidate=',
            'host_tcp9000_accept_candidate=',
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
            'listener_mismatch_but_host_tcp_passed',
            'container_has_no_route_to_host_gateway',
            'route_present_but_container_tcp_failed',
            'host_gateway_port_9000_not_confirmed_as_minio',
            'container_syn_not_observed_on_host',
            'container_syn_reached_host_and_rst_returned',
            'container_syn_reached_host_without_tcp_response',
            'host_synack_observed_without_container_ack',
            'tcp_handshake_observed_but_probe_failed',
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
        $this->assertStringContainsString('host_listener_root_class=listener_mismatch_but_host_tcp_passed', $workflow);
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
        foreach (['host_gateway_address_resolved" = true', 'host_listener_inspection" = PASS', 'minio_listener_matches_host_gateway" = false', 'host_to_host_gateway_9000_tcp_checked" = true', 'host_to_host_gateway_9000_tcp" = FAIL', 'host_gateway_port_9000_tcp_checked" = true', 'host_gateway_port_9000_tcp" = FAIL'] as $condition) {
            $this->assertStringContainsString($condition, $caseABlock);
        }
        $this->assertStringContainsString('listener_mismatch_but_host_tcp_passed', $workflow);
        $this->assertStringContainsString('listener_match_ambiguous=true', $workflow);
        $this->assertStringContainsString("'*:9000')", $workflow);
        $this->assertStringContainsString('minio_listener_matches_host_gateway=unknown', $workflow);
        $passMismatch = strpos($workflow, 'listener_mismatch_but_host_tcp_passed');
        $this->assertNotFalse($passMismatch);
        $passMismatchBlock = substr($workflow, $passMismatch - 700, 1400);
        foreach (['minio_listener_matches_host_gateway" = false', 'host_to_host_gateway_9000_tcp" = PASS', 'host_gateway_port_9000_tcp" = FAIL', 'host_gateway_connectivity_root_confirmed=false'] as $condition) {
            $this->assertStringContainsString($condition, $passMismatchBlock);
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
        $this->assertStringContainsString('docker_gateway_9000_explicit_reject_detected=unknown', $workflow);
        $this->assertStringContainsString('docker_gateway_9000_explicit_accept_detected=unknown', $workflow);
        foreach (['iptables', 'nft ', 'echo "$firewall_rules"', 'printf "%s\\n" "$firewall_rules"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($workflow));
        }
    }

    public function test_host_gateway_identity_and_container_route_are_bounded_and_sanitized(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        $this->assertStringContainsString('curl --silent --output /dev/null', $workflow);
        $this->assertStringContainsString('--connect-timeout 3', $workflow);
        $this->assertStringContainsString('--max-time 3', $workflow);
        $this->assertStringContainsString('--max-redirs 0', $workflow);
        $this->assertStringContainsString('"http://${gateway_health_host}:9000/minio/health/live"', $workflow);
        $this->assertStringContainsString('container_host_gateway_minio_health=', $workflow);
        $this->assertStringContainsString('docker exec "$APP_CONTAINER" sh -c', $workflow);
        $this->assertStringContainsString('ip route get "$1"', $workflow);
        $this->assertStringContainsString('__IP_UNAVAILABLE__', $workflow);
        $this->assertStringContainsString('__IP_LOOKUP_FAILED__', $workflow);
        $this->assertStringContainsString('reachable_via_gateway', $workflow);
        $this->assertStringContainsString('reachable_direct', $workflow);
        $this->assertStringContainsString('unreachable', $workflow);
        $this->assertStringContainsString('prohibit', $workflow);
        $this->assertStringContainsString('blackhole', $workflow);
        $this->assertStringContainsString('container_has_no_route_to_host_gateway', $workflow);
        $this->assertStringContainsString('route_present_but_container_tcp_failed', $workflow);
        $this->assertStringContainsString('host_gateway_port_9000_not_confirmed_as_minio', $workflow);
        foreach (['echo "$host_gateway_address"', 'echo "$gateway_health_host"', 'echo "$container_route_output"', 'echo "$route_output"'] as $disclosure) {
            $this->assertStringNotContainsString($disclosure, $workflow);
        }

        $caseA = strpos($workflow, 'container_has_no_route_to_host_gateway');
        $this->assertNotFalse($caseA);
        $caseABlock = substr($workflow, $caseA - 700, 950);
        foreach (['host_gateway_minio_health" = PASS', 'container_host_gateway_route_checked" = true', 'container_host_gateway_route" = unreachable', 'host_gateway_port_9000_tcp_checked" = true', 'host_gateway_port_9000_tcp" = FAIL', 'host_gateway_connectivity_root_confirmed=true'] as $condition) {
            $this->assertStringContainsString($condition, $caseABlock);
        }

        $caseB = strpos($workflow, 'route_present_but_container_tcp_failed');
        $this->assertNotFalse($caseB);
        $caseBBlock = substr($workflow, $caseB - 650, 850);
        foreach (['host_gateway_minio_health" = PASS', 'reachable_direct', 'reachable_via_gateway', 'host_gateway_port_9000_tcp" = FAIL', 'host_gateway_connectivity_root_confirmed=false'] as $condition) {
            $this->assertStringContainsString($condition, $caseBBlock);
        }
    }

    public function test_packet_path_probe_is_optional_bounded_and_conservative(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        $this->assertStringContainsString('command -v tcpdump', $workflow);
        $this->assertStringContainsString('tcpdump -D', $workflow);
        $this->assertStringContainsString('timeout 15 tcpdump', $workflow);
        $this->assertStringContainsString('tcp port 9000 and host $host_gateway_address', $workflow);
        $this->assertStringContainsString('packet_path_observation_started_before_probe=true', $workflow);
        $this->assertStringContainsString('packet_observer_ready=false', $workflow);
        $this->assertStringContainsString("grep -Fq 'listening on' \"\$packet_status_file\"", $workflow);
        $this->assertStringContainsString('readiness_attempt" -lt 25', $workflow);
        $this->assertStringContainsString('packet_observation_completed=true', $workflow);
        $this->assertStringContainsString('packet_observer_covered_probe=false', $workflow);
        $this->assertStringContainsString('packet_observer_alive_before_probe=true', $workflow);
        $this->assertStringContainsString('packet_observer_alive_after_probe=true', $workflow);
        $this->assertStringContainsString('packet_classifier_completed=false', $workflow);
        $this->assertStringContainsString('packet_classification_valid=false', $workflow);
        $this->assertStringContainsString('packet_observer_start_class=unknown', $workflow);
        $this->assertStringContainsString('packet_observer_exited_before_ready=false', $workflow);
        $this->assertStringContainsString('classify_packet_observer_status()', $workflow);
        $this->assertStringContainsString('packet_observer_start_class=ready', $workflow);
        $this->assertStringContainsString('packet_observer_start_class=readiness_timeout', $workflow);
        $this->assertStringContainsString('packet_observer_exited_before_ready=true', $workflow);
        $this->assertStringContainsString('if wait "$packet_classifier_pid" 2>/dev/null; then', $workflow);
        $this->assertStringContainsString('line_count != 4', $workflow);
        foreach (['syn', 'synack', 'rst', 'ack'] as $field) {
            $this->assertStringContainsString("seen[\"$field\"] != 1", $workflow);
        }
        $this->assertStringContainsString('$2 !~ /^[01]$/', $workflow);
        $this->assertStringContainsString('fsockopen("host.docker.internal",9000', $workflow);
        $this->assertStringContainsString('3);', $workflow);
        $this->assertStringContainsString('packet_path_container_tcp_probe_triggered=true', $workflow);
        $this->assertStringContainsString('packet_path_observation_available=false', $workflow);
        $this->assertStringContainsString('container_syn_reaches_host=unknown', $workflow);
        $this->assertStringContainsString('packet_path_observation_tool=unavailable', $workflow);
        $this->assertStringContainsString('host_tcp9000_drop_reject_candidate=unknown', $workflow);
        $this->assertStringContainsString('host_tcp9000_accept_candidate=unknown', $workflow);
        $this->assertStringContainsString('mkfifo', $workflow);
        $this->assertStringContainsString('rmdir "$packet_temp_dir"', $workflow);
        foreach (['tcpdump -w', 'upload-artifact', 'echo "$packet_capture', 'echo "$packet_classification', 'echo "$packet_status', 'echo "$container_route_output"', 'echo "$packet_probe_output"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $probe = strpos($workflow, 'packet_path_container_tcp_probe_triggered=true');
        $observer = strpos($workflow, 'packet_path_observation_started_before_probe=true');
        $this->assertNotFalse($probe);
        $this->assertNotFalse($observer);
        $this->assertLessThan($probe, $observer);

        $caseA = strpos($workflow, 'container_syn_not_observed_on_host');
        $this->assertNotFalse($caseA);
        $caseABlock = substr($workflow, $caseA - 900, 1100);
        foreach (['host_gateway_minio_health" = PASS', 'packet_path_observation_available" = true', 'packet_path_observation_started_before_probe" = true', 'packet_observer_covered_probe" = true', 'packet_observation_completed" = true', 'packet_classifier_completed" = true', 'packet_classification_valid" = true', 'packet_path_container_tcp_probe_triggered" = true', 'packet_path_container_tcp_probe_result" = FAIL', 'container_syn_reaches_host" = false', 'host_gateway_connectivity_root_confirmed=true'] as $condition) {
            $this->assertStringContainsString($condition, $caseABlock);
        }

        $caseB = strpos($workflow, 'container_syn_reached_host_and_rst_returned');
        $this->assertNotFalse($caseB);
        $caseBBlock = substr($workflow, $caseB - 450, 650);
        foreach (['container_syn_reaches_host" = true', 'host_rst_observed" = true', 'packet_path_container_tcp_probe_result" = FAIL', 'host_gateway_connectivity_root_confirmed=false'] as $condition) {
            $this->assertStringContainsString($condition, $caseBBlock);
        }

        foreach (['container_syn_reached_host_without_tcp_response', 'host_synack_observed_without_container_ack', 'tcp_handshake_observed_but_probe_failed'] as $unconfirmedClass) {
            $position = strpos($workflow, $unconfirmedClass);
            $this->assertNotFalse($position);
            $this->assertStringContainsString('host_gateway_connectivity_root_confirmed=false', substr($workflow, $position - 300, 500));
        }

        foreach (['permission_denied', 'interface_unavailable', 'filter_error', 'early_exit'] as $startupClass) {
            $this->assertStringContainsString($startupClass, $workflow);
            $this->assertStringNotContainsString("packet_observer_start_class=$startupClass\n              host_gateway_connectivity_root_confirmed=true", $workflow);
        }
    }

    public function test_packet_observer_start_classifier_executes_against_synthetic_status(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        $start = strpos($workflow, '          classify_packet_observer_status() {');
        $end = strpos($workflow, "\n          if [ \"\$host_gateway_address_resolved\"", $start === false ? 0 : $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $function = substr($workflow, $start, $end - $start);
        $function = preg_replace('/^ {10}/m', '', $function);
        $this->assertIsString($function);

        $cases = [
            'permission' => ["tcpdump: permission denied\n", 'permission_denied'],
            'interface' => ["tcpdump: eth9: No such device\n", 'interface_unavailable'],
            'filter' => ["tcpdump: syntax error in filter expression\n", 'filter_error'],
            'unknown exit' => ["tcpdump: capture startup failed\n", 'early_exit'],
        ];

        foreach ($cases as $name => [$status, $expected]) {
            $statusPath = tempnam(sys_get_temp_dir(), 'mhcs-tcpdump-status-');
            $this->assertNotFalse($statusPath);
            file_put_contents($statusPath, $status);
            $output = [];
            $exitCode = 0;
            try {
                exec('bash -c '.escapeshellarg($function.'; classify_packet_observer_status "$1"').' -- '.escapeshellarg($statusPath), $output, $exitCode);
            } finally {
                @unlink($statusPath);
            }
            $this->assertSame(0, $exitCode, $name);
            $this->assertSame([$expected], $output, $name);
            $this->assertNotContains($status, $output, $name);
        }
    }

    public function test_packet_observer_status_remains_private_and_startup_class_cannot_confirm_network_root(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        foreach (['cat "$packet_status_file"', 'tail "$packet_status_file"', 'head "$packet_status_file"', 'sed .*"$packet_status_file"', 'echo "$packet_status_file"'] as $forbidden) {
            $this->assertDoesNotMatchRegularExpression('/'.preg_quote($forbidden, '/').'/', $workflow);
        }
        $this->assertStringContainsString('packet_observer_start_class=ready', $workflow);
        $this->assertStringContainsString('packet_observer_start_class=readiness_timeout', $workflow);
        $this->assertStringContainsString('host_gateway_connectivity_root_confirmed=false', $workflow);
    }

    public function test_packet_classifier_executes_against_synthetic_tcpdump_lines(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/diagnose-production-s3.yml'));
        $this->assertIsString($workflow);

        $syn = 'IP 172.18.0.2.40000 > 172.18.0.1.9000: Flags [S], seq 1, win 64240, length 0';
        $synAck = 'IP 172.18.0.1.9000 > 172.18.0.2.40000: Flags [S.], seq 2, ack 2, win 64240, length 0';
        $rst = 'IP 172.18.0.1.9000 > 172.18.0.2.40000: Flags [R.], seq 2, win 0, length 0';
        $ack = 'IP 172.18.0.2.40000 > 172.18.0.1.9000: Flags [.], ack 3, win 64240, length 0';
        $expected = fn (int $syn, int $synAck, int $rst, int $ack): array => [
            'syn' => (string) $syn,
            'synack' => (string) $synAck,
            'rst' => (string) $rst,
            'ack' => (string) $ack,
        ];

        $cases = [
            'no packets' => ['', $expected(0, 0, 0, 0)],
            'outbound SYN' => [$syn, $expected(1, 0, 0, 0)],
            'SYN then SYN-ACK' => [$syn.PHP_EOL.$synAck, $expected(1, 1, 0, 0)],
            'SYN then RST' => [$syn.PHP_EOL.$rst, $expected(1, 0, 1, 0)],
            'SYN then SYN-ACK then ACK' => [$syn.PHP_EOL.$synAck.PHP_EOL.$ack, $expected(1, 1, 0, 1)],
        ];

        foreach ($cases as $name => [$input, $expectedOutput]) {
            [$exitCode, $actualOutput] = $this->executePacketClassifier($workflow, $input);
            $this->assertSame(0, $exitCode, $name);
            $actual = [];
            foreach ($actualOutput as $line) {
                [$key, $value] = explode('=', trim($line), 2);
                $actual[$key] = $value;
            }
            $this->assertSame($expectedOutput, $actual, $name);
        }
    }

    /** @return array{0:int,1:list<string>} */
    private function executePacketClassifier(string $workflow, string $input): array
    {
        $start = strpos($workflow, "(awk '\n");
        $endToken = "' <\"\$packet_fifo\"";
        $end = strpos($workflow, $endToken, $start === false ? 0 : $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $program = substr($workflow, $start + strlen("(awk '\n"), $end - ($start + strlen("(awk '\n")));

        $inputPath = tempnam(sys_get_temp_dir(), 'mhcs-packet-');
        $this->assertNotFalse($inputPath);
        file_put_contents($inputPath, $input);
        $output = [];
        $exitCode = 0;
        try {
            exec('awk '.escapeshellarg($program).' '.escapeshellarg($inputPath), $output, $exitCode);
        } finally {
            @unlink($inputPath);
        }

        return [$exitCode, $output];
    }
}
