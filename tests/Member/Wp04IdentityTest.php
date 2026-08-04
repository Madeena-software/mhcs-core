<?php

declare(strict_types=1);

namespace Tests\Member;

use App\Models\User;
use App\Modules\Member\Application\Data\AssistedRecoveryData;
use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Application\Services\AccountStateService;
use App\Modules\Member\Application\Services\Age17TransitionService;
use App\Modules\Member\Application\Services\AssistedRecoveryService;
use App\Modules\Member\Application\Services\MandatoryPasswordReplacementService;
use App\Modules\Member\Application\Services\MemberGuardianService;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Modules\Member\Application\Services\MemberVerificationAssetService;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\CredentialVerifier;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class Wp04IdentityTest extends TestCase
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
        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-08-04T10:00:00+00:00')));
        Storage::fake('local');
    }

    public function test_registration_separates_auth_from_member_identity_and_supports_email_or_nik_login(): void
    {
        $admin = User::factory()->create();
        $context = $this->bindContext($admin, 'member.registration');
        $nik = '900000000001';
        $kk = '9900000000000001';
        $result = $this->registerAdult($admin, $context, $nik, $kk, 'adult@example.test', 'adult-password');

        $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $result->memberId);
        $this->assertMatchesRegularExpression('/\A[0-9a-f-]{36}\z/', $result->userId);
        $this->assertNotSame($nik, $result->medicalRecordNumber);
        $this->assertDatabaseHas('members', [
            'id' => $result->memberId,
            'user_id' => $result->userId,
            'nik_lookup_digest' => app('App\\Shared\\Security\\ProtectedIdentifierService')->lookupDigest($nik),
            'identity_status' => 'verified',
            'registration_source' => 'administrator',
        ]);
        $this->assertDatabaseHas('families', [
            'family_card_lookup_digest' => app('App\\Shared\\Security\\ProtectedIdentifierService')->lookupDigest($kk),
        ]);
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('users', 'name'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('users', 'identifier_digest'));
        $this->assertSame(1, DB::table('members')->where('user_id', $result->userId)->count());

        $loginContext = $this->bindContext(User::query()->findOrFail($result->userId), 'credential.verify');
        $verifier = app(CredentialVerifier::class);
        $this->assertTrue($verifier->verify('adult@example.test', 'adult-password')->authenticated);
        $this->assertTrue($verifier->verify($nik, 'adult-password')->authenticated);
        $this->assertFalse($verifier->verify($kk, 'adult-password')->authenticated);
        $this->assertFalse($verifier->verify('unknown@example.test', 'wrong-password')->authenticated);
        $this->assertSame(
            $verifier->verify('adult@example.test', 'wrong-password')->message,
            $verifier->verify('unknown@example.test', 'wrong-password')->message,
        );
        $this->assertNotNull($loginContext);

        $audit = json_encode(DB::table('audit_events')->get()->all(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($nik, $audit);
        $this->assertStringNotContainsString($kk, $audit);

        $this->bindContext($admin, 'member.registration');
        $replay = app(MemberRegistrationService::class)->register($this->adultData('registration-1', $nik, $kk, $context, 'adult-password'));
        $this->assertTrue($replay->replayed);
        $this->assertSame(1, DB::table('members')->count());
    }

    public function test_assets_preserve_replacement_history_and_only_grant_private_objects(): void
    {
        $admin = User::factory()->create();
        $context = $this->bindContext($admin, 'member.registration');
        $result = $this->registerAdult($admin, $context, '900000000002', '9900000000000002', null, 'adult-password');
        $oldPhoto = DB::table('member_verification_assets')->where('member_id', $result->memberId)->where('type', 'profile_photo')->first();
        $member = Member::query()->findOrFail($result->memberId);

        $newContext = $this->bindContext($admin, 'member.registration');
        $newPhoto = $this->object($newContext, 'replacement-profile');
        app(MemberVerificationAssetService::class)->recordInTransaction(
            $member,
            new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $newPhoto, 'image/jpeg', $oldPhoto->id),
            $newContext,
            true,
        );

        $this->assertDatabaseHas('member_verification_assets', ['id' => $oldPhoto->id, 'is_current' => false]);
        $current = DB::table('member_verification_assets')
            ->where('member_id', $result->memberId)
            ->where('type', 'profile_photo')
            ->where('is_current', true)
            ->first();
        $this->assertNotNull($current);
        $this->assertSame($oldPhoto->id, $current->replaces_id);

        $readContext = $this->bindContext($admin, 'member.asset.read');
        $assets = app(MemberVerificationAssetService::class);
        $grant = $assets->grant($current->id, 'member-test', 'member.asset.read', new DateTimeImmutable('2026-08-04T10:01:00+00:00'));
        $this->assertSame('synthetic-replacement-profile', $assets->retrieve($grant, 'member-test', 'member.asset.read'));
        $stored = Storage::disk('local')->get($newPhoto->key);
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('synthetic-replacement-profile', $stored);
        $this->assertNotNull($readContext);
    }

    public function test_child_registration_requires_verified_guardian_and_age_transition_ends_access_atomically(): void
    {
        $admin = User::factory()->create();
        $adultContext = $this->bindContext($admin, 'member.registration');
        $adult = $this->registerAdult($admin, $adultContext, '900000000003', '9900000000000003', 'guardian@example.test', 'guardian-password');
        $childContext = $this->bindContext($admin, 'member.registration');
        $childData = new MemberRegistrationData(
            operationId: 'child-registration-1',
            email: null,
            password: null,
            name: 'Synthetic Child',
            birthDate: new DateTimeImmutable('2010-08-05'),
            administrativeGender: 'unspecified',
            nik: '900000000004',
            kk: '9900000000000003',
            phone: null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Kia, $this->object($childContext, 'child-kia'), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->object($childContext, 'child-profile'), 'image/jpeg'),
            guardianMemberIds: [$adult->memberId],
        );
        $child = app(MemberRegistrationService::class)->register($childData);
        $childUser = User::query()->findOrFail($child->userId);
        $this->assertFalse($childUser->login_enabled);
        $this->assertFalse($childUser->canAuthenticate());
        $relation = DB::table('member_guardians')->where('child_member_id', $child->memberId)->first();
        $this->assertNotNull($relation);

        $guardianUser = User::query()->findOrFail($adult->userId);
        $guardianContext = $this->bindContext($guardianUser, 'member.dependent-access');
        $authorized = app(MemberGuardianService::class)->authorizeDependent($child->memberId);
        $this->assertSame($adult->memberId, $authorized->actingGuardianMemberId);
        $this->assertSame($child->memberId, $authorized->dependentMemberId);
        $this->assertNotNull($guardianContext);

        DB::table('members')->where('id', $child->memberId)->update(['birth_date' => '2008-08-03']);
        $childModel = Member::query()->findOrFail($child->memberId);
        $transitionContext = $this->bindContext($admin, 'member.registration');
        $ktp = $this->object($transitionContext, 'child-ktp');
        app(MemberVerificationAssetService::class)->recordInTransaction(
            $childModel,
            new VerificationAssetInput(VerificationAssetType::Ktp, $ktp, 'image/jpeg'),
            $transitionContext,
            true,
        );

        $transitionContext = $this->bindContext($admin, 'member.age-transition');
        $transition = app(Age17TransitionService::class)->transition($child->memberId, 'age-transition-1');
        $this->assertNotNull($transition->temporaryCredential);
        $this->assertSame('active', User::query()->findOrFail($child->userId)->account_status);
        $this->assertTrue(User::query()->findOrFail($child->userId)->must_change_password);
        $this->assertDatabaseHas('member_guardians', ['id' => $relation->id, 'status' => 'ended']);
        $this->assertDatabaseHas('member_verification_assets', ['member_id' => $child->memberId, 'type' => 'kia', 'is_current' => false]);

        $replayed = app(Age17TransitionService::class)->transition($child->memberId, 'age-transition-1');
        $this->assertTrue($replayed->replayed);
        $this->assertNull($replayed->temporaryCredential);

        $replacementContext = $this->bindContext($childUser, 'member.password-replacement');
        app(MandatoryPasswordReplacementService::class)->replace($child->userId, $transition->temporaryCredential, 'child-password', 'password-replacement-1');
        $childUser->refresh();
        $this->assertFalse($childUser->must_change_password);
        $this->assertTrue(Hash::check('child-password', $childUser->password));
        $this->assertNotNull($replacementContext);

        $this->bindContext($childUser, 'member.dependent-access');
        $this->expectException(MemberIdentityException::class);
        app(MemberGuardianService::class)->authorizeDependent($child->memberId);
    }

    public function test_assisted_recovery_requires_protected_evidence_preserves_suspension_and_returns_credential_once(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $member = $this->registerAdult($admin, $registrationContext, '900000000005', '9900000000000005', null, 'old-password');
        $identity = DB::table('member_verification_assets')->where('member_id', $member->memberId)->whereIn('type', ['ktp', 'kia'])->where('is_current', true)->first();
        $profile = DB::table('member_verification_assets')->where('member_id', $member->memberId)->where('type', 'profile_photo')->where('is_current', true)->first();

        $stateContext = $this->bindContext($admin, 'member.account-state');
        app(AccountStateService::class)->suspend($member->userId, 'synthetic recovery test');
        $this->assertNotNull($stateContext);

        $recoveryContext = $this->bindContext($admin, 'member.assisted-recovery');
        $data = new AssistedRecoveryData('recovery-1', '900000000005', '9900000000000005', $identity->id, $profile->id);
        $result = app(AssistedRecoveryService::class)->recover($data);
        $this->assertNotNull($result->temporaryCredential);
        $this->assertSame('suspended', $result->accountStatus);
        $recoveredUser = User::query()->findOrFail($member->userId);
        $this->assertTrue(Hash::check($result->temporaryCredential, $recoveredUser->password));
        $this->assertTrue($recoveredUser->must_change_password);

        $replay = app(AssistedRecoveryService::class)->recover($data);
        $this->assertTrue($replay->replayed);
        $this->assertNull($replay->temporaryCredential);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'member.assisted-recovery')->where('outcome', 'success')->count());

        try {
            app(AssistedRecoveryService::class)->recover(new AssistedRecoveryData('recovery-2', '900000000005', '9900000000000000', $identity->id, $profile->id));
            $this->fail('Mismatched family evidence must fail.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, DB::table('audit_events')->where('action', 'member.assisted-recovery')->where('outcome', 'rejected')->count());
        $this->assertNotNull($recoveryContext);
    }

    public function test_duplicate_nik_rolls_back_member_audit_and_operation_state_and_immutable_identity_fields_reject_mutation(): void
    {
        $admin = User::factory()->create();
        $context = $this->bindContext($admin, 'member.registration');
        $first = $this->registerAdult($admin, $context, '900000000006', '9900000000000006', 'duplicate@example.test', 'adult-password');
        $membersBefore = DB::table('members')->count();
        $usersBefore = DB::table('users')->count();
        $operationsBefore = DB::table('member_operations')->count();
        $auditBefore = DB::table('audit_events')->count();

        try {
            app(MemberRegistrationService::class)->register($this->adultData('duplicate-registration', '900000000006', '9900000000000006', $context, 'adult-password', 'other@example.test'));
            $this->fail('Duplicate NIK must be rejected.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($membersBefore, DB::table('members')->count());
        $this->assertSame($usersBefore, DB::table('users')->count());
        $this->assertSame($operationsBefore, DB::table('member_operations')->count());
        $this->assertSame($auditBefore, DB::table('audit_events')->count());

        $member = Member::query()->findOrFail($first->memberId);
        $member->medical_record_number = (string) Str::uuid();
        $this->expectException(MemberIdentityException::class);
        $member->save();
    }

    private function registerAdult(User $admin, AuthenticatedContext $context, string $nik, string $kk, ?string $email, string $password): object
    {
        return app(MemberRegistrationService::class)->register($this->adultData('registration-1', $nik, $kk, $context, $password, $email));
    }

    private function adultData(string $operationId, string $nik, string $kk, AuthenticatedContext $context, string $password, ?string $email = 'adult@example.test'): MemberRegistrationData
    {
        return new MemberRegistrationData(
            operationId: $operationId,
            email: $email,
            password: $password,
            name: 'Synthetic Adult',
            birthDate: new DateTimeImmutable('1985-08-04'),
            administrativeGender: 'unspecified',
            nik: $nik,
            kk: $kk,
            phone: null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Ktp, $this->object($context, 'ktp-'.$nik), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->object($context, 'profile-'.$nik), 'image/jpeg'),
            externalIdentifiers: [['namespace' => 'synthetic.test', 'value' => 'patient-'.$nik]],
        );
    }

    private function object(AuthenticatedContext $context, string $label): PrivateObject
    {
        return app(PrivateObjectStore::class)->put('synthetic-'.$label, $context, (string) $context->purpose);
    }

    /** @param list<string>|null $roles */
    private function bindContext(User $user, string $purpose, ?array $roles = null): AuthenticatedContext
    {
        $roles ??= ['administrator'];
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $user->id),
            operationId: new CorrelationId('operation-'.strtolower(str_replace('-', '', (string) Str::uuid()))),
            sessionId: LocalId::fromString('session-'.(string) $user->id),
            roles: $roles,
            permissions: ['member.identity.manage', 'member.identity.verify', 'member.account.manage'],
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

        return $context;
    }
}
