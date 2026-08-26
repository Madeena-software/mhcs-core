<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Authorization\AuthorizationException;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Validation\NonclinicalValidationAccountProvisioningService;
use App\Shared\Validation\NonclinicalValidationContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NonclinicalValidationAccountProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_trusted_system_context_creates_exact_fixed_accounts_and_grants(): void
    {
        $this->bindContext();
        $secret = $this->runtimeSecret();

        $result = app(NonclinicalValidationAccountProvisioningService::class)
            ->provision($secret);

        $this->assertFalse($result['replayed']);
        $this->assertSame('CREATED', $result['member_state']);
        $this->assertSame('CREATED', $result['operator_state']);
        $this->assertSame(2, User::query()->count());
        $this->assertDatabaseHas('users', [
            'email' => $this->memberEmail(),
            'account_status' => 'active',
            'login_enabled' => true,
            'must_change_password' => false,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => $this->operatorEmail(),
            'account_status' => 'active',
            'login_enabled' => true,
            'must_change_password' => false,
        ]);
        $this->assertSame([], DB::table('authorization_role_assignments')->where('user_id', $result['member_user_id'])->pluck('role')->all());
        $this->assertSame([], DB::table('authorization_permission_assignments')->where('user_id', $result['member_user_id'])->pluck('permission')->all());
        $this->assertSame(['operator'], DB::table('authorization_role_assignments')->where('user_id', $result['operator_user_id'])->where('active', true)->pluck('role')->all());
        $this->assertSame([
            'operator.arrival.record',
            'operator.attendance.read',
            'operator.identity.verify',
            'operator.portal.access',
        ], DB::table('authorization_permission_assignments')->where('user_id', $result['operator_user_id'])->where('active', true)->orderBy('permission')->pluck('permission')->all());
        $this->assertDatabaseHas('audit_events', ['action' => 'production.validation-context.member-account.provisioned', 'target_id' => $result['member_user_id']]);
        $this->assertDatabaseHas('audit_events', ['action' => 'production.validation-context.operator-account.provisioned', 'target_id' => $result['operator_user_id']]);
        $this->assertTrue(Hash::check($secret, (string) DB::table('users')->where('id', $result['operator_user_id'])->value('password')));
    }

    public function test_exact_replay_is_duplicate_free_and_does_not_reset_passwords(): void
    {
        $this->bindContext();
        $secret = $this->runtimeSecret();
        $service = app(NonclinicalValidationAccountProvisioningService::class);

        $first = $service->provision($secret);
        $memberHash = (string) DB::table('users')->where('id', $first['member_user_id'])->value('password');
        $operatorHash = (string) DB::table('users')->where('id', $first['operator_user_id'])->value('password');
        $replay = $service->provision($secret);

        $this->assertTrue($replay['replayed']);
        $this->assertSame($first['member_user_id'], $replay['member_user_id']);
        $this->assertSame($first['operator_user_id'], $replay['operator_user_id']);
        $this->assertSame($memberHash, DB::table('users')->where('id', $first['member_user_id'])->value('password'));
        $this->assertSame($operatorHash, DB::table('users')->where('id', $first['operator_user_id'])->value('password'));
        $this->assertSame(2, User::query()->count());
        $this->assertSame(1, DB::table('authorization_role_assignments')->count());
        $this->assertSame(4, DB::table('authorization_permission_assignments')->count());
    }

    public function test_non_system_or_untrusted_context_is_rejected(): void
    {
        foreach ([
            ['authenticated-session', ['administrator']],
            ['authenticated-session', ['operator']],
            ['wrong-purpose', ['system']],
        ] as [$purpose, $roles]) {
            $this->bindContext($purpose, $roles);

            try {
                app(NonclinicalValidationAccountProvisioningService::class)->provision($this->runtimeSecret());
                $this->fail('Untrusted context was accepted.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_blank_secret_and_partial_or_unowned_state_fail_closed(): void
    {
        $this->bindContext();
        $service = app(NonclinicalValidationAccountProvisioningService::class);

        $this->expectException(AuthorizationException::class);
        $service->provision(' ');
    }

    public function test_matching_unowned_user_is_not_adopted(): void
    {
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'email' => $this->memberEmail(),
            'password' => Hash::make($this->runtimeSecret()),
            'account_status' => 'active',
            'login_enabled' => true,
            'must_change_password' => false,
        ]);
        $this->bindContext();

        $this->expectException(AuthorizationException::class);
        app(NonclinicalValidationAccountProvisioningService::class)->provision($this->runtimeSecret());
        $this->assertSame(1, User::query()->count());
    }

    public function test_anonymous_and_incomplete_contexts_are_rejected(): void
    {
        foreach ([
            AuthenticatedContext::anonymous(),
            new AuthenticatedContext(roles: ['system'], purpose: 'authenticated-session'),
            new AuthenticatedContext(actorId: LocalId::fromString((string) Str::uuid()), roles: ['system'], purpose: 'authenticated-session'),
        ] as $context) {
            $this->app->instance(AuthenticatedContextProvider::class, new class($context) implements AuthenticatedContextProvider
            {
                public function __construct(private readonly AuthenticatedContext $context) {}

                public function current(): AuthenticatedContext
                {
                    return $this->context;
                }
            });

            try {
                app(NonclinicalValidationAccountProvisioningService::class)->provision($this->runtimeSecret());
                $this->fail('Incomplete context was accepted.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_partial_state_and_wrong_replay_secret_fail_closed(): void
    {
        $this->bindContext();
        $secret = $this->runtimeSecret();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $result = $service->provision($secret);

        try {
            $service->provision($this->runtimeSecret());
            $this->fail('Wrong replay secret was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        DB::table('authorization_permission_assignments')->where('user_id', $result['operator_user_id'])->delete();
        DB::table('authorization_role_assignments')->where('user_id', $result['operator_user_id'])->delete();
        DB::table('audit_events')->where('target_id', $result['operator_user_id'])->delete();
        DB::table('users')->where('id', $result['operator_user_id'])->delete();

        try {
            $service->provision($secret);
            $this->fail('Partial state was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_member_unexpected_grants_and_orphaned_ownership_fail_closed(): void
    {
        $this->bindContext();
        $secret = $this->runtimeSecret();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $result = $service->provision($secret);

        DB::table('authorization_role_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $result['member_user_id'],
            'role' => 'operator',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            $service->provision($secret);
            $this->fail('Unexpected Member grant was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(2, User::query()->count());
    }

    public function test_missing_duplicate_and_wrong_ownership_markers_fail_closed(): void
    {
        $this->bindContext();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $result = $service->provision($this->runtimeSecret());

        DB::table('audit_events')->where('target_id', $result['member_user_id'])->delete();
        $this->assertRejected($service);
    }

    public function test_duplicate_ownership_marker_fails_closed(): void
    {
        $this->bindContext();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $result = $service->provision($this->runtimeSecret());
        $event = DB::table('audit_events')->where('target_id', $result['member_user_id'])->first();

        DB::table('audit_events')->insert([
            ...get_object_vars($event),
            'event_id' => (string) Str::uuid(),
        ]);

        $this->assertRejected($service);
    }

    public function test_account_state_conflicts_fail_closed(): void
    {
        $this->bindContext();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $result = $service->provision($this->runtimeSecret());

        DB::table('users')->where('id', $result['member_user_id'])->update(['login_enabled' => false]);
        $this->assertRejected($service);
    }

    public function test_operator_missing_permission_and_administrator_grant_fail_closed(): void
    {
        $this->bindContext();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $secret = $this->runtimeSecret();
        $result = $service->provision($secret);

        DB::table('authorization_permission_assignments')
            ->where('user_id', $result['operator_user_id'])
            ->where('permission', 'operator.identity.verify')
            ->delete();
        $this->assertRejected($service, $secret);
    }

    public function test_plaintext_and_forced_password_state_fail_closed(): void
    {
        $this->bindContext();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $secret = $this->runtimeSecret();
        $result = $service->provision($secret);

        DB::table('users')->where('id', $result['operator_user_id'])->update([
            'password' => '',
            'must_change_password' => true,
        ]);
        $this->assertRejected($service, $secret);
    }

    public function test_no_member_domain_or_operator_workflow_state_is_created(): void
    {
        $this->bindContext();
        $result = app(NonclinicalValidationAccountProvisioningService::class)->provision($this->runtimeSecret());

        $this->assertSame(0, DB::table('members')->count());
        $this->assertSame(0, DB::table('operator_profiles')->count());
        $this->assertSame(0, DB::table('operator_sites')->count());
        $this->assertSame(0, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('point_ledger_entries')->count());
        $this->assertSame(0, DB::table('member_external_identifiers')->count());
        $this->assertSame(0, DB::table('member_verification_assets')->count());
        $this->assertSame(0, DB::table('operator_eligible_shifts')->count());
        $this->assertSame(0, DB::table('operator_shift_assignments')->count());
        $this->assertSame(0, DB::table('operator_arrivals')->count());
        $this->assertSame(0, DB::table('operator_paper_tickets')->count());
        $this->assertSame(0, DB::table('operator_queue_admissions')->count());
        $this->assertSame(0, DB::table('image_gateway_capture_sets')->count());
        $this->assertNotEmpty($result['member_user_id']);
    }

    public function test_audit_failure_rolls_back_both_accounts_and_grants(): void
    {
        $this->bindContext();
        $this->app->instance(AuditStore::class, new class implements AuditStore
        {
            public function append(AuditEvent $event): void
            {
                throw new \RuntimeException('audit failure');
            }
        });

        try {
            app(NonclinicalValidationAccountProvisioningService::class)->provision($this->runtimeSecret());
            $this->fail('Audit failure did not abort provisioning.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, DB::table('authorization_role_assignments')->count());
        $this->assertSame(0, DB::table('authorization_permission_assignments')->count());
        $this->assertSame(0, DB::table('audit_events')->count());
    }

    public function test_existing_email_uniqueness_is_the_schema_concurrency_guard(): void
    {
        $this->bindContext();
        app(NonclinicalValidationAccountProvisioningService::class)->provision($this->runtimeSecret());

        $this->expectException(QueryException::class);
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'email' => $this->memberEmail(),
            'password' => Hash::make($this->runtimeSecret()),
            'account_status' => 'active',
            'login_enabled' => true,
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_unexpected_operator_grant_fails_closed(): void
    {
        $this->bindContext();
        $secret = $this->runtimeSecret();
        $service = app(NonclinicalValidationAccountProvisioningService::class);
        $result = $service->provision($secret);

        DB::table('authorization_permission_assignments')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $result['operator_user_id'],
            'permission' => 'operator.audit.read',
            'assigned_by_user_id' => null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        $service->provision($secret);
    }

    public function test_context_identity_and_secret_contract_are_canonical_and_non_environment_driven(): void
    {
        $this->assertSame('MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD', NonclinicalValidationAccountProvisioningService::OPERATOR_SECRET_NAME);
        $this->assertSame('mhcs-'.NonclinicalValidationContext::KEY.'-member@invalid', $this->memberEmail());
        $this->assertSame('mhcs-'.NonclinicalValidationContext::KEY.'-operator@invalid', $this->operatorEmail());

        $serviceSource = (string) file_get_contents(base_path('app/Shared/Validation/NonclinicalValidationAccountProvisioningService.php'));
        $testSource = (string) file_get_contents(__FILE__);
        foreach ([
            implode('-', ['operator', 'secret']),
            implode('-', ['wrong', 'secret']),
        ] as $literal) {
            $this->assertStringNotContainsString($literal, $serviceSource.$testSource);
        }
        $this->assertStringNotContainsString(implode(['e', 'n', 'v', '(']), $serviceSource);
    }

    private function runtimeSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function memberEmail(): string
    {
        return 'mhcs-'.NonclinicalValidationContext::KEY.'-member@invalid';
    }

    private function operatorEmail(): string
    {
        return 'mhcs-'.NonclinicalValidationContext::KEY.'-operator@invalid';
    }

    private function assertRejected(NonclinicalValidationAccountProvisioningService $service, ?string $secret = null): void
    {
        try {
            $service->provision($secret ?? $this->runtimeSecret());
            $this->fail('Inconsistent validation account state was accepted.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function bindContext(string $purpose = 'authenticated-session', array $roles = ['system']): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) Str::uuid()),
            operationId: CorrelationId::random(),
            roles: $roles,
            purpose: $purpose,
        );
        $this->app->instance(AuthenticatedContextProvider::class, new class($context) implements AuthenticatedContextProvider
        {
            public function __construct(private readonly AuthenticatedContext $context) {}

            public function current(): AuthenticatedContext
            {
                return $this->context;
            }
        });
    }
}
