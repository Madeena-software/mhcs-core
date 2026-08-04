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
use App\Shared\Security\SensitivePayloadException;
use App\Shared\Security\TemporaryCredentialIssuer;
use App\Shared\Storage\AccessGrant;
use App\Shared\Storage\EncryptedLocalObjectStore;
use App\Shared\Storage\ObjectAccessException;
use App\Shared\Time\FrozenClock;
use App\Shared\Transactions\TransactionalRowLock;
use App\Shared\Transactions\TransactionException;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        $active = User::factory()->create([
            'email' => 'auth-active@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->assertFalse(Auth::attempt(['email' => $suspended->email, 'password' => 'correct-password']));
        $this->assertFalse(Auth::attempt(['email' => $temporary->email, 'password' => 'correct-password']));
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

    public function test_audit_and_outbox_follow_local_transaction_rollback(): void
    {
        Schema::create('security_transaction_probe', function (Blueprint $table): void {
            $table->string('id')->primary();
        });

        try {
            DB::transaction(function (): never {
                DB::table('security_transaction_probe')->insert(['id' => 'rollback']);
                app(AuditStore::class)->append($this->auditEvent('audit-rollback'));
                app(OutboxStore::class)->record($this->event('event-rollback'));
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseMissing('security_transaction_probe', ['id' => 'rollback']);
        $this->assertDatabaseMissing('audit_events', ['event_id' => 'audit-rollback']);
        $this->assertDatabaseMissing('outbox_messages', ['event_id' => 'event-rollback']);
        Schema::dropIfExists('security_transaction_probe');
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
        $this->assertSame('private clinical bytes', $store->get($grant, $otherOperation, 'member-view', 'object.read'));

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
        Schema::create('security_lock_probe', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->integer('value');
        });
        DB::table('security_lock_probe')->insert(['id' => 'one', 'value' => 1]);

        $result = (new TransactionalRowLock)->run(
            'security_lock_probe',
            'one',
            $this->context('quota.reserve'),
            static function (object $row): int {
                DB::table('security_lock_probe')->where('id', 'one')->update(['value' => $row->value + 1]);

                return $row->value + 1;
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
        Schema::create('security_lock_probe', function (Blueprint $table): void {
            $table->string('id')->primary();
        });
        DB::table('security_lock_probe')->insert(['id' => 'one']);

        $this->expectException(TransactionException::class);
        (new TransactionalRowLock)->run('security_lock_probe', 'one', AuthenticatedContext::anonymous(), static fn (): null => null);
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
