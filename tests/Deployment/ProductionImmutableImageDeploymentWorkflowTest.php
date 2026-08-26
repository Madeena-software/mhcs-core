<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionImmutableImageDeploymentWorkflowTest extends TestCase
{
    public function test_production_deployment_requires_and_verifies_an_immutable_application_image(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/deploy-swarm.yml'));
        $compose = file_get_contents(base_path('docker-compose.prod.yml'));

        $this->assertIsString($workflow);
        $this->assertIsString($compose);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('source_sha:', $workflow);
        $this->assertStringContainsString('image_digest:', $workflow);
        $this->assertSame(1, substr_count($workflow, 'source_sha:'));
        $this->assertSame(1, substr_count($workflow, 'image_digest:'));
        $this->assertMatchesRegularExpression('/\^\[0-9a-f\]\{40\}\$/', $workflow);
        $this->assertMatchesRegularExpression('/\^sha256:\[0-9a-f\]\{64\}\$/', $workflow);

        $this->assertStringContainsString('ghcr.io/madeena-software/mhcs-core', $workflow);
        $this->assertStringContainsString('IMAGE_REF="ghcr.io/madeena-software/mhcs-core@${IMAGE_DIGEST}"', $workflow);
        $this->assertStringContainsString('GHCR_READ_USERNAME', $workflow);
        $this->assertStringContainsString('GHCR_READ_TOKEN', $workflow);
        $this->assertStringContainsString('docker login ghcr.io -u "$GHCR_READ_USERNAME" --password-stdin', $workflow);
        $this->assertStringContainsString('docker pull "$IMAGE_REF"', $workflow);
        $this->assertSame(1, substr_count($workflow, 'PULL_MAX_ATTEMPTS=3'));
        $this->assertStringContainsString('PULL_ATTEMPT=1', $workflow);
        $this->assertStringContainsString('while true; do', $workflow);
        $this->assertStringContainsString('if [ "$PULL_ATTEMPT" -ge "$PULL_MAX_ATTEMPTS" ]; then', $workflow);
        $this->assertStringContainsString('PULL_DELAY=$((PULL_ATTEMPT * 10))', $workflow);
        $this->assertStringContainsString('sleep "$PULL_DELAY"', $workflow);
        $this->assertStringContainsString('PULL_ATTEMPT=$((PULL_ATTEMPT + 1))', $workflow);
        $this->assertStringContainsString('RepoDigests', $workflow);
        $this->assertStringContainsString('org.opencontainers.image.revision', $workflow);
        $this->assertStringContainsString('org.opencontainers.image.source', $workflow);
        $this->assertStringContainsString('docker compose -f docker-compose.prod.yml config', $workflow);
        $this->assertStringContainsString('MHCS_IMAGE="$IMAGE_REF"', $workflow);
        $this->assertStringContainsString('VERSION_CURRENT_PATH="$REMOTE_PATH/VERSION-CURRENT"', $workflow);
        $this->assertStringContainsString('[ -e "$VERSION_CURRENT_PATH" ] && [ ! -f "$VERSION_CURRENT_PATH" ]', $workflow);
        $this->assertStringContainsString(': > "$VERSION_CURRENT_PATH"', $workflow);
        $this->assertLessThan(
            strpos($workflow, 'Preparing persistent host paths'),
            strpos($workflow, 'docker pull "$IMAGE_REF"'),
        );

        foreach (['app', 'queue', 'scheduler', 'image-worker'] as $service) {
            $this->assertStringContainsString("mhcs_core_{$service}", $workflow);
        }
        $this->assertStringContainsString('--with-registry-auth', $workflow);
        $this->assertStringContainsString('docker stack deploy', $workflow);

        $pullSuccessPosition = strpos($workflow, 'Authorized immutable image pull succeeded');
        $repoDigestPosition = strpos($workflow, 'RepoDigests');
        $composePosition = strpos($workflow, 'docker compose -f docker-compose.prod.yml config');
        $deployPosition = strpos($workflow, 'docker stack deploy');
        $this->assertNotFalse($pullSuccessPosition);
        $this->assertNotFalse($repoDigestPosition);
        $this->assertNotFalse($composePosition);
        $this->assertNotFalse($deployPosition);
        $this->assertLessThan($repoDigestPosition, $pullSuccessPosition);
        $this->assertLessThan($composePosition, $repoDigestPosition);
        $this->assertLessThan($deployPosition, $composePosition);

        foreach (['docker build', 'mhcs_core:latest', 'npm ci', 'npm run build', 'composer install'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        $this->assertStringNotContainsString('needs.build', $workflow);
        $this->assertStringNotContainsString('GITHUB_SHA', $workflow);
        $this->assertStringNotContainsString('LIVE_VERSION:-unknown', $workflow);

        foreach (['app', 'queue', 'scheduler', 'image-worker'] as $service) {
            $serviceBlock = preg_match('/^  '.preg_quote($service, '/').':\n(.*?)(?=^  [a-z-]+:|\z)/ms', $compose, $matches);
            $this->assertSame(1, $serviceBlock);
            $this->assertStringContainsString('${MHCS_IMAGE:?', $matches[1]);
        }
        $this->assertStringContainsString('MHCS_RELEASE_VERSION: "${APP_VERSION:?', $compose);
        $this->assertStringNotContainsString('mhcs_core:latest', $compose);
        $this->assertStringNotContainsString('${APP_VERSION:-latest}', $compose);

        $deployPosition = strpos($workflow, 'docker stack deploy');
        $versionPosition = strpos($workflow, 'printf \'%s\\n\' "$SOURCE_SHA" > "$REMOTE_PATH/VERSION-CURRENT"');
        $this->assertNotFalse($deployPosition);
        $this->assertNotFalse($versionPosition);
        $this->assertGreaterThan($deployPosition, $versionPosition);
        $this->assertGreaterThan(strpos($workflow, 'if [ "$FAIL" -gt 0 ]; then'), $versionPosition);

        foreach (['provision-production-nonclinical-validation-context.yml', 'validate-production-real-npz-end-to-end.yml'] as $workflowName) {
            $this->assertStringNotContainsString("gh workflow run {$workflowName}", $workflow);
        }
    }
}
