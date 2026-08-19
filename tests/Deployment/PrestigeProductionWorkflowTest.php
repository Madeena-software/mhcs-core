<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class PrestigeProductionWorkflowTest extends TestCase
{
    public function test_prestige_production_workflow_is_manual_fail_closed_and_sanitized(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/apply-prestige-production-data.yml'));

        $this->assertIsString($workflow);
        $normalizedWorkflow = preg_replace('/\s+/', ' ', $workflow);
        $this->assertIsString($normalizedWorkflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringNotContainsString('push:', $workflow);
        $this->assertStringNotContainsString('pull_request:', $workflow);
        $this->assertStringNotContainsString('schedule:', $workflow);
        $this->assertStringNotContainsString('cron:', $workflow);

        $this->assertStringContainsString('confirmation:', $workflow);
        $this->assertStringContainsString('required: true', $workflow);
        $this->assertStringContainsString('APPLY-PRESTIGE-2026-08-27-28', $workflow);
        $this->assertStringContainsString('production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('needs: confirm', $workflow);

        $revisionGate = strpos($workflow, 'Verify exact production revision');
        $backupGate = strpos($workflow, 'Run established verified production database backup');
        $seedCommand = strpos($workflow, "php artisan db:seed --class='Database\\Seeders\\PrestigeClinicSeeder' --force");

        $this->assertNotFalse($revisionGate);
        $this->assertNotFalse($backupGate);
        $this->assertNotFalse($seedCommand);
        $this->assertLessThan($backupGate, $revisionGate);
        $this->assertLessThan($seedCommand, $backupGate);
        $this->assertSame(1, substr_count($workflow, "--class='Database\\Seeders\\PrestigeClinicSeeder'"));
        $this->assertStringContainsString('sudo -S', $workflow);
        $this->assertStringContainsString('BACKUP_VERIFIED reference=', $workflow);
        $this->assertStringContainsString('database backup failed; seeding blocked', $workflow);

        $this->assertStringContainsString('4488f37787bc521869a2bb6113507387c5a983c8', $workflow);
        $this->assertStringContainsString('mhcs_core_app', $workflow);
        $this->assertStringContainsString('VERSION-CURRENT', $workflow);
        $this->assertStringContainsString('healthy', $workflow);

        $this->assertStringContainsString('/etc/madeena-mhcs_core-prestige-employee.csv', $workflow);
        $this->assertStringContainsString('umask 077', $workflow);
        $this->assertStringContainsString('chmod 600', $workflow);
        $this->assertStringContainsString('PRESTIGE_EMPLOYEE_CSV=/tmp/mhcs-prestige-employee.csv', $workflow);
        $this->assertStringContainsString('docker cp', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" \\ chown www-data:www-data "$CONTAINER_CSV"', $normalizedWorkflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" \\ chmod 600 "$CONTAINER_CSV"', $normalizedWorkflow);
        $this->assertStringContainsString('CONTAINER_CSV_OWNER="$(docker exec "$APP_CONTAINER" stat -c \'%U\' "$CONTAINER_CSV"', $workflow);
        $this->assertStringContainsString('[ "$CONTAINER_CSV_OWNER" != "www-data" ]', $workflow);
        $this->assertStringContainsString('CONTAINER_CSV_MODE="$(docker exec "$APP_CONTAINER" stat -c \'%a\' "$CONTAINER_CSV"', $workflow);
        $this->assertStringContainsString('[ "$CONTAINER_CSV_MODE" != "600" ]', $workflow);
        $this->assertStringContainsString('trap cleanup EXIT', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" rm -f -- "$CONTAINER_CSV"', $workflow);
        $this->assertStringNotContainsString('$GITHUB_WORKSPACE', $workflow);

        $this->assertStringContainsString('MHCS_ALLOW_PRODUCTION_MVP_SEED=true', $workflow);
        $this->assertSame(1, substr_count($workflow, 'MHCS_ALLOW_PRODUCTION_MVP_SEED=true'));
        $seedExecStart = strrpos(substr($workflow, 0, $seedCommand), 'docker exec');
        $this->assertNotFalse($seedExecStart);
        $seedInvocation = substr($workflow, $seedExecStart, $seedCommand + strlen("php artisan db:seed --class='Database\\Seeders\\PrestigeClinicSeeder' --force") - $seedExecStart);
        $this->assertStringNotContainsString('-u 0', $seedInvocation);
        $this->assertStringNotContainsString('inputs.seeder', $workflow);
        $this->assertStringNotContainsString('inputs.csv', $workflow);
        $this->assertStringNotContainsString('inputs.command', $workflow);

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
        ] as $invariant) {
            $this->assertStringContainsString($invariant, $workflow);
        }

        $this->assertStringContainsString('where("status", "confirmed")', $workflow);
        $this->assertStringContainsString('pluck("member_id")', $workflow);
        $this->assertStringContainsString('sort(SORT_STRING)', $workflow);
        $this->assertStringContainsString('verification_passed', $workflow);
    }
}
