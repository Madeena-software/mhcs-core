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
        $this->assertSame(1, substr_count($workflow, 'environment: production'));
        $this->assertStringContainsString('PRESTIGE_EMPLOYEE_CSV: ${{ secrets.PRESTIGE_EMPLOYEE_CSV }}', $workflow);
        $this->assertStringContainsString('PRESTIGE_OPERATOR_CREDENTIALS: ${{ secrets.PRESTIGE_OPERATOR_CREDENTIALS }}', $workflow);
        $this->assertStringNotContainsString('inputs.secret', $workflow);
        $this->assertStringNotContainsString('inputs.operator', $workflow);

        $revisionGate = strpos($workflow, 'Verify exact production revision');
        $csvStage = strpos($workflow, 'Stage protected Environment CSV privately');
        $csvValidation = strpos($workflow, 'CSV_VALIDATED rows=');
        $operatorStage = strpos($workflow, 'Stage protected Prestige Operator credentials privately');
        $operatorValidation = strpos($workflow, 'FILTER_VALIDATE_EMAIL');
        $adminPrecheck = strpos($workflow, 'Check required production administrator credentials');
        $containerOperatorStage = strpos($workflow, 'Stage protected Operator credentials in the application container');
        $employeeContainerCopy = strpos($workflow, 'docker cp "$HOST_CSV" "$APP_CONTAINER:$CONTAINER_CSV"');
        $operatorContainerCopy = strpos($workflow, 'docker cp "$HOST_OPERATOR_CREDENTIALS" "$APP_CONTAINER:$CONTAINER_OPERATOR_CREDENTIALS"');
        $operatorDestinationCheck = strpos($workflow, 'OPERATOR_PATH_STATE=');
        $operatorCleanupActivation = strpos($workflow, 'CONTAINER_OPERATOR_STAGED=1');
        $backupGate = strpos($workflow, 'Run established verified production database backup');
        $seedCommand = strpos($workflow, "php artisan db:seed --class='Database\\Seeders\\PrestigeClinicSeeder' --force");
        $bootstrapValidation = strpos($workflow, 'Verify temporary bootstrap credential output');
        $bootstrapExpected = strpos($workflow, 'BOOTSTRAP_CREDENTIAL_EXPECTED=1');
        $seedStatus = strpos($workflow, 'SEED_STATUS=success');

        $this->assertNotFalse($revisionGate);
        $this->assertNotFalse($csvStage);
        $this->assertNotFalse($csvValidation);
        $this->assertNotFalse($operatorStage);
        $this->assertNotFalse($operatorValidation);
        $this->assertNotFalse($adminPrecheck);
        $this->assertNotFalse($containerOperatorStage);
        $this->assertNotFalse($employeeContainerCopy);
        $this->assertNotFalse($operatorContainerCopy);
        $this->assertNotFalse($operatorDestinationCheck);
        $this->assertNotFalse($operatorCleanupActivation);
        $this->assertNotFalse($backupGate);
        $this->assertNotFalse($seedCommand);
        $this->assertNotFalse($bootstrapValidation);
        $this->assertNotFalse($bootstrapExpected);
        $this->assertNotFalse($seedStatus);
        $this->assertLessThan($csvStage, $revisionGate);
        $this->assertLessThan($operatorStage, $csvStage);
        $this->assertLessThan($operatorValidation, $csvValidation);
        $this->assertLessThan($adminPrecheck, $operatorStage);
        $this->assertLessThan($containerOperatorStage, $adminPrecheck);
        $this->assertLessThan($employeeContainerCopy, $adminPrecheck);
        $this->assertLessThan($operatorContainerCopy, $adminPrecheck);
        $this->assertLessThan($backupGate, $employeeContainerCopy);
        $this->assertLessThan($backupGate, $operatorContainerCopy);
        $this->assertLessThan($backupGate, $containerOperatorStage);
        $this->assertLessThan($backupGate, $revisionGate);
        $this->assertLessThan($seedCommand, $backupGate);
        $this->assertLessThan($operatorContainerCopy, $operatorCleanupActivation);
        $this->assertLessThan($operatorCleanupActivation, $operatorDestinationCheck);
        $this->assertLessThan($seedCommand, $bootstrapExpected);
        $this->assertLessThan($bootstrapValidation, $seedCommand);
        $this->assertLessThan($seedStatus, $bootstrapValidation);
        $this->assertSame(1, substr_count($workflow, "--class='Database\\Seeders\\PrestigeClinicSeeder'"));
        $this->assertStringContainsString('sudo -S', $workflow);
        $this->assertStringContainsString('BACKUP_VERIFIED reference=', $workflow);
        $this->assertStringContainsString('database backup failed; seeding blocked', $workflow);

        $this->assertStringContainsString('EXPECTED_REVISION="4488f37787bc521869a2bb6113507387c5a983c8"', $workflow);
        $this->assertSame(1, substr_count($workflow, 'EXPECTED_REVISION="4488f37787bc521869a2bb6113507387c5a983c8"'));
        $this->assertStringContainsString('mhcs_core_app', $workflow);
        $this->assertStringContainsString('VERSION-CURRENT', $workflow);
        $this->assertStringContainsString('healthy', $workflow);

        $this->assertStringNotContainsString('/etc/madeena-mhcs_core-prestige-employee.csv', $workflow);
        $this->assertStringNotContainsString('PRESTIGE_SOURCE', $workflow);
        $this->assertStringNotContainsString('protected runner-host CSV transport', $workflow);
        $this->assertStringNotContainsString('_sudo cat', $workflow);
        $this->assertStringContainsString('umask 077', $workflow);
        $this->assertStringContainsString('chmod 600', $workflow);
        $this->assertStringContainsString('PRESTIGE_EMPLOYEE_CSV=/tmp/mhcs-prestige-employee.csv', $workflow);
        $this->assertStringContainsString('if [ -z "${PRESTIGE_EMPLOYEE_CSV:-}" ]; then', $workflow);
        $this->assertStringContainsString('HOST_CSV="$(mktemp /tmp/mhcs-prestige-csv.XXXXXX)"', $workflow);
        $this->assertStringContainsString('printf \'%s\' "$PRESTIGE_EMPLOYEE_CSV" >"$HOST_CSV"', $workflow);
        $this->assertStringContainsString('unset PRESTIGE_EMPLOYEE_CSV', $workflow);
        $this->assertStringContainsString('[ ! -f "$HOST_CSV" ] || [ -L "$HOST_CSV" ]', $workflow);
        $this->assertStringContainsString('HOST_CSV_MODE="$(stat -c \'%a\' "$HOST_CSV"', $workflow);
        $this->assertStringContainsString('[ "$HOST_CSV_MODE" != "600" ]', $workflow);
        $this->assertStringContainsString('HOST_CSV_SIZE="$(stat -c \'%s\' "$HOST_CSV"', $workflow);
        $this->assertStringContainsString('[ -z "$HOST_CSV_SIZE" ] || [ "$HOST_CSV_SIZE" -le 0 ]', $workflow);
        $this->assertStringNotContainsString('echo "$PRESTIGE_EMPLOYEE_CSV"', $workflow);
        $this->assertStringNotContainsString('cat "$PRESTIGE_EMPLOYEE_CSV"', $workflow);
        $secretWrite = strpos($workflow, 'printf \'%s\' "$PRESTIGE_EMPLOYEE_CSV" >"$HOST_CSV"');
        $secretUnset = strpos($workflow, 'unset PRESTIGE_EMPLOYEE_CSV');
        $this->assertNotFalse($secretWrite);
        $this->assertNotFalse($secretUnset);
        $this->assertLessThan($secretUnset, $secretWrite);
        $this->assertStringContainsString('docker cp', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" \\ chown www-data:www-data "$CONTAINER_CSV"', $normalizedWorkflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" \\ chmod 600 "$CONTAINER_CSV"', $normalizedWorkflow);
        $this->assertStringContainsString('CONTAINER_CSV_OWNER="$(docker exec "$APP_CONTAINER" stat -c \'%U\' "$CONTAINER_CSV"', $workflow);
        $this->assertStringContainsString('[ "$CONTAINER_CSV_OWNER" != "www-data" ]', $workflow);
        $this->assertStringContainsString('CONTAINER_CSV_MODE="$(docker exec "$APP_CONTAINER" stat -c \'%a\' "$CONTAINER_CSV"', $workflow);
        $this->assertStringContainsString('[ "$CONTAINER_CSV_MODE" != "600" ]', $workflow);

        $this->assertStringContainsString('if [ -z "${PRESTIGE_OPERATOR_CREDENTIALS:-}" ]; then', $workflow);
        $this->assertStringContainsString('HOST_OPERATOR_CREDENTIALS="$(mktemp /tmp/mhcs-prestige-operator.XXXXXX)"', $workflow);
        $this->assertStringContainsString('chmod 600 "$HOST_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('printf \'%s\' "$PRESTIGE_OPERATOR_CREDENTIALS" >"$HOST_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('unset PRESTIGE_OPERATOR_CREDENTIALS', $workflow);
        $operatorSecretWrite = strpos($workflow, 'printf \'%s\' "$PRESTIGE_OPERATOR_CREDENTIALS" >"$HOST_OPERATOR_CREDENTIALS"');
        $operatorSecretUnset = strpos($workflow, 'unset PRESTIGE_OPERATOR_CREDENTIALS');
        $this->assertNotFalse($operatorSecretWrite);
        $this->assertNotFalse($operatorSecretUnset);
        $this->assertLessThan($operatorSecretUnset, $operatorSecretWrite);
        $this->assertStringContainsString('[ ! -f "$HOST_OPERATOR_CREDENTIALS" ] || [ -L "$HOST_OPERATOR_CREDENTIALS" ]', $workflow);
        $this->assertStringContainsString('HOST_OPERATOR_MODE="$(stat -c \'%a\' "$HOST_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('[ "$HOST_OPERATOR_MODE" != "600" ]', $workflow);
        $this->assertStringContainsString('HOST_OPERATOR_SIZE="$(stat -c \'%s\' "$HOST_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('[ -z "$HOST_OPERATOR_SIZE" ] || [ "$HOST_OPERATOR_SIZE" -le 0 ]', $workflow);
        $this->assertStringContainsString('FILTER_VALIDATE_EMAIL', $workflow);
        $this->assertStringContainsString('fgets(STDIN)', $workflow);
        $this->assertStringContainsString('str_starts_with($line, "password:")', $workflow);
        $this->assertStringContainsString('FATAL: protected Prestige Operator credential structure validation failed.', $workflow);
        $this->assertStringNotContainsString('echo "$PRESTIGE_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringNotContainsString('cat "$PRESTIGE_OPERATOR_CREDENTIALS"', $workflow);

        $this->assertStringContainsString('getenv("SUPER_ADMIN_EMAIL")', $workflow);
        $this->assertStringContainsString('getenv("SUPER_ADMIN_PASSWORD")', $workflow);
        $this->assertStringContainsString('FATAL: required production administrator credentials are unavailable.', $workflow);
        $this->assertStringNotContainsString('mvp-admin@example.test', $workflow);
        $this->assertStringNotContainsString('madeenaadmin', $workflow);

        $this->assertStringContainsString('CONTAINER_OPERATOR_CREDENTIALS="/var/www/html/research/prestige/operator.txt"', $workflow);
        $this->assertStringContainsString('OPERATOR_PATH_STATE', $workflow);
        $this->assertStringContainsString('printf existing', $workflow);
        $this->assertStringContainsString('printf symlink', $workflow);
        $this->assertStringContainsString('FATAL: unexpected existing Prestige Operator credential file.', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" mkdir -p -- "$OPERATOR_DIRECTORY"', $workflow);
        $this->assertStringContainsString('docker cp "$HOST_OPERATOR_CREDENTIALS" "$APP_CONTAINER:$CONTAINER_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('CONTAINER_OPERATOR_STAGED=1', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" \\ chown www-data:www-data "$CONTAINER_OPERATOR_CREDENTIALS"', $normalizedWorkflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" \\ chmod 600 "$CONTAINER_OPERATOR_CREDENTIALS"', $normalizedWorkflow);
        $this->assertStringContainsString('CONTAINER_OPERATOR_OWNER="$(docker exec "$APP_CONTAINER" stat -c \'%U\' "$CONTAINER_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('CONTAINER_OPERATOR_GROUP="$(docker exec "$APP_CONTAINER" stat -c \'%G\' "$CONTAINER_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('[ "$CONTAINER_OPERATOR_OWNER" != "www-data" ] || [ "$CONTAINER_OPERATOR_GROUP" != "www-data" ]', $workflow);
        $this->assertStringContainsString('CONTAINER_OPERATOR_MODE="$(docker exec "$APP_CONTAINER" stat -c \'%a\' "$CONTAINER_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('[ "$CONTAINER_OPERATOR_MODE" != "600" ]', $workflow);
        $this->assertStringNotContainsString('PRESTIGE_OPERATOR_CREDENTIALS=/tmp', $workflow);

        $this->assertStringContainsString('BOOTSTRAP_CREDENTIAL_PATH="/tmp/mhcs-prestige-bootstrap-credentials.txt"', $workflow);
        $this->assertStringContainsString('BOOTSTRAP_PATH_STATE', $workflow);
        $this->assertStringContainsString('FATAL: unexpected existing bootstrap credential output file.', $workflow);
        $this->assertStringContainsString('MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt', $workflow);
        $this->assertStringContainsString('BOOTSTRAP_CREDENTIAL_EXPECTED=1', $workflow);
        $this->assertStringContainsString('Verify temporary bootstrap credential output', $workflow);
        $this->assertStringContainsString('BOOTSTRAP_OWNER', $workflow);
        $this->assertStringContainsString('BOOTSTRAP_MODE', $workflow);
        $this->assertStringContainsString('BOOTSTRAP_SIZE', $workflow);
        $this->assertStringContainsString('BOOTSTRAP_CREDENTIAL_OUTPUT_VERIFIED', $workflow);
        $this->assertStringNotContainsString('cat "$BOOTSTRAP_CREDENTIAL_PATH"', $workflow);
        $this->assertStringNotContainsString('docker cp "$BOOTSTRAP_CREDENTIAL_PATH"', $workflow);

        $this->assertStringContainsString('trap cleanup EXIT', $workflow);
        $this->assertStringContainsString('rm -f -- "$HOST_CSV"', $workflow);
        $this->assertStringContainsString('rm -f -- "$HOST_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" rm -f -- "$CONTAINER_CSV"', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" rm -f -- "$CONTAINER_OPERATOR_CREDENTIALS"', $workflow);
        $this->assertStringContainsString('docker exec -u 0 "$APP_CONTAINER" rm -f -- "$BOOTSTRAP_CREDENTIAL_PATH"', $workflow);
        $this->assertStringNotContainsString('$GITHUB_WORKSPACE', $workflow);

        $this->assertStringContainsString('MHCS_ALLOW_PRODUCTION_MVP_SEED=true', $workflow);
        $this->assertSame(1, substr_count($workflow, 'MHCS_ALLOW_PRODUCTION_MVP_SEED=true'));
        $seedExecStart = strpos($workflow, "docker exec \\\n            -e MHCS_ALLOW_PRODUCTION_MVP_SEED=true");
        $this->assertNotFalse($seedExecStart);
        $seedInvocation = substr($workflow, $seedExecStart, $seedCommand + strlen("php artisan db:seed --class='Database\\Seeders\\PrestigeClinicSeeder' --force") - $seedExecStart);
        $this->assertStringNotContainsString('-u 0', $seedInvocation);
        $this->assertSame(3, substr_count($seedInvocation, '-e '));
        $this->assertStringContainsString('-e MHCS_BOOTSTRAP_CREDENTIAL_PATH=/tmp/mhcs-prestige-bootstrap-credentials.txt', $seedInvocation);
        $this->assertStringNotContainsString('PRESTIGE_OPERATOR_CREDENTIALS', $seedInvocation);
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
