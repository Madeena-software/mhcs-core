<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionPrivateDocumentVerificationWorkflowTest extends TestCase
{
    public function test_private_document_diagnostic_status_boundaries_fail_closed(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/verify-production-private-documents.yml'));
        $this->assertIsString($workflow);

        foreach ([
            'laravel_bootstrap=PASS',
            'laravel_bootstrap=FAIL',
            'database_read_status=PASS',
            'database_read_status=FAIL',
            'diagnostic_execution=PASS',
            'diagnostic_execution=FAIL',
            'diagnostic_failure_boundary=laravel_bootstrap',
            'diagnostic_failure_boundary=database_read',
        ] as $status) {
            $this->assertStringContainsString($status, $workflow);
        }

        $bootstrapFailure = strpos($workflow, 'diagnostic_failure_boundary=laravel_bootstrap');
        $dbQuery = strpos($workflow, "DB::table('examination_consents')");
        $headBucket = strpos($workflow, '->headBucket(');
        $this->assertNotFalse($bootstrapFailure);
        $this->assertNotFalse($dbQuery);
        $this->assertNotFalse($headBucket);
        $this->assertLessThan($dbQuery, $bootstrapFailure);
        $this->assertLessThan($headBucket, $bootstrapFailure);
        $this->assertStringContainsString('if (! $bootstrapOk) {', $workflow);
        $this->assertStringContainsString('if ($dbQueryFailed) {', $workflow);

        $databaseFailure = strpos($workflow, 'diagnostic_failure_boundary=database_read');
        $this->assertNotFalse($databaseFailure);
        $this->assertLessThan($headBucket, $databaseFailure);
        $this->assertStringContainsString('exit(1);', $workflow);
    }

    public function test_private_document_diagnostic_classifies_only_observed_linkage_failures(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/verify-production-private-documents.yml'));
        $this->assertIsString($workflow);

        $this->assertStringContainsString('bool $databaseReadSucceeded,', $workflow);
        $this->assertStringContainsString("return 'diagnostic_execution_failed';", $workflow);
        $this->assertStringNotContainsString("if (! \$selectionSucceeded) {\n                  return 'db_linkage_incomplete';", $workflow);

        $databasePass = strpos($workflow, 'database_read_status=PASS');
        $headBucket = strpos($workflow, '->headBucket(');
        $diagnosticPass = strpos($workflow, 'diagnostic_execution=PASS');
        $this->assertNotFalse($databasePass);
        $this->assertNotFalse($headBucket);
        $this->assertNotFalse($diagnosticPass);
        $this->assertLessThan($headBucket, $databasePass);
        $this->assertLessThan($diagnosticPass, $databasePass);

        $this->assertStringContainsString("return 's3_access_unavailable';", $workflow);
        $this->assertStringContainsString("\$headBucketStatus !== 'PASS'", $workflow);
    }

    public function test_private_document_verification_workflow_is_manual_fail_closed_and_read_only(): void
    {
        $workflowPath = base_path('.github/workflows/verify-production-private-documents.yml');
        $this->assertFileExists($workflowPath);

        $workflow = file_get_contents($workflowPath);
        $this->assertIsString($workflow);

        $this->assertStringContainsString("on:\n  workflow_dispatch:", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('set -euo pipefail', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'cron:', 'set -x'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }

        $revision = 'b6232a158b3f6884fd9823bc875abc432676b781';
        $this->assertStringContainsString($revision, $workflow);
        foreach (['mhcs_core_app', '/var/www/html/VERSION-CURRENT', 'SERVICE_REVISION', 'CONTAINER_REVISION', 'EXPECTED_REVISION'] as $guard) {
            $this->assertStringContainsString($guard, $workflow);
        }

        $revisionFailure = strpos($workflow, 'revision_match=false');
        $phpDiagnostic = strpos($workflow, 'docker exec -i "$APP_CONTAINER" php');
        $dbQuery = strpos($workflow, "DB::table('examination_consents')");
        $headBucket = strpos($workflow, '->headBucket(');
        $headObject = strpos($workflow, 'headObjectSafely($s3Client');
        $this->assertNotFalse($revisionFailure);
        $this->assertNotFalse($phpDiagnostic);
        $this->assertNotFalse($dbQuery);
        $this->assertNotFalse($headBucket);
        $this->assertNotFalse($headObject);
        $this->assertLessThan($phpDiagnostic, $revisionFailure);
        $this->assertLessThan($dbQuery, $revisionFailure);
        $this->assertLessThan($headBucket, $revisionFailure);
        $this->assertLessThan($headObject, $headBucket);
        $this->assertStringContainsString('->headObject(', $workflow);
        $this->assertStringContainsString('if [ "$revision_match" != "true" ]; then', $workflow);
        $this->assertStringContainsString('exit 1', $workflow);

        $autoload = strpos($workflow, "require 'vendor/autoload.php';");
        $bootstrap = strpos($workflow, "require 'bootstrap/app.php';");
        $kernel = strpos($workflow, 'Illuminate\\Contracts\\Console\\Kernel::class');
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
            'getClient()',
            'getConfig()',
            'ContentLength',
            '.meta.json',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }

        foreach ([
            'examination_consents',
            'member_paper_questionnaires',
            'members',
            'bookings',
            'shift_schedules',
        ] as $table) {
            $this->assertStringContainsString("DB::table('$table')", $workflow);
        }

        preg_match_all("/DB::table\\('([^']+)'\\)/", $workflow, $matches);
        $tableNames = array_values(array_unique($matches[1]));
        sort($tableNames);
        $this->assertSame([
            'bookings',
            'examination_consents',
            'member_paper_questionnaires',
            'members',
            'shift_schedules',
        ], $tableNames);
        $this->assertSame(1, substr_count($workflow, "DB::table('examination_consents')"));
        $this->assertSame(1, substr_count($workflow, "DB::table('member_paper_questionnaires')"));
        $this->assertSame(2, substr_count($workflow, "->orderByDesc('created_at')"));
        $this->assertSame(2, substr_count($workflow, "->orderByDesc('id')"));

        foreach (['orderByDesc(\'created_at\')', 'orderByDesc(\'id\')', '->first()'] as $selection) {
            $this->assertStringContainsString($selection, $workflow);
        }
        $this->assertStringNotContainsString('whereNotNull', $workflow);
        $this->assertStringNotContainsString('whereNotNull(', $workflow);

        foreach ([
            'consent_record_found=',
            'consent_member_record_exists=',
            'consent_booking_record_exists=',
            'consent_booking_member_match=',
            'consent_signer_member_match=',
            'consent_object_key_present=',
            'consent_object_key_valid=',
            'consent_db_checksum_present=',
            'consent_db_checksum_valid=',
            'consent_db_bytes_valid=',
            'consent_db_format_valid=',
            'questionnaire_record_found=',
            'questionnaire_member_record_exists=',
            'questionnaire_booking_record_exists=',
            'questionnaire_booking_member_match=',
            'questionnaire_schedule_record_exists=',
            'questionnaire_booking_schedule_match=',
            'questionnaire_object_key_present=',
            'questionnaire_object_key_valid=',
            'questionnaire_db_checksum_present=',
            'questionnaire_db_checksum_valid=',
            'questionnaire_db_bytes_valid=',
            'questionnaire_db_format_valid=',
            'configured_private_s3_head_bucket=',
            'configured_private_s3_head_bucket_error_family=',
            'consent_s3_object_head=',
            'consent_s3_object_error_family=',
            'consent_s3_metadata_head=',
            'consent_s3_metadata_error_family=',
            'questionnaire_s3_object_head=',
            'questionnaire_s3_object_error_family=',
            'questionnaire_s3_metadata_head=',
            'questionnaire_s3_metadata_error_family=',
            'consent_db_bytes_match_s3=',
            'questionnaire_db_bytes_match_s3=',
            'consent_db_chain_valid=',
            'consent_s3_chain_valid=',
            'consent_complete_persistence_evidence=',
            'questionnaire_db_chain_valid=',
            'questionnaire_s3_chain_valid=',
            'questionnaire_complete_persistence_evidence=',
            'consent_interpretation=',
            'questionnaire_interpretation=',
        ] as $output) {
            $this->assertStringContainsString($output, $workflow);
        }

        foreach (['none', 'authorization', 'not_found', 'transport', 'unsupported', 'unknown', 'SKIPPED'] as $classification) {
            $this->assertStringContainsString($classification, $workflow);
        }
    }

    public function test_private_document_verification_has_no_sensitive_output_or_mutating_operation(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/verify-production-private-documents.yml'));
        $this->assertIsString($workflow);

        $lowerWorkflow = strtolower($workflow);
        foreach ([
            'getobject',
            'putobject',
            'deleteobject',
            'listobjects',
            'copyobject',
            'putbucket',
            'deletebucket',
            'insert(',
            'update(',
            'delete(',
            'truncate',
            'migrate',
            'db:seed',
            'docker service update',
            'docker stack deploy',
            'docker compose up',
            'docker compose down',
            'docker restart',
            'docker network connect',
            'docker network disconnect',
            'ssh ',
            'putenv(',
            'getenv(',
            '$_env',
            '$_server',
            'print_r(',
            'var_dump(',
            'phpinfo(',
        ] as $forbiddenOperation) {
            $this->assertStringNotContainsString($forbiddenOperation, $lowerWorkflow);
        }

        $this->assertDoesNotMatchRegularExpression(
            '/(?:echo|printf)[^\n]*(?:\$(?:memberId|bookingId|scheduleId|objectKey|bucket|endpoint|accessKey|secret|region|checksum|bytes|filename|timestamp|requestId))/i',
            $workflow,
        );

        foreach ([
            'HeadBucket',
            'HeadObject',
            'ContentLength',
            'revision_match=false',
            'revision_match=true',
            'set -euo pipefail',
        ] as $requiredSafetyMarker) {
            $this->assertStringContainsString($requiredSafetyMarker, $workflow);
        }

        $this->assertStringNotContainsString('.github/workflows/diagnose-production-s3.yml', $workflow);
    }
}
