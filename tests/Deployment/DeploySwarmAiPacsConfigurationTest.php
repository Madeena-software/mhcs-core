<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class DeploySwarmAiPacsConfigurationTest extends TestCase
{
    public function test_deploy_swarm_workflow_propagates_and_asserts_ai_pacs_configuration(): void
    {
        $workflowPath = base_path('.github/workflows/deploy-swarm.yml');
        $this->assertFileExists($workflowPath);

        $content = file_get_contents($workflowPath);
        $this->assertIsString($content);

        // 1. Secrets binding in deployment step env
        $this->assertStringContainsString('AI_PACS_USERNAME: ${{ secrets.AI_PACS_USERNAME }}', $content);
        $this->assertStringContainsString('AI_PACS_PASSWORD: ${{ secrets.AI_PACS_PASSWORD }}', $content);
        $this->assertStringContainsString('AI_PACS_URL: ${{ secrets.AI_PACS_URL }}', $content);

        // 2. Inclusion in require_env check loop
        $this->assertMatchesRegularExpression('/for var in [^;]*AI_PACS_USERNAME[^;]*AI_PACS_PASSWORD[^;]*AI_PACS_URL[^;]*; do\s+require_env "\$var"/m', $content);

        // 3. Writing to production .env with safe printf
        $this->assertStringContainsString("printf 'AI_PACS_URL=%s\\n' \"\$AI_PACS_URL\"", $content);
        $this->assertStringContainsString("printf 'AI_PACS_USERNAME=%s\\n' \"\$AI_PACS_USERNAME\"", $content);
        $this->assertStringContainsString("printf 'AI_PACS_PASSWORD=%s\\n' \"\$AI_PACS_PASSWORD\"", $content);

        // 4. Runtime configuration assertion confirms non-empty values through config
        $this->assertStringContainsString('assert(filled(config("services.ai_pacs.base_url")), "AI_PACS_URL must be configured");', $content);
        $this->assertStringContainsString('assert(filled(config("services.ai_pacs.username")), "AI_PACS_USERNAME must be configured");', $content);
        $this->assertStringContainsString('assert(filled(config("services.ai_pacs.password")), "AI_PACS_PASSWORD must be configured");', $content);

        // 5. Ensure secret values are not echoed or exposed
        $this->assertStringNotContainsString('echo "$AI_PACS_PASSWORD"', $content);
        $this->assertStringNotContainsString('echo "$AI_PACS_USERNAME"', $content);
        $this->assertStringNotContainsString('echo "$AI_PACS_URL"', $content);
    }
}
