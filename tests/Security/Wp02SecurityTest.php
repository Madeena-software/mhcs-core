<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Models\User;
use App\Modules\Member\Application\Contracts\MemberProjectionException;
use App\Modules\Member\Application\Contracts\OperatorMemberProjection;
use App\Modules\Member\Domain\Funding\FundingPolicy;
use App\Modules\Member\Domain\Funding\FundingPolicyException;
use App\Modules\Member\Domain\Funding\FundingSource;
use App\Shared\Adapters\AuthenticatedExternalAdapter;
use App\Shared\Adapters\ExternalAdapterExecutor;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditException;
use App\Shared\Audit\AuditStore;
use App\Shared\Authorization\AuthorizationException;
use App\Shared\Authorization\AuthorizationGuard;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\CorrelationId;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Logging\CorrelatedLogger;
use App\Shared\Security\CredentialVerifier;
use App\Shared\Security\KeyMaterial;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Security\SecurityException;
use App\Shared\Security\SensitiveDataSanitizer;
use App\Shared\Security\SensitivePayloadException;
use App\Shared\Security\TemporaryCredentialIssuer;
use App\Shared\Storage\AccessGrant;
use App\Shared\Storage\EncryptedLocalObjectStore;
use App\Shared\Storage\ObjectAccessException;
use App\Shared\Time\FrozenClock;
use App\Shared\Transactions\TransactionalRowLock;
use App\Shared\Transactions\TransactionException;
use DateTimeImmutable;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

