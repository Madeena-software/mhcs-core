<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionVerificationWorkflowTest extends TestCase
{
    public function test_consolidated_production_verification_is_manual_read_only_and_conditional(): void
    {
        $workflowPath = base_path('.github/workflows/verify-production.yml');
        $this->assertFileExists($workflowPath);
        $this->assertFileDoesNotExist(base_path('.github/workflows/verify-production-upload.yml'));

        $workflow = file_get_contents($workflowPath);
        $this->assertIsString($workflow);

        $normalizedWorkflow = preg_replace('/\s+/', ' ', $workflow);
        $this->assertIsString($normalizedWorkflow);

        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringNotContainsString('push:', $workflow);
        $this->assertStringNotContainsString('pull_request:', $workflow);
        $this->assertStringNotContainsString('schedule:', $workflow);
        $this->assertStringNotContainsString('cron:', $workflow);

        foreach ([
            'expected_revision:',
            'run_large_upload_probe:',
            'verify_prestige:',
        ] as $input) {
            $this->assertStringContainsString($input, $workflow);
        }

        $this->assertStringContainsString('required: false', $workflow);
        $this->assertStringContainsString('default: false', $workflow);
        $this->assertStringContainsString('type: string', $workflow);
        $this->assertStringContainsString('type: boolean', $workflow);
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('group: production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);

        foreach ([
            'Swarm.LocalNodeState',
            'Swarm.ControlAvailable',
            'docker service ls',
            'mhcs_core_db',
            'mhcs_core_app',
            'mhcs_core_queue',
            'mhcs_core_scheduler',
            'mhcs_core_image-worker',
            'mhcs_core_nginx',
            'mhcs-core-application-network',
            'mhcs-mpips-integration-v1',
            'PRODUCTION_REVISION',
            'service_image=',
            'container_image=',
            'version_current=',
            'consistent=',
            'EXPECTED_REVISION=',
            'REVISION_MATCH=',
            '/var/www/html/VERSION-CURRENT',
        ] as $observation) {
            $this->assertStringContainsString($observation, $workflow);
        }

        $this->assertStringContainsString('if [ -n "$EXPECTED_REVISION" ]; then', $workflow);
        $this->assertStringContainsString('if [ "$REVISION_MATCH" != "true" ]; then', $workflow);
        $this->assertStringNotContainsString('GITHUB_SHA', $workflow);

        $this->assertStringContainsString('local_ingress_http_status=', $workflow);
        $this->assertStringContainsString('http://127.0.0.1:8013/up', $workflow);
        $this->assertStringContainsString('LARAVEL_BOOTSTRAP=pass', $workflow);
        $this->assertStringContainsString('DATABASE_READ_ONLY_QUERY=pass', $workflow);
        $this->assertStringContainsString('select 1', $workflow);

        $this->assertStringContainsString('RUN_LARGE_UPLOAD_PROBE', $workflow);
        $this->assertStringContainsString('if [ "$RUN_LARGE_UPLOAD_PROBE" = "true" ]; then', $workflow);
        $this->assertStringContainsString('required_bytes=$((100 * 1024 * 1024))', $workflow);
        $this->assertStringContainsString('truncate -s "$required_bytes" "$probe_file"', $workflow);
        $this->assertStringContainsString("trap 'rm -f -- \"\$probe_file\"' EXIT INT TERM", $workflow);
        $this->assertStringContainsString('type=application/octet-stream', $workflow);
        $this->assertStringContainsString('uploaded_bytes', $workflow);
        $this->assertStringContainsString('HTTP 405', $workflow);
        $this->assertStringContainsString('HTTP 413', $workflow);
        $this->assertStringContainsString('UPLOAD_PROBE=skipped', $workflow);

        $this->assertStringContainsString('VERIFY_PRESTIGE', $workflow);
        $this->assertStringContainsString('if [ "$VERIFY_PRESTIGE" = "true" ]; then', $workflow);
        foreach ([
            'site_active',
            'schedule_count',
            'schedule_bounds_match',
            'quota_27',
            'quota_28',
            'confirmed_27',
            'confirmed_28',
            'total_bookings',
            'distinct_members',
            'member_sets_equal',
            'verification_passed',
            '2026-08-26 17:00:00',
            '2026-08-27 17:00:00',
            '2026-08-28 17:00:00',
            '->pluck("member_id")',
            'PRESTIGE_VERIFICATION=skipped',
        ] as $prestigeCheck) {
            $this->assertStringContainsString($prestigeCheck, $workflow);
        }

        $lowerWorkflow = strtolower($workflow);
        foreach ([
            'php artisan',
            'migrate',
            'db:seed',
            'docker service update',
            'docker stack deploy',
            'docker compose up',
            'docker compose down',
            'docker restart',
            'inputs.command',
            'inputs.seeder',
            'inputs.csv',
        ] as $forbiddenOperation) {
            $this->assertStringNotContainsString($forbiddenOperation, $lowerWorkflow);
        }

        $this->assertStringNotContainsString('member_id" =>', $workflow);
        $this->assertStringNotContainsString('member_ids', strtolower($workflow));
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        $this->assertStringContainsString('FAIL=', $workflow);
        $this->assertStringContainsString('exit 1', $workflow);
    }
}
