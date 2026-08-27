<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionPrestigeWebTestMembersSeederWorkflowTest extends TestCase
{
    public function test_workflow_is_dedicated_fixed_target_and_fail_closed(): void
    {
        $path = base_path('.github/workflows/seed-production-prestige-web-test-members.yml');
        $workflow = file_get_contents($path);

        $this->assertIsString($workflow);
        $this->assertStringContainsString('name: Seed production Prestige web-test Members', $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringNotContainsString('push:', $workflow);
        $this->assertStringNotContainsString('pull_request:', $workflow);
        $this->assertStringContainsString('permissions:', $workflow);
        $this->assertStringContainsString('contents: read', $workflow);
        $this->assertStringContainsString('group: production-deployment-mhcs_core', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString('timeout-minutes: 10', $workflow);
        $this->assertStringContainsString('AUTHORIZE_ONE_PRESTIGE_WEB_TEST_MEMBER_SEED', $workflow);
        $this->assertStringContainsString('EXPECTED_REVISION="2361ff074e31b3c70419ab21a1710fec024ff5d3"', $workflow);
        $this->assertStringContainsString('service ls', $workflow);
        $this->assertStringContainsString('desired-state=running', $workflow);
        $this->assertStringContainsString('com.docker.swarm.task.id', $workflow);
        $this->assertStringContainsString('Spec.TaskTemplate.ContainerSpec.Image', $workflow);
        $this->assertStringContainsString('{{.Config.Image}}', $workflow);
        $this->assertStringContainsString('VERSION-CURRENT', $workflow);
        $this->assertStringContainsString('State.Health.Status', $workflow);
        $this->assertStringContainsString('pre-mutation ABSENT probe', $workflow);
        $this->assertStringContainsString('JAD-PRES-NPZ-TEST', $workflow);
        $this->assertSame(1, substr_count($workflow, "php artisan db:seed --class='Database\\Seeders\\PrestigeWebTestMembersSeeder' --force"));
        $this->assertSame(1, substr_count($workflow, 'MHCS_ALLOW_PRODUCTION_MVP_SEED=true'));
        $this->assertStringNotContainsString('PrestigeClinicSeeder', $workflow);
        $this->assertStringNotContainsString('PRESTIGE_EMPLOYEE_CSV', $workflow);
        $this->assertStringNotContainsString('retry', strtolower($workflow));
        $this->assertStringNotContainsString('docker service update', $workflow);
        $this->assertStringNotContainsString('docker service scale', $workflow);
        $this->assertStringNotContainsString('restart', strtolower($workflow));
        foreach (['canonical_prestige_member_count=37', 'additional_web_test_member_count=2', 'fixture_credit_count=2', 'fixture_charge_count=2', 'operational_progression_present=false', 'seeding=PASS'] as $evidence) {
            $this->assertStringContainsString($evidence, $workflow);
        }
        foreach (['operator_arrivals', 'operator_identity_verifications', 'examination_consents', 'operator_paper_tickets', 'operator_queue_admissions', 'member_vital_signs_assessments', 'operator_vital_signs_executions', 'member_paper_questionnaires', 'image_gateway_capture_sets', 'image_gateway_studies', 'local_imaging_orders'] as $table) {
            $this->assertStringContainsString('"'.$table.'"', $workflow);
        }
    }

    public function test_canonical_prestige_workflow_is_not_modified_by_this_task(): void
    {
        $this->assertSame('', trim((string) shell_exec('git diff -- .github/workflows/apply-prestige-production-data.yml')));
    }
}
