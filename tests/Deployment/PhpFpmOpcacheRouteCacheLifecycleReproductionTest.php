<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class PhpFpmOpcacheRouteCacheLifecycleReproductionTest extends TestCase
{
    private const FPM_CONTAINER = 'mhcs-repro-fpm-test';
    private const WEB_CONTAINER = 'mhcs-repro-web-test';
    private const HTTP_PORT = 8023;
    private const BASE_URL = 'http://127.0.0.1:8023';

    private string $cookieFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookieFile = storage_path('framework/testing/repro-cookies-' . uniqid() . '.txt');
        $this->cleanupContainers();
    }

    protected function tearDown(): void
    {
        $this->cleanupContainers();
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        if (file_exists(base_path('bootstrap/cache/routes-v7.php'))) {
            @unlink(base_path('bootstrap/cache/routes-v7.php'));
        }
        parent::tearDown();
    }

    private function cleanupContainers(): void
    {
        shell_exec(sprintf('docker rm -f %s %s 2>/dev/null', self::FPM_CONTAINER, self::WEB_CONTAINER));
    }

    /**
     * Empirically proves the production OPcache lifecycle mechanism:
     * A long-running PHP-FPM process with opcache.validate_timestamps=0 retains an older
     * route cache in OPcache memory after a separate CLI process rewrites routes-v7.php on disk,
     * causing web requests to continue failing until PHP-FPM receives a graceful reload (USR2).
     */
    public function test_php_fpm_opcache_retains_stale_route_cache_until_graceful_reload(): void
    {
        // 0. Verify prerequisite container images and fixtures
        $fixtureCache = base_path('tests/Fixtures/RouteCache/historical-0c016f3-routes-v7.php');
        $this->assertFileExists($fixtureCache, 'Historical stale route cache fixture must exist');

        $fixtureContent = file_get_contents($fixtureCache);
        $this->assertStringNotContainsString('operator.shifts.create', $fixtureContent, 'Stale fixture must demonstrably lack operator.shifts.create');
        $this->assertStringContainsString('operator.eligible-shifts', $fixtureContent, 'Stale fixture must contain operator.eligible-shifts');

        // Verify Docker images
        $images = (string) shell_exec('docker images --format "{{.Repository}}:{{.Tag}}"');
        $this->assertStringContainsString('mhcs-core:test', $images, 'mhcs-core:test image must be available');
        $this->assertStringContainsString('nginx:alpine', $images, 'nginx:alpine image must be available');

        // 1. Seed synthetic operator into MySQL
        $seedOutput = shell_exec('php ' . escapeshellarg(base_path('tests/Fixtures/seed_repro_operator.php')));
        $this->assertIsString($seedOutput, 'Seed script must return output');
        $seedData = json_decode(trim($seedOutput), true);
        $this->assertIsArray($seedData, 'Seed script must return valid JSON: ' . $seedOutput);
        $this->assertArrayHasKey('email', $seedData);
        $this->assertArrayHasKey('password', $seedData);
        $this->assertArrayHasKey('site_id', $seedData);

        // 2. PHASE A: Place stale route-cache artifact in persisted bootstrap/cache
        $targetCache = base_path('bootstrap/cache/routes-v7.php');
        copy($fixtureCache, $targetCache);
        chmod($targetCache, 0777);

        $diskCacheInitial = file_get_contents($targetCache);
        $this->assertStringNotContainsString('operator.shifts.create', $diskCacheInitial, 'Phase A disk cache must not contain operator.shifts.create');

        // Start long-running PHP-FPM with production-equivalent OPcache
        $base = escapeshellarg(base_path());
        $fpmCmd = sprintf(
            'docker run -d --name %s --network mhcs-core_default --network-alias app -e DB_HOST=db -v %s:/var/www/html -v %s:%s -w /var/www/html mhcs-core:test php-fpm --nodaemonize',
            self::FPM_CONTAINER,
            $base,
            $base,
            $base
        );
        $fpmId = trim((string) shell_exec($fpmCmd));
        $this->assertNotEmpty($fpmId, 'Failed to start PHP-FPM container');

        // Start Nginx
        $webCmd = sprintf(
            'docker run -d --name %s --network mhcs-core_default -p 127.0.0.1:%d:80 -v %s:/var/www/html -v %s:%s -v %s/docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro nginx:alpine',
            self::WEB_CONTAINER,
            self::HTTP_PORT,
            $base,
            $base,
            $base,
            $base
        );
        $webId = trim((string) shell_exec($webCmd));
        $this->assertNotEmpty($webId, 'Failed to start Nginx container');

        // Wait up to 15s for web stack readiness
        $ready = false;
        $lastStatus = 0;
        $lastBody = '';
        $lastError = '';
        for ($i = 0; $i < 15; $i++) {
            usleep(1000000);
            $health = $this->httpGet('/operator/login');
            $lastStatus = $health['status'];
            $lastBody = $health['body'];
            $lastError = $health['error'] ?? '';
            if ($health['status'] === 200 && str_contains(strtolower($health['body']), 'operator')) {
                $ready = true;
                break;
            }
        }
        if (! $ready) {
            $fpmLogs = (string) shell_exec(sprintf('docker logs --tail 30 %s 2>&1', self::FPM_CONTAINER));
            $webLogs = (string) shell_exec(sprintf('docker logs --tail 30 %s 2>&1', self::WEB_CONTAINER));
            $this->fail(sprintf(
                "PHP-FPM + Nginx stack did not become ready at /operator/login.\nLast status: %d\nLast error: %s\nLast body: %s\nFPM logs:\n%s\nNginx logs:\n%s",
                $lastStatus,
                $lastError,
                substr($lastBody, 0, 300),
                $fpmLogs,
                $webLogs
            ));
        }

        // Verify effective OPcache settings in FPM
        $fpmOpcache = (string) shell_exec(sprintf('docker exec %s php -i | grep -iE "opcache\.(enable|validate_timestamps)"', self::FPM_CONTAINER));
        $this->assertMatchesRegularExpression('/opcache\.enable\s*=>\s*On/i', $fpmOpcache, 'FPM must have opcache.enable=On');
        $this->assertMatchesRegularExpression('/opcache\.enable_cli\s*=>\s*Off/i', $fpmOpcache, 'FPM must have opcache.enable_cli=Off');
        $this->assertMatchesRegularExpression('/opcache\.validate_timestamps\s*=>\s*Off/i', $fpmOpcache, 'FPM must have opcache.validate_timestamps=Off (0)');

        // Authenticate synthetic Operator via HTTP
        $this->authenticateOperator($seedData['email'], $seedData['password'], $seedData['site_id']);

        // Phase A web request: GET /operator/eligible-shifts
        $phaseAResponse = $this->httpGet('/operator/eligible-shifts');
        $this->assertSame(500, $phaseAResponse['status'], sprintf('Phase A request must fail with HTTP 500 when route cache is stale. Redirect: %s, Body: %s', $phaseAResponse['redirect_url'], substr($phaseAResponse['body'], 0, 200)));

        $recentLogs = (string) shell_exec('tail -n 25 ' . escapeshellarg(storage_path('logs/laravel.log')));
        $this->assertStringContainsString('Route [operator.shifts.create] not defined', $recentLogs, 'Phase A failure must log missing operator.shifts.create route');

        // 3. PHASE B: Refresh disk only via separate CLI process without touching FPM
        $cliOutput = (string) shell_exec(sprintf('docker exec %s php artisan route:cache', self::FPM_CONTAINER));
        $this->assertStringContainsString('Routes cached successfully', $cliOutput, 'CLI route:cache must report success');

        // Deterministically verify route cache on disk now contains operator.shifts.create
        $diskCacheRefreshed = (string) file_get_contents($targetCache);
        $this->assertStringContainsString('operator.shifts.create', $diskCacheRefreshed, 'Phase B: disk cache must now contain operator.shifts.create');

        // 4. PHASE C: Prove web process remains stale without FPM reload
        $phaseCResponse = $this->httpGet('/operator/eligible-shifts');
        $this->assertSame(500, $phaseCResponse['status'], 'Phase C request MUST STILL return HTTP 500 because running PHP-FPM OPcache retains stale bytecode');

        $recentLogsC = (string) shell_exec('tail -n 25 ' . escapeshellarg(storage_path('logs/laravel.log')));
        $this->assertStringContainsString('Route [operator.shifts.create] not defined', $recentLogsC, 'Phase C must still log missing operator.shifts.create');

        // 5. PHASE D: Controlled local graceful PHP-FPM reload
        $reloadOutput = (string) shell_exec(sprintf('docker kill -s USR2 %s', self::FPM_CONTAINER));
        $this->assertStringContainsString(self::FPM_CONTAINER, $reloadOutput, 'docker kill -s USR2 must succeed');
        usleep(1500000); // 1.5s for graceful worker respawn

        $fpmLogs = (string) shell_exec(sprintf('docker logs --tail 20 %s 2>&1', self::FPM_CONTAINER));
        $this->assertStringContainsString('Reloading in progress', $fpmLogs, 'FPM logs must show graceful reload in progress');

        // Repeat request after FPM reload
        $phaseDResponse = $this->httpGet('/operator/eligible-shifts');
        $this->assertSame(200, $phaseDResponse['status'], 'Phase D request must return HTTP 200 after PHP-FPM graceful reload');
        $this->assertStringContainsString('+ Create Field Operational Shift', $phaseDResponse['body'], 'Phase D view must render Create Field Operational Shift button');
        $this->assertStringContainsString('/operator/shifts/create', $phaseDResponse['body'], 'Phase D view must render operator.shifts.create URL');
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
        // Step 1: GET /operator/login to retrieve CSRF token
        $loginPage = $this->httpGet('/operator/login');
        preg_match('/name="_token" value="([^"]+)"/', $loginPage['body'], $tokenMatch);
        $this->assertNotEmpty($tokenMatch[1] ?? null, 'Failed to extract CSRF token from login page: ' . $loginPage['body']);
        $csrfToken = $tokenMatch[1];

        // Step 2: POST /operator/login
        $loginResult = $this->httpRequest('POST', '/operator/login', [
            '_token' => $csrfToken,
            'identifier' => $email,
            'password' => $password,
        ]);
        $this->assertSame(302, $loginResult['status'], 'Login POST must redirect (302). Headers: ' . $loginResult['headers']);
        $this->assertStringNotContainsString('/operator/login', $loginResult['redirect_url'], 'Login failed: redirected back to login page. Headers: ' . $loginResult['headers']);

        // Step 3: GET /operator/site and submit site selection
        $sitePage = $this->httpGet('/operator/site');
        $this->assertSame(200, $sitePage['status'], "Site page must return 200. Headers:\n{$sitePage['headers']}\nBody:\n" . substr($sitePage['body'], 0, 400));
        $this->assertStringContainsString('name="site_id"', $sitePage['body'], 'Site page must contain site_id form.');

        preg_match('/name="_token" value="([^"]+)"/', $sitePage['body'], $siteTokenMatch);
        $siteToken = $siteTokenMatch[1] ?? $csrfToken;

        // Submit site selection
        $siteResult = $this->httpRequest('POST', '/operator/site', [
            '_token' => $siteToken,
            'site_id' => $siteId,
        ]);
        $this->assertSame(302, $siteResult['status'], 'Site selection must redirect (302)');
        $this->assertStringNotContainsString('/operator/site', $siteResult['redirect_url'], 'Site selection failed: redirected back to site selection');
    }
}
