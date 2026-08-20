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
            'diagnose_prestige_legacy:',
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
        foreach (['target_schedule_count', 'target_bounds_match', 'target_total_bookings', 'target_distinct_members', 'target_member_sets_equal', 'target_charge_entries', 'historical_schedule_preserved', 'historical_status_closed', 'historical_bookings', 'historical_charge_entries', 'historical_reversal_entries'] as $invariant) {
            $this->assertStringContainsString($invariant, $workflow);
        }
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
        $this->assertStringContainsString(
            "      diagnose_prestige_legacy:\n        description: Run sanitized read-only legacy Prestige schedule diagnostics.\n        required: false\n        default: false\n        type: boolean",
            $workflow,
        );
        $this->assertStringContainsString(
            'DIAGNOSE_PRESTIGE_LEGACY: ${{ inputs.diagnose_prestige_legacy }}',
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

        $diagnosticStart = strpos($workflow, '          if [ "$DIAGNOSE_PRESTIGE_LEGACY" = "true" ]; then');
        $diagnosticEnd = strpos($workflow, '          if [ "$FAIL" -gt 0 ]; then', $diagnosticStart);
        $this->assertNotFalse($diagnosticStart);
        $this->assertNotFalse($diagnosticEnd);
        $diagnosticBlock = substr($workflow, $diagnosticStart, $diagnosticEnd - $diagnosticStart);
        $this->assertStringContainsString('PRESTIGE_LEGACY_DIAGNOSTIC=skipped', $workflow);
        $this->assertStringContainsString('PRESTIGE_LEGACY_DIAGNOSTIC=pass', $diagnosticBlock);
        $this->assertStringContainsString('LEGACY_PRESTIGE_SCHEDULE ', $diagnosticBlock);
        $this->assertStringContainsString('docker exec "$APP_CONTAINER" php -r', $diagnosticBlock);
        $this->assertStringContainsString('site-prestige', $diagnosticBlock);
        $this->assertStringContainsString('PRES-01', $diagnosticBlock);
        $this->assertStringContainsString('SYN-CHEST-A', $diagnosticBlock);
        foreach ([
            'legacy_2026_08_14' => '2026-08-14 01:00:00',
            'legacy_2026_08_26' => '2026-08-26 01:00:00',
            'legacy_2026_08_27' => '2026-08-27 01:00:00',
            'legacy_2026_08_28' => '2026-08-28 01:00:00',
        ] as $label => $startsAt) {
            $this->assertStringContainsString($label, $diagnosticBlock);
            $this->assertStringContainsString($startsAt, $diagnosticBlock);
        }
        foreach ([
            'exists', 'starts_at', 'ends_at', 'quota', 'status',
            'bookings_total', 'booking_status_counts', 'distinct_members',
            'all_booked_members_in_prestige_cohort', 'member_set_has_37_unique',
            'has_bookings', 'has_point_ledger', 'has_progressed_clinical_records',
            'operator_eligible_shifts', 'operator_shift_assignments',
            'point_ledger_entries', 'point_ledger_charge_entries', 'point_ledger_reversal_entries',
        ] as $aggregate) {
            $this->assertStringContainsString('"'.$aggregate.'"', $diagnosticBlock);
        }
        foreach ([
            'legacy_schedule_count=', 'legacy_booking_count=', 'legacy_distinct_members=',
            'legacy_point_ledger_entries=', 'legacy_progressed_schedule_count=',
        ] as $summary) {
            $this->assertStringContainsString($summary, $diagnosticBlock);
        }
        foreach ([
            'local_imaging_orders',
            'operator_paper_tickets',
            'operator_queue_admissions',
            'operator_arrivals',
            'operator_identity_verifications',
            'member_paper_questionnaires',
            'member_vital_signs_assessments',
            'image_gateway_capture_sets',
        ] as $progressedTable) {
            $this->assertStringContainsString('"'.$progressedTable.'"', $diagnosticBlock);
        }
        $this->assertStringContainsString('$schedules->count() > 1', $diagnosticBlock);
        $this->assertStringContainsString('where("starts_at", $startsAt)', $diagnosticBlock);
        $this->assertStringContainsString('point_ledger_entries.booking_id', $diagnosticBlock);
        $this->assertStringContainsString('PointEntryType::Charge->value', $diagnosticBlock);
        $this->assertStringContainsString('whereNotNull("point_ledger_entries.reverses_id")', $diagnosticBlock);
        $this->assertStringNotContainsString('source_reference', $diagnosticBlock);
        foreach ([
            'insert(', 'update(', 'delete(', 'upsert(', 'create(',
            'lockForUpdate', 'seed', 'migrate', 'backup', 'php artisan',
        ] as $forbiddenDiagnosticOperation) {
            $this->assertStringNotContainsString($forbiddenDiagnosticOperation, strtolower($diagnosticBlock));
        }
        foreach (['"id"', '"member_id"', '"booking_id"', '"schedule_id"', '"user_id"'] as $forbiddenOutputField) {
            $this->assertStringNotContainsString($forbiddenOutputField.' =>', $diagnosticBlock);
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