final class Wp02SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.security.identifier_key' => str_repeat('i', 32),
            'mhcs.security.object_key' => str_repeat('o', 32),
            'mhcs.security.grant_key' => str_repeat('g', 32),
            'mhcs.security.login' => [
                'pair_max_attempts' => 5,
                'origin_max_attempts' => 10,
                'identifier_max_attempts' => 20,
                'decay_seconds' => 60,
            ],
        ]);
        RateLimiter::clear('credential:'.hash('sha256', 'test'));
    }

    public function test_identifier_display_encryption_and_keyed_lookup_are_separate_and_fail_closed(): void
    {
        $service = new ProtectedIdentifierService(
            new Encrypter(str_repeat('e', 32), 'AES-256-CBC'),
            KeyMaterial::from(str_repeat('i', 32)),
        );
        $protected = $service->protect('  synthetic-identifier-value ');

        $this->assertNotSame('synthetic-identifier-value', $protected['encrypted_display']);
        $this->assertSame('synthetic-identifier-value', $service->display($protected['encrypted_display']));
        $this->assertSame($protected['lookup_digest'], $service->lookupDigest('synthetic-identifier-value'));
        $this->assertNotSame($protected['encrypted_display'], $protected['lookup_digest']);

        $this->expectException(\Throwable::class);
        KeyMaterial::from('');
    }

    public function test_temporary_credentials_are_hashed_and_require_replacement(): void
    {
        $user = User::factory()->create(['email' => 'temporary@example.test']);
        $plaintext = app(TemporaryCredentialIssuer::class)->issue($user);
        $user->refresh();

        $this->assertNotSame($plaintext, $user->password);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($plaintext, $user->password));

        app(TemporaryCredentialIssuer::class)->replace($user, 'replacement-password');
        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('replacement-password', $user->password));

        $suspended = User::factory()->create([
            'email' => 'suspended-temporary@example.test',
            'account_status' => 'suspended',
        ]);
        app(TemporaryCredentialIssuer::class)->issue($suspended);
        $suspended->refresh();

        $this->assertSame('suspended', $suspended->account_status);
    }

    public function test_credential_verification_is_generic_rate_limited_and_denies_suspension(): void
    {
        $provider = $this->bindContext('credential.verify');
        $identifiers = new ProtectedIdentifierService(
            new Encrypter(str_repeat('e', 32), 'AES-256-CBC'),
            KeyMaterial::from(str_repeat('i', 32)),
        );
        $user = User::factory()->create([
            'email' => 'known@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $verifier = new CredentialVerifier($identifiers, app(AuditStore::class), $provider, app('App\\Shared\\Time\\Clock'));

        $unknown = $verifier->verify('unknown@example.test', 'wrong-password');
        $incorrect = $verifier->verify('known@example.test', 'wrong-password');

        $this->assertFalse($unknown->authenticated);
        $this->assertSame($unknown->message, $incorrect->message);
        $this->assertDatabaseCount('audit_events', 2);

        $user->forceFill(['account_status' => 'suspended'])->save();
        $suspended = $verifier->verify('known@example.test', 'correct-password');
        $this->assertFalse($suspended->authenticated);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_status' => 'suspended']);

        $pending = User::factory()->create([
            'email' => 'pending@example.test',
            'password' => Hash::make('correct-password'),
            'account_status' => 'pending_activation',
        ]);
        $loginDisabled = User::factory()->create([
            'email' => 'login-disabled@example.test',
            'password' => Hash::make('correct-password'),
            'login_enabled' => false,
        ]);

        $this->assertFalse($verifier->verify($pending->email, 'correct-password')->authenticated);
        $this->assertFalse($verifier->verify($loginDisabled->email, 'correct-password')->authenticated);
    }

    public function test_successful_logins_from_one_origin_do_not_consume_origin_limits(): void
    {
        config([
            'mhcs.security.login.pair_max_attempts' => 1,
            'mhcs.security.login.origin_max_attempts' => 1,
            'mhcs.security.login.identifier_max_attempts' => 2,
        ]);
        $user = User::factory()->create([
            'email' => 'shared-origin@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $verifier = $this->credentialVerifier();

        $this->requestFrom('198.51.100.10');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $result = $verifier->verify($user->email, 'correct-password');

            $this->assertTrue($result->authenticated);
            $this->assertFalse($result->rateLimited);
        }
    }

    public function test_failed_attempts_from_one_trusted_origin_are_bounded_even_with_forwarded_headers(): void
    {
        config([
            'mhcs.security.login.pair_max_attempts' => 5,
            'mhcs.security.login.origin_max_attempts' => 2,
            'mhcs.security.login.identifier_max_attempts' => 10,
        ]);
        $verifier = $this->credentialVerifier();

        foreach (['203.0.113.10', '203.0.113.11'] as $forwarded) {
            $this->requestFrom('198.51.100.20', $forwarded);
            $this->assertFalse($verifier->verify('origin-'.$forwarded.'@example.test', 'wrong-password')->rateLimited);
        }

        $this->requestFrom('198.51.100.20', '203.0.113.12');
        $this->assertTrue($verifier->verify('third-origin-attempt@example.test', 'wrong-password')->rateLimited);
    }

    public function test_distributed_failures_reach_the_broader_identifier_threshold(): void
    {
        config([
            'mhcs.security.login.pair_max_attempts' => 2,
            'mhcs.security.login.origin_max_attempts' => 2,
            'mhcs.security.login.identifier_max_attempts' => 3,
        ]);
        $verifier = $this->credentialVerifier();

        foreach (['198.51.100.30', '198.51.100.31', '198.51.100.32'] as $origin) {
            $this->requestFrom($origin);
            $this->assertFalse($verifier->verify('distributed@example.test', 'wrong-password')->rateLimited);
        }

        $this->requestFrom('198.51.100.33');
        $this->assertTrue($verifier->verify('distributed@example.test', 'wrong-password')->rateLimited);
    }

    public function test_success_clears_pair_and_identifier_failures_but_not_origin_abuse(): void
    {
        config([
            'mhcs.security.login.pair_max_attempts' => 2,
            'mhcs.security.login.origin_max_attempts' => 3,
            'mhcs.security.login.identifier_max_attempts' => 4,
        ]);
        $user = User::factory()->create([
            'email' => 'cleared@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $verifier = $this->credentialVerifier();

        $this->requestFrom('198.51.100.40');
        $this->assertFalse($verifier->verify($user->email, 'wrong-password')->rateLimited);
        $this->assertTrue($verifier->verify($user->email, 'correct-password')->authenticated);
        $this->assertFalse($verifier->verify($user->email, 'wrong-password')->rateLimited);
        $this->assertFalse($verifier->verify($user->email, 'wrong-password')->rateLimited);
        $this->assertTrue($verifier->verify($user->email, 'wrong-password')->rateLimited);
    }

    public function test_successful_login_does_not_clear_unrelated_origin_failures(): void
    {
        config([
            'mhcs.security.login.pair_max_attempts' => 5,
            'mhcs.security.login.origin_max_attempts' => 2,
            'mhcs.security.login.identifier_max_attempts' => 10,
        ]);
        $user = User::factory()->create([
            'email' => 'origin-success@example.test',
            'password' => Hash::make('correct-password'),
        ]);
        $verifier = $this->credentialVerifier();
        $origin = '198.51.100.50';

        $this->requestFrom($origin);
        $this->assertFalse($verifier->verify('unrelated-one@example.test', 'wrong-password')->rateLimited);
        $this->assertTrue($verifier->verify($user->email, 'correct-password')->authenticated);
        $this->assertFalse($verifier->verify('unrelated-two@example.test', 'wrong-password')->rateLimited);
        $this->assertTrue($verifier->verify('unrelated-three@example.test', 'wrong-password')->rateLimited);
    }

    public function test_invalid_credential_throttling_configuration_fails_closed(): void
    {
        $base = [
            'pair_max_attempts' => 2,
            'origin_max_attempts' => 2,
            'identifier_max_attempts' => 3,
            'decay_seconds' => 60,
        ];
        $invalidConfigurations = [
            'missing' => array_diff_key($base, ['pair_max_attempts' => true]),
            'zero' => ['pair_max_attempts' => 0] + $base,
            'negative' => ['pair_max_attempts' => -1] + $base,
            'blank' => ['pair_max_attempts' => ''] + $base,
            'malformed' => ['pair_max_attempts' => 'not-an-integer'] + $base,
            'inconsistent' => ['identifier_max_attempts' => 2] + $base,
        ];

        foreach ($invalidConfigurations as $name => $configuration) {
            config(['mhcs.security.login' => $configuration]);

            try {
                $this->credentialVerifier()->verify('invalid-config@example.test', 'wrong-password');
                $this->fail("Configuration [{$name}] must fail closed.");
            } catch (SecurityException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_laravel_authentication_denies_suspended_and_temporary_accounts(): void
    {
        $suspended = User::factory()->create([
            'email' => 'auth-suspended@example.test',
            'password' => Hash::make('correct-password'),
            'account_status' => 'suspended',
        ]);
        $temporary = User::factory()->create([
            'email' => 'auth-temporary@example.test',
            'password' => Hash::make('correct-password'),
            'must_change_password' => true,
        ]);
        $pending = User::factory()->create([
            'email' => 'auth-pending@example.test',
            'password' => Hash::make('correct-password'),
            'account_status' => 'pending_activation',
        ]);
        $loginDisabled = User::factory()->create([
            'email' => 'auth-login-disabled@example.test',
            'password' => Hash::make('correct-password'),
            'login_enabled' => false,
        ]);
        $active = User::factory()->create([
            'email' => 'auth-active@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->assertFalse(Auth::attempt(['email' => $suspended->email, 'password' => 'correct-password']));
        $this->assertFalse(Auth::attempt(['email' => $temporary->email, 'password' => 'correct-password']));
        $this->assertFalse(Auth::attempt(['email' => $pending->email, 'password' => 'correct-password']));
        $this->assertFalse(Auth::attempt(['email' => $loginDisabled->email, 'password' => 'correct-password']));
        $this->assertTrue(Auth::attempt(['email' => $active->email, 'password' => 'correct-password']));
        Auth::logout();
    }

    public function test_audit_is_append_only_rejects_sensitive_metadata_and_rolls_back_with_state(): void
    {
        $event = $this->auditEvent('audit-1');
        app(AuditStore::class)->append($event);
        $this->assertDatabaseHas('audit_events', ['event_id' => 'audit-1']);

        $this->expectException(AuditException::class);
        app(AuditStore::class)->append($event);
    }

    public function test_sensitive_audit_payloads_are_rejected(): void
    {
        $this->expectException(SensitivePayloadException::class);
        $this->auditEvent('audit-sensitive', ['password' => 'do-not-store']);
    }

    public function test_sensitive_scalar_audit_payloads_are_rejected_under_neutral_keys(): void
    {
        foreach ([
            'nik' => '3201010101010001',
            'kk' => '3201010101010002',
            'bearer' => 'Bearer eyJhbGciOiJIUzI1NiJ9.synthetic-signature',
            'credentials' => 'username=operator@example.test password=not-for-audit',
            'clinical' => 'Patient reports chest pain and shortness of breath.',
            'npz' => "PK\x03\x04 synthetic NPZ payload",
            'dicom' => str_repeat("\x00", 128).'DICM'.'synthetic payload',
        ] as $label => $value) {
            try {
                SensitiveDataSanitizer::assertSafe(['neutral_value' => $value]);
                $this->fail("Sensitive scalar [{$label}] was accepted.");
            } catch (SensitivePayloadException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_canonical_uuid_audit_identifiers_are_opaque_but_raw_numeric_scalars_remain_rejected(): void
    {
        $uuid = '00000000-0000-4000-8000-123456789012';
        $event = AuditEvent::fromContext(
            context: $this->context('audit'),
            action: 'security.uuid',
            source: 'test',
            outcome: 'observed',
            occurredAt: new DateTimeImmutable('2026-08-07T00:00:00+00:00'),
            targetType: 'operator_identity_verification',
            targetId: $uuid,
            metadata: [
                'operator_site_id' => $uuid,
                'purpose' => 'identity.view',
            ],
        );

        app(AuditStore::class)->append($event);
        $stored = DB::table('audit_events')->where('event_id', $event->eventId)->first();

        $this->assertNotNull($stored);
        $this->assertSame($uuid, $stored->target_id);
        $this->assertSame($uuid, json_decode($stored->metadata, true, flags: JSON_THROW_ON_ERROR)['operator_site_id']);

        foreach (['1234567890', '123456789012', '12345678901234567890'] as $value) {
            try {
                SensitiveDataSanitizer::assertSafeString($value);
                $this->fail('A standalone numeric identifier was accepted as a string.');
            } catch (SensitivePayloadException) {
                $this->addToAssertionCount(1);
            }

            try {
                SensitiveDataSanitizer::assertSafe(['neutral_value' => $value]);
                $this->fail('A standalone numeric identifier was accepted in metadata.');
            } catch (SensitivePayloadException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_audit_and_outbox_follow_local_transaction_rollback(): void
    {
        try {
            DB::transaction(function (): never {
                DB::table('idempotent_consumptions')->insert([
                    'message_id' => 'security-rollback',
                    'consumer' => 'security-source',
                    'payload_hash' => hash('sha256', 'security-rollback'),
                    'status' => 'handled',
                    'attempts' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(AuditStore::class)->append($this->auditEvent('audit-rollback'));
                app(OutboxStore::class)->record($this->event('event-rollback'));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseMissing('idempotent_consumptions', [
            'message_id' => 'security-rollback',
            'consumer' => 'security-source',
        ]);
        $this->assertDatabaseMissing('audit_events', ['event_id' => 'audit-rollback']);
        $this->assertDatabaseMissing('outbox_messages', ['event_id' => 'event-rollback']);
    }

    public function test_correlated_logs_are_recursive_and_sanitized(): void
    {
        $provider = $this->bindContext('logging');
        $handler = new TestHandler;
        $logger = new Logger('security-test', [$handler]);
        $correlated = new CorrelatedLogger($logger, $provider);

        $correlated->info('security operation', [
            'password' => 'SYNTHETIC_VALUE_A',
            'nested' => ['authorization' => 'SYNTHETIC_VALUE_B'],
            'safe_status' => 'accepted',
        ]);

        $record = $handler->getRecords()[0];
        $context = $record['context'];
        $this->assertSame('operation-1', $context['correlation_id']);
        $this->assertSame('[REDACTED]', $context['password']);
        $this->assertSame('[REDACTED]', $context['nested']['authorization']);
        $this->assertSame('accepted', $context['safe_status']);
        $this->assertStringNotContainsString('SYNTHETIC_VALUE_A', json_encode($context));
        $this->assertStringNotContainsString('SYNTHETIC_VALUE_B', json_encode($context));
    }

    public function test_private_objects_are_encrypted_opaque_and_grant_protected(): void
    {
        Storage::fake('local');
        $clock = new FrozenClock(new DateTimeImmutable('2026-08-04T10:00:00+00:00'));
        $store = new EncryptedLocalObjectStore(
            KeyMaterial::from(str_repeat('o', 32)),
            KeyMaterial::from(str_repeat('g', 32)),
            $clock,
        );
        $context = $this->context('object.read');
        $object = $store->put('private clinical bytes', $context, 'object.read');
        $grant = $store->grant(
            $object,
            $context,
            'member-view',
            'object.read',
            new DateTimeImmutable('2026-08-04T10:01:00+00:00'),
        );

        $stored = Storage::disk('local')->get((string) $object->key);
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('private clinical bytes', $stored);
        $this->assertSame('private clinical bytes', $store->get($grant, $context, 'member-view', 'object.read'));

        $otherOperation = new AuthenticatedContext(
            actorId: $context->actorId,
            operationId: new CorrelationId('operation-2'),
            sessionId: $context->sessionId,
            roles: $context->roles,
            permissions: $context->permissions,
            siteId: $context->siteId,
            caseId: $context->caseId,
            purpose: 'object.read',
        );
        try {
            $store->get($grant, $otherOperation, 'member-view', 'object.read');
            $this->fail('An access grant must not cross operation boundaries.');
        } catch (ObjectAccessException) {
            $this->addToAssertionCount(1);
        }

        $wrongPurpose = $this->context('wrong-purpose');
        $this->expectException(ObjectAccessException::class);
        $store->get($grant, $wrongPurpose, 'member-view', 'object.read');
    }

    public function test_access_grants_reject_expiry_mutation_and_wrong_target(): void
    {
        $key = KeyMaterial::from(str_repeat('g', 32));
        $issued = new DateTimeImmutable('2026-08-04T10:00:00+00:00');
        $grant = AccessGrant::issue(
            'private-object:objects/one',
            'actor-1',
            'member-view',
            'object.read',
            $issued,
            $issued->modify('+1 minute'),
            'operation-1',
            $key,
        );
        $context = $this->context('object.read');

        $grant->verify($context, 'member-view', 'object.read', 'private-object:objects/one', $issued->modify('+30 seconds'), $key);

        $this->expectException(ObjectAccessException::class);
        $grant->verify($context, 'member-view', 'object.read', 'private-object:objects/one', $issued->modify('+2 minutes'), $key);
    }

    public function test_transactional_row_lock_and_funding_policy_fail_closed(): void
    {
        $probeId = DB::table('idempotent_consumptions')->insertGetId([
            'message_id' => 'security-lock',
            'consumer' => 'security-source',
            'payload_hash' => hash('sha256', 'security-lock'),
            'status' => 'pending',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = (new TransactionalRowLock)->run(
            'idempotent_consumptions',
            $probeId,
            $this->context('quota.reserve'),
            static function (object $row) use ($probeId): int {
                DB::table('idempotent_consumptions')->where('id', $probeId)->update(['attempts' => $row->attempts + 1]);

                return $row->attempts + 1;
            },
        );

        $this->assertSame(2, $result);
        $this->assertSame(FundingSource::BusinessReserved, FundingPolicy::assertAllowed('b2b', 'business_reserved'));
        $this->assertSame(FundingSource::Personal, FundingPolicy::assertAllowed('b2c', FundingSource::Personal));

        $this->expectException(FundingPolicyException::class);
        FundingPolicy::assertAllowed('b2b', 'personal');
    }

    public function test_row_lock_requires_trusted_context(): void
    {
        $this->expectException(TransactionException::class);
        (new TransactionalRowLock)->run('idempotent_consumptions', '999999999', AuthenticatedContext::anonymous(), static fn (): null => null);
    }

    public function test_external_adapter_execution_requires_authentication_and_audits_without_credentials(): void
    {
        $context = $this->context('adapter.call');
        $adapter = new class implements AuthenticatedExternalAdapter
        {
            public function identity(): string
            {
                return 'fake.adapter';
            }

            public function audience(): string
            {
                return 'future-service';
            }

            public function credential(): ?string
            {
                return 'SYNTHETIC_CREDENTIAL_VALUE';
            }
        };
        $result = (new ExternalAdapterExecutor(app(AuditStore::class), app('App\\Shared\\Time\\Clock')))->execute(
            $adapter,
            $context,
            'future-service',
            ['request_kind' => 'probe'],
            static fn (string $credential): string => $credential,
        );

        $this->assertTrue($result->completed);
        $this->assertSame('completed', $result->classification);
        $this->assertDatabaseHas('audit_events', ['action' => 'external-adapter.attempt']);
        $this->assertStringNotContainsString('SYNTHETIC_CREDENTIAL_VALUE', json_encode(DB::table('audit_events')->get()->all()));
    }

    public function test_caller_claims_cannot_replace_trusted_actor(): void
    {
        $provider = $this->bindContext('attendance.read');
        $guard = new AuthorizationGuard($provider);

        $this->expectException(AuthorizationException::class);
        $guard->authorizeClaims(['actor_id' => 'attacker'], 'attendance.read');
    }

    public function test_caller_claims_cannot_replace_trusted_scope(): void
    {
        $guard = new AuthorizationGuard($this->bindContext('attendance.read'));

        foreach ([
            ['site_id' => 'other-site'],
            ['case_id' => 'other-case'],
            ['assignment_id' => 'assignment-1'],
            ['role' => 'administrator'],
            ['permission' => 'member.delete'],
        ] as $claims) {
            try {
                $guard->authorizeClaims($claims, 'attendance.read');
                $this->fail('Untrusted authorization claims must be rejected.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_operator_projection_requires_scope_and_masks_identifiers(): void
    {
        $projection = OperatorMemberProjection::fromTrustedContext(
            ['member_id' => 'member-1', 'display_name' => 'Member', 'masked_identifier' => '********0003'],
            $this->context('attendance.read'),
            'attendance.read',
        );

        $this->assertSame(['member_id' => 'member-1', 'display_name' => 'Member', 'masked_identifier' => '********0003'], $projection->toArray());

        $this->expectException(MemberProjectionException::class);
        OperatorMemberProjection::fromTrustedContext(
            ['masked_identifier' => 'full-identifier-value'],
            $this->context('attendance.read'),
            'attendance.read',
        );
    }

    private function credentialVerifier(): CredentialVerifier
    {
        return new CredentialVerifier(
            new ProtectedIdentifierService(
                new Encrypter(str_repeat('e', 32), 'AES-256-CBC'),
                KeyMaterial::from(str_repeat('i', 32)),
            ),
            app(AuditStore::class),
            $this->bindContext('credential.verify'),
            app('App\\Shared\\Time\\Clock'),
        );
    }

    private function requestFrom(string $origin, ?string $forwarded = null): void
    {
        $server = ['REMOTE_ADDR' => $origin];
        if ($forwarded !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwarded;
        }

        $this->app->instance('request', Request::create('/', 'POST', [], [], [], $server));
    }

    private function bindContext(string $purpose): AuthenticatedContextProvider
    {
        $provider = new class($this->context($purpose)) implements AuthenticatedContextProvider
        {
            public function __construct(private readonly AuthenticatedContext $context) {}

            public function current(): AuthenticatedContext
            {
                return $this->context;
            }
        };
        $this->app->instance(AuthenticatedContextProvider::class, $provider);

        return $provider;
    }

    private function context(string $purpose): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: LocalId::fromString('actor-1'),
            operationId: new CorrelationId('operation-1'),
            sessionId: LocalId::fromString('session-1'),
            roles: ['operator'],
            permissions: ['attendance.read'],
            siteId: LocalId::fromString('site-1'),
            caseId: LocalId::fromString('case-1'),
            purpose: $purpose,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function auditEvent(string $id, array $metadata = []): AuditEvent
    {
        $context = $this->context('audit');

        return new AuditEvent(
            eventId: $id,
            eventVersion: 1,
            actorId: $context->actorId,
            sessionId: $context->sessionId,
            roles: $context->roles,
            permissions: $context->permissions,
            siteId: $context->siteId,
            caseId: $context->caseId,
            targetType: null,
            targetId: null,
            action: 'security.test',
            previousStateDigest: null,
            newStateDigest: null,
            reason: null,
            occurredAt: new DateTimeImmutable('2026-08-04T10:00:00+00:00'),
            recordedAt: new DateTimeImmutable('2026-08-04T10:00:00+00:00'),
            correlationId: 'operation-1',
            source: 'test',
            outcome: 'observed',
            metadata: $metadata,
        );
    }

    private function event(string $id): VersionedDomainEvent
    {
        return new VersionedDomainEvent(
            id: LocalId::fromString($id),
            name: 'security.test',
            version: 1,
            time: new DateTimeImmutable('2026-08-04T10:00:00+00:00'),
            data: ['status' => 'test'],
        );
    }
}
