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
            'verify_prestige_members:',
        ] as $input) {
            $this->assertStringContainsString($input, $workflow);
        }

        $this->assertStringContainsString('required: false', $workflow);
        $this->assertStringContainsString('default: false', $workflow);
        $this->assertStringContainsString('type: string', $workflow);
        $this->assertStringContainsString('type: boolean', $workflow);
        $this->assertStringContainsString(
            "      verify_prestige_members:\n        description: Run sanitized read-only Prestige Member-account checks.\n        required: false\n        default: false\n        type: boolean",
            $workflow,
        );
        $this->assertStringContainsString(
            "      run_large_upload_probe:\n        description: Run the generated 100 MiB local ingress probe.\n        required: false\n        default: false\n        type: boolean",
            $workflow,
        );
        $this->assertStringContainsString(
            "      verify_prestige:\n        description: Run sanitized read-only Prestige invariant checks.\n        required: false\n        default: false\n        type: boolean",
            $workflow,
        );
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
            'quota_20_26',
            'quota_27',
            'quota_28',
            'confirmed_20_26',
            'confirmed_27',
            'confirmed_28',
            'total_bookings',
            'distinct_members',
            'member_sets_equal',
            'verification_passed',
            '2026-08-19 17:00:00',
            '2026-08-26 17:00:00',
            '2026-08-27 17:00:00',
            '2026-08-28 17:00:00',
            '$quotaValues === [37, 37, 37]',
            '$confirmedCounts === [37, 37, 37]',
            '$totalBookings = (int) $db::table("bookings")',
            '$totalBookings === 111',
            '$memberSets[0] === $memberSets[1]',
            '$memberSets[1] === $memberSets[2]',
            'count(array_unique($memberSets[2])) === 37',
            '->pluck("member_id")',
            'PRESTIGE_VERIFICATION=skipped',
        ] as $prestigeCheck) {
            $this->assertStringContainsString($prestigeCheck, $workflow);
        }

        $this->assertStringContainsString(
            'VERIFY_PRESTIGE_MEMBERS: ${{ inputs.verify_prestige_members }}',
            $workflow,
        );
        $memberVerificationStart = strpos($workflow, '          if [ "$VERIFY_PRESTIGE_MEMBERS" = "true" ]; then');
        $prestigeVerificationStart = strpos($workflow, '          if [ "$VERIFY_PRESTIGE" = "true" ]; then');
        $this->assertNotFalse($memberVerificationStart);
        $this->assertNotFalse($prestigeVerificationStart);
        $this->assertLessThan($prestigeVerificationStart, $memberVerificationStart);
        $memberVerificationBlock = substr($workflow, $memberVerificationStart, $prestigeVerificationStart - $memberVerificationStart);

        foreach ([
            '$db::table("users")',
            'where("email", "like", "%".$prestigeEmailSuffix)',
            '@prestige.madeena-xray.com',
            'get(["id", "account_status", "login_enabled"])',
            '$db::table("members")',
            'whereIn("user_id", $candidateUserIds)',
            'get(["user_id"])',
            '$linkedMembers->pluck("user_id")->unique()->count()',
            '$candidateUsers->every(',
            'where("account_status", "active")',
            'where("login_enabled", true)',
            '$linkageExact =',
            '$candidateUserCount === 37',
            '$linkedMemberCount === 37',
            '$distinctLinkedUserCount === 37',
            '$activeAccountCount === 37',
            '$loginEnabledAccountCount === 37',
            'prestige_user_accounts=',
            'prestige_linked_members=',
            'prestige_active_accounts=',
            'prestige_login_enabled_accounts=',
            'prestige_member_linkage_exact=',
            'PRESTIGE_MEMBER_VERIFICATION=pass',
            'PRESTIGE_MEMBER_VERIFICATION=failed',
        ] as $memberCheck) {
            $this->assertStringContainsString($memberCheck, $memberVerificationBlock);
        }

        $this->assertStringContainsString(
            "          else\n            echo \"PRESTIGE_MEMBER_VERIFICATION=skipped\"\n          fi",
            $memberVerificationBlock,
        );
        $this->assertSame(1, substr_count($memberVerificationBlock, 'PRESTIGE_MEMBER_VERIFICATION=skipped'));
        $this->assertStringNotContainsString('bookings', strtolower($memberVerificationBlock));

        foreach ([
            'insert(',
            'update(',
            'delete(',
            'upsert(',
            'create(',
            'seed',
            'migrate',
            'lockForUpdate',
            'ProtectedIdentifierService',
            'decrypt',
            'nik',
            'password',
            'hash',
        ] as $forbiddenMemberOperation) {
            $this->assertStringNotContainsString($forbiddenMemberOperation, strtolower($memberVerificationBlock));
        }

        $memberOutputStart = strpos($memberVerificationBlock, 'echo "prestige_user_accounts=');
        $this->assertNotFalse($memberOutputStart);
        $memberOutput = substr($memberVerificationBlock, $memberOutputStart);
        foreach (['nik', 'email', 'local-part', 'member_id', 'user_id', 'password', 'hash'] as $forbiddenOutput) {
            $this->assertStringNotContainsString($forbiddenOutput, strtolower($memberOutput));
        }

        $this->assertStringNotContainsString('member_id" =>', $memberVerificationBlock);
        $this->assertStringContainsString('if [ "$VERIFY_PRESTIGE" = "true" ]; then', $workflow);
        $this->assertStringContainsString('DATABASE_READ_ONLY_QUERY=pass', $workflow);

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
