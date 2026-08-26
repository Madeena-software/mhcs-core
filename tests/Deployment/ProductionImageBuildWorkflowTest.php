<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionImageBuildWorkflowTest extends TestCase
{
    public function test_production_image_build_workflow_is_immutable_and_non_production(): void
    {
        $path = base_path('.github/workflows/build-production-image.yml');
        $this->assertFileExists($path);
        $workflow = file_get_contents($path);
        $this->assertIsString($workflow);

        $this->assertStringContainsString("name: Build Production Image\n", $workflow);
        $this->assertStringContainsString("on:\n  workflow_dispatch:\n", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        foreach (["push:\n", 'pull_request:', 'schedule:', 'workflow_run:', 'repository_dispatch:'] as $trigger) {
            $this->assertStringNotContainsString($trigger, $workflow);
        }

        $this->assertMatchesRegularExpression(
            '/workflow_dispatch:\n\s+inputs:\n\s+source_sha:\n\s+description:.*\n\s+required: true\n\s+type: string/s',
            $workflow,
        );
        $this->assertSame(1, substr_count($workflow, 'source_sha:'));
        $this->assertStringContainsString('SOURCE_SHA_INPUT: ${{ inputs.source_sha }}', $workflow);
        $this->assertMatchesRegularExpression('/\^\[0-9a-f\]\{40\}\$/', $workflow);
        $this->assertStringContainsString('echo "source_sha=${SOURCE_SHA}" >> "$GITHUB_OUTPUT"', $workflow);

        $this->assertStringContainsString('runs-on: ubuntu-latest', $workflow);
        $this->assertStringNotContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read\n  packages: write\n", $workflow);
        $this->assertSame(1, substr_count($workflow, 'permissions:'));
        foreach (['actions:', 'deployments:', 'id-token:', 'administration:', 'secrets:'] as $permission) {
            $this->assertStringNotContainsString($permission, $workflow);
        }

        $this->assertStringContainsString('repository: Madeena-software/mhcs-core', $workflow);
        $this->assertStringContainsString('ref: ${{ steps.source.outputs.source_sha }}', $workflow);
        $this->assertStringContainsString('ACTUAL_SHA="$(git rev-parse HEAD)"', $workflow);
        $this->assertStringContainsString('[ "$ACTUAL_SHA" != "$SOURCE_SHA" ]', $workflow);

        $this->assertStringContainsString('ghcr.io/madeena-software/mhcs-core:', $workflow);
        $this->assertMatchesRegularExpression(
            '/tags:\s*\|\n\s+ghcr\.io\/madeena-software\/mhcs-core:\$\{\{ steps\.source\.outputs\.source_sha \}\}/',
            $workflow,
        );
        foreach ([':latest', ':main', ':master', ':production', ':stable', ':release'] as $tag) {
            $this->assertStringNotContainsString($tag, $workflow);
        }
        $this->assertStringContainsString('target: app', $workflow);
        $this->assertStringContainsString('push: true', $workflow);
        $this->assertStringContainsString('password: ${{ github.token }}', $workflow);
        $this->assertStringNotContainsString('DOCKERHUB_TOKEN', $workflow);

        $this->assertStringContainsString('org.opencontainers.image.source=https://github.com/Madeena-software/mhcs-core', $workflow);
        $this->assertStringContainsString('org.opencontainers.image.revision=${{ steps.source.outputs.source_sha }}', $workflow);
        $this->assertStringContainsString('BUILD_DIGEST: ${{ steps.build.outputs.digest }}', $workflow);
        $this->assertMatchesRegularExpression('/sha256:\[0-9a-f\]\{64\}/', $workflow);
        $this->assertStringContainsString('IMAGE_REF="ghcr.io/madeena-software/mhcs-core@${BUILD_DIGEST}"', $workflow);
        $this->assertStringContainsString('docker pull "$IMAGE_REF"', $workflow);
        $this->assertStringContainsString('docker image inspect', $workflow);
        $this->assertStringContainsString('oci_revision_verified=true', $workflow);
        $this->assertStringContainsString('oci_source_verified=true', $workflow);

        $this->assertStringContainsString('MHCS_IMAGE="$IMAGE_REF"', $workflow);
        $this->assertStringContainsString('docker compose -f docker-compose.prod.yml config', $workflow);
        $this->assertStringContainsString('compose_validation=PASS', $workflow);

        foreach ([
            'docker stack deploy', 'docker service update', 'php artisan migrate', 'php artisan db:seed',
            'AWS_', 'MPIPS_', 'MHCS_REAL_NPZ', 'SSH_USER', 'SUDO_PASSWORD', 'APP_KEY', 'DB_PASSWORD',
            'DB_ROOT_PASSWORD', 'Phase H', 'Phase I', 'workflow_call', 'workflow chaining',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
        $this->assertStringNotContainsString('secrets.', $workflow);
        $this->assertStringNotContainsString('secrets[', $workflow);
        $this->assertStringNotContainsString('self-hosted', $workflow);
    }
}
