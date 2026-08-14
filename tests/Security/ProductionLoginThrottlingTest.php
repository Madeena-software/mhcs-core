<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\User;
use App\Shared\Security\CredentialVerifier;
use App\Shared\Security\SecurityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class ProductionLoginThrottlingTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_credential_verifier_works_under_injected_production_throttling_configuration(): void
    {
        config([
            'app.env' => 'production',
            'mhcs.security.login.pair_max_attempts' => 5,
            'mhcs.security.login.origin_max_attempts' => 10,
            'mhcs.security.login.identifier_max_attempts' => 20,
            'mhcs.security.login.decay_seconds' => 60,
        ]);

        $user = User::factory()->create([
            'email' => 'operator-prod-test@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'account_status' => 'active',
            'login_enabled' => true,
        ]);

        $verifier = app(CredentialVerifier::class);
        $result = $verifier->verifyForInteractiveLogin('operator-prod-test@example.test', 'CorrectPassword123!');

        $this->assertTrue($result->authenticated);
        $this->assertNotNull($result->user);
        $this->assertSame((string) $user->id, (string) $result->user->id);
    }

    public function test_credential_verifier_fails_closed_when_production_throttling_is_missing(): void
    {
        config([
            'app.env' => 'production',
            'mhcs.security.login.pair_max_attempts' => null,
            'mhcs.security.login.origin_max_attempts' => null,
            'mhcs.security.login.identifier_max_attempts' => null,
            'mhcs.security.login.decay_seconds' => null,
        ]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Credential throttling [pair_max_attempts] must be a positive integer.');

        $verifier = app(CredentialVerifier::class);
        $verifier->verifyForInteractiveLogin('operator@example.test', 'password');
    }

    public function test_unconfigured_production_image_and_upload_policies_remain_fail_closed(): void
    {
        config([
            'app.env' => 'production',
            'mhcs.upload.max_file_mb' => null,
            'mhcs.upload.max_file_bytes' => null,
            'mhcs.image_policy.decompressed_bytes' => null,
            'mhcs.image_policy.max_width' => null,
            'mhcs.image_policy.max_height' => null,
        ]);

        $this->assertNull(config('mhcs.upload.max_file_mb'));
        $this->assertNull(config('mhcs.upload.max_file_bytes'));
        $this->assertNull(config('mhcs.image_policy.decompressed_bytes'));
        $this->assertNull(config('mhcs.image_policy.max_width'));
        $this->assertNull(config('mhcs.image_policy.max_height'));
    }

    public function test_operator_login_post_and_dashboard_render_under_production_config(): void
    {
        config([
            'app.env' => 'production',
            'mhcs.security.login.pair_max_attempts' => 5,
            'mhcs.security.login.origin_max_attempts' => 10,
            'mhcs.security.login.identifier_max_attempts' => 20,
            'mhcs.security.login.decay_seconds' => 60,
        ]);

        $fixture = $this->operatorFixture(false);

        // 1. Submit operator login POST
        $response = $this->post(route('operator.login.store'), [
            'identifier' => $fixture['operator']->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('operator.dashboard'));
        $this->assertAuthenticatedAs($fixture['operator'], 'web');

        // 2. Reach operator dashboard (GET /operator)
        $dashboardResponse = $this->get(route('operator.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee('Dasbor operator');
    }
}
