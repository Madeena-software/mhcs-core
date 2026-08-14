<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\User;
use App\Shared\Security\CredentialVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Operator\Mvp04Fixtures;
use Tests\TestCase;

final class ProductionLoginThrottlingTest extends TestCase
{
    use Mvp04Fixtures;
    use RefreshDatabase;

    public function test_credential_verifier_works_under_production_environment_defaults(): void
    {
        config(['app.env' => 'production']);

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

    public function test_operator_login_post_and_dashboard_render_under_production_config(): void
    {
        config(['app.env' => 'production']);

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
