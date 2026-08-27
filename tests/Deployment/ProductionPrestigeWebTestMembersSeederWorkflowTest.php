<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionPrestigeWebTestMembersSeederWorkflowTest extends TestCase
{
    public function test_workflow_is_dedicated_and_fail_closed(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/seed-production-prestige-web-test-members.yml'));
        $this->assertIsString($workflow);
        foreach (['name: Seed production Prestige web-test Members', 'workflow_dispatch:', 'expected_application_revision:', 'authorization_marker:', 'contents: read', 'group: production-deployment-mhcs_core', 'cancel-in-progress: false', 'runs-on: self-hosted', 'timeout-minutes: 10', 'AUTHORIZE_ONE_PRESTIGE_WEB_TEST_MEMBER_SEED', 'service ls', 'desired-state=running', 'com.docker.swarm.task.id', 'Spec.TaskTemplate.ContainerSpec.Image', '{{.Config.Image}}', 'VERSION-CURRENT', 'State.Health.Status', 'pre-mutation ABSENT probe', 'MHCS_ALLOW_PRODUCTION_MVP_SEED=true', 'JAD-NPZ-0827', 'JAD-NPZ-0828', 'where("quota", "!=", 50)', 'diagnostic_booking_count=4', 'additional_web_test_member_count=2'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $this->assertStringContainsString('[[ "$EXPECTED_APPLICATION_REVISION" =~ ^[0-9a-f]{40}$ ]]', $workflow);
        $this->assertSame(1, substr_count($workflow, 'docker exec -e MHCS_ALLOW_PRODUCTION_MVP_SEED=true'));
        $this->assertSame(1, substr_count($workflow, "php artisan db:seed --class='Database\\Seeders\\PrestigeWebTestMembersSeeder' --force"));
        foreach (['PrestigeClinicSeeder', 'PRESTIGE_EMPLOYEE_CSV', 'docker service update', 'docker service scale', 'retry', 'restart'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($workflow));
        }
    }

    public function test_canonical_prestige_workflow_matches_accepted_baseline(): void
    {
        $this->assertSame('', trim((string) shell_exec('git diff 2361ff074e31b3c70419ab21a1710fec024ff5d3..HEAD -- .github/workflows/apply-prestige-production-data.yml')));
    }
}
