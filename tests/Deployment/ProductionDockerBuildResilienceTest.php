<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionDockerBuildResilienceTest extends TestCase
{
    public function test_server_parser_packages_only_the_locked_parser_with_compatible_node_runtime(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $this->assertStringContainsString('FROM node:24.21.0-trixie-slim AS dicom-runtime', $dockerfile);
        $this->assertStringContainsString('FROM php:8.4-fpm-trixie AS app', $dockerfile);
        $this->assertStringContainsString('COPY --from=dicom-runtime /usr/local/bin/node /usr/local/bin/node', $dockerfile);
        $this->assertStringContainsString('/app/node_modules/dicom-parser/dist/dicomParser.min.js', $dockerfile);
        $this->assertStringNotContainsString('/app/node_modules ./node_modules', $dockerfile);
        $this->assertStringContainsString('USER www-data', $dockerfile);
        $this->assertStringContainsString('ENTRYPOINT ["/usr/local/bin/mhcs-entrypoint"]', $dockerfile);
        $lock = json_decode(file_get_contents(base_path('package-lock.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('1.8.21', $lock['packages']['node_modules/dicom-parser']['version']);
    }

    public function test_production_node_build_uses_bounded_npm_fetch_resilience(): void
    {
        $dockerfilePath = base_path('Dockerfile');
        $this->assertFileExists($dockerfilePath);

        $dockerfile = file_get_contents($dockerfilePath);
        $this->assertIsString($dockerfile);

        $executableDockerfile = preg_replace('/^\s*#.*$/m', '', $dockerfile);
        $this->assertIsString($executableDockerfile);

        $this->assertStringContainsString('# syntax=docker/dockerfile:1', $dockerfile);
        $this->assertStringContainsString('FROM node:24-alpine AS node-builder', $executableDockerfile);

        foreach ([
            'NPM_CONFIG_FETCH_RETRIES=5',
            'NPM_CONFIG_FETCH_RETRY_FACTOR=2',
            'NPM_CONFIG_FETCH_RETRY_MINTIMEOUT=20000',
            'NPM_CONFIG_FETCH_RETRY_MAXTIMEOUT=120000',
            'NPM_CONFIG_FETCH_TIMEOUT=300000',
            '--mount=type=cache,target=/root/.npm',
            'npm ci',
            '--no-audit',
            '--no-fund',
            '--prefer-offline',
        ] as $required) {
            $this->assertStringContainsString($required, $executableDockerfile);
        }

        $this->assertMatchesRegularExpression(
            '/RUN\s+--mount=type=cache,target=\/root\/\.npm\s+.*npm ci.*&& npm run build/s',
            $executableDockerfile,
        );
        $this->assertMatchesRegularExpression(
            '/COPY\s+--from=node-builder\s+.*\/app\/public\/build\s+\.\/public\/build/s',
            $executableDockerfile,
        );

        foreach ([
            '/\bnpm\s+install\b/',
            '/strict-ssl\s*=\s*false/i',
            '/--network=host/',
            '/--legacy-peer-deps/',
            '/npm\s+config\s+set\s+registry/i',
            '/NODE_TLS_REJECT_UNAUTHORIZED\s*=\s*0/',
            '/(?:for|while|until)\b[^\n]*(?:npm|retry)/i',
            '/--mount=type=cache,target=\/root\/\.npm[^\n]*COPY/i',
        ] as $forbidden) {
            $this->assertDoesNotMatchRegularExpression($forbidden, $executableDockerfile);
        }
    }
}
