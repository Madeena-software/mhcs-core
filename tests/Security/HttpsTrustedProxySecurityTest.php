<?php

declare(strict_types=1);

namespace Tests\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HttpsTrustedProxySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxied_https_request_from_trusted_proxy_is_recognized_as_secure_and_generates_https_operator_form_action(): void
    {
        $response = $this->call(
            'GET',
            'http://fams.mhcsgo.cloud/operator/login',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '10.250.1.5',
                'HTTP_HOST' => 'fams.mhcsgo.cloud',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            ]
        );

        $response->assertOk();

        $content = (string) $response->getContent();

        // Form action must explicitly use https:// and must NOT use http://
        $this->assertStringContainsString('action="https://fams.mhcsgo.cloud/operator/login"', $content);
        $this->assertStringNotContainsString('action="http://fams.mhcsgo.cloud/operator/login"', $content);
    }

    public function test_proxied_https_request_to_member_login_generates_https_form_action(): void
    {
        $response = $this->call(
            'GET',
            'http://fams.mhcsgo.cloud/login',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '172.28.0.3',
                'HTTP_HOST' => 'fams.mhcsgo.cloud',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.195',
            ]
        );

        $response->assertOk();

        $content = (string) $response->getContent();

        // Form action must explicitly use https:// and must NOT use http://
        $this->assertStringContainsString('action="https://fams.mhcsgo.cloud/login"', $content);
        $this->assertStringNotContainsString('action="http://fams.mhcsgo.cloud/login"', $content);
    }

    public function test_untrusted_remote_ip_does_not_honor_forwarded_proto(): void
    {
        // Direct untrusted client IP (not in 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.1)
        $response = $this->call(
            'GET',
            'http://fams.mhcsgo.cloud/operator/login',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '198.51.100.55',
                'HTTP_HOST' => 'fams.mhcsgo.cloud',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]
        );

        $response->assertOk();

        $content = (string) $response->getContent();

        // Because remote address 198.51.100.55 is untrusted, X-Forwarded-Proto is ignored and form action remains http://
        $this->assertStringContainsString('action="http://fams.mhcsgo.cloud/operator/login"', $content);
        $this->assertStringNotContainsString('action="https://fams.mhcsgo.cloud/operator/login"', $content);
    }

    public function test_session_cookie_is_flagged_secure_when_configured_or_on_https(): void
    {
        config(['session.secure' => true]);

        $response = $this->call(
            'GET',
            'http://fams.mhcsgo.cloud/operator/login',
            [],
            [],
            [],
            [
                'REMOTE_ADDR' => '10.250.1.5',
                'HTTP_HOST' => 'fams.mhcsgo.cloud',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]
        );

        $response->assertOk();

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        foreach ($cookies as $cookie) {
            $this->assertTrue($cookie->isSecure(), "Cookie {$cookie->getName()} must have Secure flag set");
            $this->assertSame('lax', $cookie->getSameSite(), "Cookie {$cookie->getName()} must have SameSite=lax");
        }
    }

    public function test_proxied_https_login_validation_redirect_stays_on_https(): void
    {
        $response = $this->call(
            'POST',
            'http://fams.mhcsgo.cloud/operator/login',
            [
                'identifier' => '',
                'password' => '',
            ],
            [],
            [],
            [
                'REMOTE_ADDR' => '10.250.1.5',
                'HTTP_HOST' => 'fams.mhcsgo.cloud',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
            ]
        );

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://fams.mhcsgo.cloud', $location);
    }
}
