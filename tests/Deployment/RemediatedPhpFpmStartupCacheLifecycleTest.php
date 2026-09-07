<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class RemediatedPhpFpmStartupCacheLifecycleTest extends TestCase
{
    private const FPM_CONTAINER = 'mhcs-remed-fpm-test';
    private const WEB_CONTAINER = 'mhcs-remed-web-test';
    private const HTTP_PORT = 8024;
    private const BASE_URL = 'http://127.0.0.1:8024';

    private string $cookieFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookieFile = storage_path('framework/testing/remed-cookies-' . uniqid() . '.txt');
        $this->cleanupContainers();
    }

    protected function tearDown(): void
    {
        $this->cleanupContainers();
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        foreach (['routes-v7.php', 'config.php'] as $file) {
            $path = base_path('bootstrap/cache/' . $file);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        parent::tearDown();
    }

    private function cleanupContainers(): void
    {
        shell_exec(sprintf('docker rm -f %s %s 2>/dev/null', self::FPM_CONTAINER, self::WEB_CONTAINER));
    }

    /**
     * Proves that under the remediated entrypoint architecture:
     * 1. If an older/stale route cache (historical-0c016f3 lacking operator.shifts.create) is placed on disk
     *    in bootstrap/cache prior to container startup, the container entrypoint finalizes current-release
     *    caches before PHP-FPM starts.
     * 2. The running PHP-FPM process immediately compiles and serves current-release bytecode containing
     *    operator.shifts.create.
     * 3. An authenticated request to GET /operator/eligible-shifts returns HTTP 200 on its very first attempt
     *    without requiring an after-start FPM reload.
     */
    public function test_remediated_startup_finalizes_caches_and_serves_first_request_successfully(): void
    {
        // 0. Verify prerequisite fixtures and Docker images
        $fixtureCache = base_path('tests/Fixtures/RouteCache/historical-0c016f3-routes-v7.php');
        $this->assertFileExists($fixtureCache, 'Historical stale route cache fixture must exist');

        $fixtureContent = file_get_contents($fixtureCache);
        $this->assertStringNotContainsString('operator.shifts.create', $fixtureContent, 'Stale fixture must lack operator.shifts.create');

        // 1. Seed synthetic operator into MySQL
        $seedOutput = shell_exec('php ' . escapeshellarg(base_path('tests/Fixtures/seed_repro_operator.php')));
        $this->assertIsString($seedOutput, 'Seed script must return output');
        $seedData = json_decode(trim($seedOutput), true);
        $this->assertIsArray($seedData, 'Seed script must return valid JSON: ' . $seedOutput);

        // 2. Place stale route-cache fixture into bootstrap/cache to simulate an outdated volume/disk artifact
        $targetCache = base_path('bootstrap/cache/routes-v7.php');
        copy($fixtureCache, $targetCache);
        chmod($targetCache, 0777);

        $diskCacheInitial = file_get_contents($targetCache);
        $this->assertStringNotContainsString('operator.shifts.create', $diskCacheInitial);

        // 3. Start PHP-FPM container using the remediated entrypoint (default command in image)
        $base = escapeshellarg(base_path());
        $fpmCmd = sprintf(
            'docker run -d --name %s --network mhcs-core_default --network-alias app-remed -e DB_HOST=db -v %s:/var/www/html -v %s:%s -w /var/www/html mhcs-core:remediated',
            self::FPM_CONTAINER,
            $base,
            $base,
            $base
        );
        $fpmId = trim((string) shell_exec($fpmCmd));
        $this->assertNotEmpty($fpmId, 'Failed to start remediated PHP-FPM container');

        // 4. Start Nginx reverse proxy pointing to app-remed:9000
        $nginxConf = tempnam(sys_get_temp_dir(), 'nginx-remed-') . '.conf';
        $confContent = str_replace('fastcgi_pass app:9000;', 'fastcgi_pass app-remed:9000;', (string) file_get_contents(base_path('docker/nginx.conf')));
        file_put_contents($nginxConf, $confContent);

        $webCmd = sprintf(
            'docker run -d --name %s --network mhcs-core_default -p 127.0.0.1:%d:80 -v %s:/var/www/html -v %s:%s -v %s:/etc/nginx/conf.d/default.conf:ro nginx:alpine',
            self::WEB_CONTAINER,
            self::HTTP_PORT,
            $base,
            $base,
            $base,
            escapeshellarg($nginxConf)
        );
        $webId = trim((string) shell_exec($webCmd));
        $this->assertNotEmpty($webId, 'Failed to start Nginx container');

        // 5. Wait for web stack readiness
        $ready = false;
        for ($i = 0; $i < 15; $i++) {
            usleep(1000000);
            $health = $this->httpGet('/operator/login');
            if ($health['status'] === 200 && str_contains(strtolower($health['body']), 'operator')) {
                $ready = true;
                break;
            }
        }
        if (! $ready) {
            $fpmLogs = (string) shell_exec(sprintf('docker logs --tail 30 %s 2>&1', self::FPM_CONTAINER));
            $this->fail("Remediated web stack did not become ready. FPM logs:\n" . $fpmLogs);
        }

        // 6. Verify entrypoint logs prove pre-FPM cache finalization completed
        $entrypointLogs = (string) shell_exec(sprintf('docker logs --tail 30 %s 2>&1', self::FPM_CONTAINER));
        $this->assertStringContainsString('Finalizing Laravel runtime caches before PHP-FPM startup', $entrypointLogs);
        $this->assertStringContainsString('Runtime caches finalized successfully. Starting PHP-FPM', $entrypointLogs);

        // 7. Verify disk cache now contains operator.shifts.create
        $diskCacheAfter = (string) file_get_contents($targetCache);
        $this->assertStringContainsString('operator.shifts.create', $diskCacheAfter, 'Entrypoint must overwrite any stale disk cache with current release routes');

        // 8. Authenticate synthetic operator
        $this->authenticateOperator($seedData['email'], $seedData['password'], $seedData['site_id']);

        // 9. Send GET /operator/eligible-shifts on the very first web request
        // Must return HTTP 200 immediately, proving OPcache never loaded the stale bytecode!
        $response = $this->httpGet('/operator/eligible-shifts');
        $this->assertSame(200, $response['status'], sprintf(
            'Remediated FPM first request must return HTTP 200. Got status: %d, body: %s',
            $response['status'],
            substr($response['body'], 0, 300)
        ));
        $this->assertStringContainsString('+ Create Field Operational Shift', $response['body']);
        $this->assertStringContainsString('/operator/shifts/create', $response['body']);

        @unlink($nginxConf);
    }

    /**
     * Structurally verifies that deployment and compose configurations enforce:
     * 1. No post-FPM artisan cache mutation (config:cache, route:cache, view:cache) in deploy-swarm.yml.
     * 2. Pre-FPM cache finalization in docker/entrypoint.sh.
     */
    public function test_deployment_configuration_structurally_prevents_post_fpm_cache_mutation(): void
    {
        $workflowContent = (string) file_get_contents(base_path('.github/workflows/deploy-swarm.yml'));
        $this->assertStringNotContainsString('docker exec "$APP_CONTAINER" php artisan config:cache', $workflowContent);
        $this->assertStringNotContainsString('docker exec "$APP_CONTAINER" php artisan route:cache', $workflowContent);
        $this->assertStringNotContainsString('docker exec "$APP_CONTAINER" php artisan view:cache', $workflowContent);

        $entrypointContent = (string) file_get_contents(base_path('docker/entrypoint.sh'));
        $this->assertStringContainsString('php artisan config:cache', $entrypointContent);
        $this->assertStringContainsString('php artisan route:cache', $entrypointContent);
        $this->assertStringContainsString('php artisan view:cache', $entrypointContent);
        $this->assertStringContainsString('exec php-fpm --nodaemonize', $entrypointContent);

        // Verify ordering: cache preparation precedes exec php-fpm
        $cachePos = strpos($entrypointContent, 'php artisan route:cache');
        $fpmPos = strpos($entrypointContent, 'exec php-fpm --nodaemonize');
        $this->assertNotFalse($cachePos);
        $this->assertNotFalse($fpmPos);
        $this->assertLessThan($fpmPos, $cachePos, 'Cache preparation must strictly precede exec php-fpm');
    }

    /**
     * @return array{status: int, headers: string, body: string, redirect_url: string}
     */
    private function httpRequest(string $method, string $path, array $postData = []): array
    {
        $cookieParam = escapeshellarg($this->cookieFile);
        $url = escapeshellarg(self::BASE_URL . $path);
        if ($method === 'POST') {
            $dataStr = escapeshellarg(http_build_query($postData));
            $cmd = "curl -s -i -k -X POST -c {$cookieParam} -b {$cookieParam} -d {$dataStr} {$url}";
        } else {
            $cmd = "curl -s -i -k -c {$cookieParam} -b {$cookieParam} {$url}";
        }

        $raw = (string) shell_exec($cmd);
        $headerEnd = strpos($raw, "\r\n\r\n");
        if ($headerEnd === false) {
            $headerEnd = strpos($raw, "\n\n");
            $headers = $headerEnd !== false ? substr($raw, 0, $headerEnd) : '';
            $body = $headerEnd !== false ? substr($raw, $headerEnd + 2) : $raw;
        } else {
            $headers = substr($raw, 0, $headerEnd);
            $body = substr($raw, $headerEnd + 4);
        }

        $status = 0;
        if (preg_match('/^HTTP\/[12\.]+ (\d+)/m', $headers, $sm)) {
            $status = (int) $sm[1];
        }

        $redirectUrl = '';
        if (preg_match('/^Location:\s*(.+)$/im', $headers, $lm)) {
            $redirectUrl = trim($lm[1]);
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'redirect_url' => $redirectUrl];
    }

    private function httpGet(string $path): array
    {
        return $this->httpRequest('GET', $path);
    }

    private function authenticateOperator(string $email, string $password, string $siteId): void
    {
        $loginPage = $this->httpGet('/operator/login');
        preg_match('/name="_token" value="([^"]+)"/', $loginPage['body'], $tokenMatch);
        $this->assertNotEmpty($tokenMatch[1] ?? null, 'Failed to extract CSRF token: ' . $loginPage['body']);
        $csrfToken = $tokenMatch[1];

        $loginResult = $this->httpRequest('POST', '/operator/login', [
            '_token' => $csrfToken,
            'identifier' => $email,
            'password' => $password,
        ]);
        $this->assertSame(302, $loginResult['status'], 'Login POST must redirect (302)');

        $sitePage = $this->httpGet('/operator/site');
        $this->assertSame(200, $sitePage['status'], 'Site page must return 200');

        preg_match('/name="_token" value="([^"]+)"/', $sitePage['body'], $siteTokenMatch);
        $siteToken = $siteTokenMatch[1] ?? $csrfToken;

        $siteResult = $this->httpRequest('POST', '/operator/site', [
            '_token' => $siteToken,
            'site_id' => $siteId,
        ]);
        $this->assertSame(302, $siteResult['status'], 'Site selection must redirect (302)');
    }
}
