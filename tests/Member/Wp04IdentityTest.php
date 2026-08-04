<?php

declare(strict_types=1);

namespace Tests\Member;

use App\Models\User;
use App\Modules\Member\Application\Data\AssistedRecoveryData;
use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Application\Services\AccountStateService;
use App\Modules\Member\Application\Services\AdultActivationService;
use App\Modules\Member\Application\Services\Age17TransitionService;
use App\Modules\Member\Application\Services\AssistedRecoveryService;
use App\Modules\Member\Application\Services\MandatoryPasswordReplacementService;
use App\Modules\Member\Application\Services\MemberAuthorization;
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
            'mhcs.security.asset_grants' => [
                'max_ttl_seconds' => 300,
                'audiences' => ['member-view'],
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
        app(MemberVerificationAssetService::class)->recordForRegistration(
            $member,
            new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $newPhoto, 'image/jpeg', $oldPhoto->id),
            $newContext,
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
        $grant = $assets->grant($current->id, 'member-view', 'member.asset.read', new DateTimeImmutable('2026-08-04T10:01:00+00:00'));
        $this->assertSame('synthetic-replacement-profile', $assets->retrieve($grant, 'member-view', 'member.asset.read'));
        $stored = Storage::disk('local')->get($newPhoto->key);
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('synthetic-replacement-profile', $stored);
        $this->assertNotNull($readContext);
    }

    public function test_online_adult_registration_uses_trusted_non_administrator_boundary_and_stays_pending(): void
    {
        $registrant = User::factory()->create();
        $context = $this->bindContext(
            $registrant,
            'member.registration',
            ['member'],
            [MemberAuthorization::ONLINE_REGISTRATION_PERMISSION],
        );

        $result = app(MemberRegistrationService::class)->register($this->adultData(
            'online-adult-registration',
            '900000000020',
            '9900000000000020',
            $context,
            'online-password',
            'online@example.test',
            RegistrationSource::Online,
        ));

        $this->assertSame('pending_activation', $result->accountStatus);
        $this->assertSame('pending_verification', $result->identityStatus);
        $this->assertSame((string) $registrant->id, $result->userId);
        $this->assertSame(1, User::query()->count());
        $this->assertFalse(User::query()->findOrFail($result->userId)->canAuthenticate());
        $this->assertSame(
            2,
            DB::table('member_verification_assets')
                ->where('member_id', $result->memberId)
                ->where('uploaded_by_user_id', $registrant->id)
                ->count(),
        );

        try {
            app(MemberRegistrationService::class)->register($this->adultData(
                'online-bound-account',
                '900000000025',
                '9900000000000025',
                $context,
                'ignored-password',
                'ignored@example.test',
                RegistrationSource::Online,
            ));
            $this->fail('An existing Member-bound account cannot complete another online registration.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_registration_assets_reject_forged_administrator_context_before_mutation(): void
    {
        $admin = User::factory()->create();
        $trustedContext = $this->bindContext($admin, 'member.registration');
        $result = $this->registerAdult($admin, $trustedContext, '900000000030', '9900000000000030', null, 'adult-password');
        $member = Member::query()->findOrFail($result->memberId);
        $old = DB::table('member_verification_assets')
            ->where('member_id', $member->id)
            ->where('type', 'profile_photo')
            ->where('is_current', true)
            ->first();
        $assetCount = DB::table('member_verification_assets')->count();
        $auditCount = DB::table('audit_events')->count();

        $lowPrivilegeActor = User::factory()->create();
        $lowPrivilegeContext = $this->bindContext(
            $lowPrivilegeActor,
            'member.registration',
            ['member'],
            [MemberAuthorization::ONLINE_REGISTRATION_PERMISSION],
        );
        $forgedAdministratorContext = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $admin->id),
            operationId: new CorrelationId('forged-administrator-operation'),
            sessionId: LocalId::fromString('forged-administrator-session'),
            roles: ['administrator'],
            permissions: [MemberAuthorization::REGISTRATION_PERMISSION, MemberAuthorization::IDENTITY_VERIFICATION_PERMISSION],
            purpose: 'member.registration',
        );

        try {
            app(MemberVerificationAssetService::class)->recordForRegistration(
                $member,
                new VerificationAssetInput(
                    VerificationAssetType::ProfilePhoto,
                    $this->object($lowPrivilegeContext, 'forged-administrator-asset'),
                    'image/jpeg',
                    $old->id,
                ),
                $forgedAdministratorContext,
            );
            $this->fail('A forged administrator context must not authorize asset recording.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($assetCount, DB::table('member_verification_assets')->count());
        $this->assertSame($auditCount, DB::table('audit_events')->count());
        $this->assertDatabaseHas('member_verification_assets', ['id' => $old->id, 'is_current' => true]);
    }

    public function test_adult_activation_requires_complete_verified_evidence_is_idempotent_and_rejects_conflicts(): void
    {
        $registrant = User::factory()->create();
        $onlineContext = $this->bindContext(
            $registrant,
            'member.registration',
            ['member'],
            [MemberAuthorization::ONLINE_REGISTRATION_PERMISSION],
        );
        $pending = app(MemberRegistrationService::class)->register($this->adultData(
            'activation-registration',
            '900000000021',
            '9900000000000021',
            $onlineContext,
            'activation-password',
            'activation@example.test',
            RegistrationSource::Online,
        ));

        $admin = User::factory()->create();
        $this->bindContext($admin, 'member.identity.verify');
        $assets = DB::table('member_verification_assets')->where('member_id', $pending->memberId)->get();
        foreach ($assets as $asset) {
            app(MemberVerificationAssetService::class)->review($asset->id, true);
        }

        $this->bindContext($admin, 'member.account-state', ['administrator'], [MemberAuthorization::ACCOUNT_STATE_PERMISSION]);
        $activation = app(AdultActivationService::class)->activate($pending->memberId, 'adult-activation-1');
        $user = User::query()->findOrFail($pending->userId);
        $this->assertSame('active', $activation->accountStatus);
        $this->assertTrue($user->login_enabled);
        $this->assertFalse($activation->mustChangePassword);
        $this->assertTrue(DB::table('audit_events')->where('action', 'member.adult-activation')->exists());

        $activationService = app(AdultActivationService::class);
        $replay = $activationService->activate($pending->memberId, 'adult-activation-1');
        $this->assertTrue($replay->replayed);

        $otherRegistrant = User::factory()->create();
        $otherContext = $this->bindContext(
            $otherRegistrant,
            'member.registration',
            ['member'],
            [MemberAuthorization::ONLINE_REGISTRATION_PERMISSION],
        );
        $other = app(MemberRegistrationService::class)->register($this->adultData(
            'activation-other-registration',
            '900000000022',
            '9900000000000022',
            $otherContext,
            'other-password',
            'other-activation@example.test',
            RegistrationSource::Online,
        ));
        try {
            $activationService->activate($other->memberId, 'adult-activation-1');
            $this->fail('An activation operation cannot be reused for another Member.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_adult_activation_rejects_unauthorized_suspended_child_and_incomplete_cases(): void
    {
        $registrant = User::factory()->create();
        $onlineContext = $this->bindContext(
            $registrant,
            'member.registration',
            ['member'],
            [MemberAuthorization::ONLINE_REGISTRATION_PERMISSION],
        );
        $pending = app(MemberRegistrationService::class)->register($this->adultData(
            'activation-incomplete',
            '900000000023',
            '9900000000000023',
            $onlineContext,
            'activation-password',
            'incomplete@example.test',
            RegistrationSource::Online,
        ));
        $rejected = function (callable $operation): void {
            try {
                $operation();
                $this->fail('The invalid activation case must be rejected.');
            } catch (MemberIdentityException) {
                $this->addToAssertionCount(1);
            }
        };

        $admin = User::factory()->create();
        $this->bindContext($admin, 'member.account-state', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(AdultActivationService::class)->activate($pending->memberId, 'activation-unauthorized'));

        $this->bindContext($admin, 'member.account-state', ['administrator'], [MemberAuthorization::ACCOUNT_STATE_PERMISSION]);
        DB::table('users')->where('id', $pending->userId)->update(['account_status' => 'suspended']);
        $rejected(fn () => app(AdultActivationService::class)->activate($pending->memberId, 'activation-suspended'));

        $this->bindContext($admin, 'member.registration');
        $adult = $this->registerAdult($admin, $this->bindContext($admin, 'member.registration'), '900000000024', '9900000000000024', null, 'guardian-password');
        $childContext = $this->bindContext($admin, 'member.registration');
        $child = app(MemberRegistrationService::class)->register(new MemberRegistrationData(
            operationId: 'activation-child',
            email: null,
            password: null,
            name: 'Activation Child',
            birthDate: new DateTimeImmutable('2012-08-05'),
            administrativeGender: 'unspecified',
            nik: '900000000025',
            kk: '9900000000000024',
            phone: null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Kia, $this->object($childContext, 'activation-child-kia'), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->object($childContext, 'activation-child-profile'), 'image/jpeg'),
            guardianMemberIds: [$adult->memberId],
        ));
        $rejected(fn () => app(AdultActivationService::class)->activate($child->memberId, 'activation-child-operation'));
    }

    public function test_asset_grants_enforce_exact_ttl_and_trusted_audiences(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $member = $this->registerAdult($admin, $registrationContext, '900000000026', '9900000000000026', null, 'adult-password');
        $asset = DB::table('member_verification_assets')->where('member_id', $member->memberId)->where('type', 'profile_photo')->where('is_current', true)->first();
        $this->bindContext($admin, 'member.asset.read', ['administrator'], [MemberAuthorization::ASSET_ACCESS_PERMISSION]);
        $assets = app(MemberVerificationAssetService::class);

        $grant = $assets->grant($asset->id, 'member-view', 'member.asset.read', new DateTimeImmutable('2026-08-04T10:05:00+00:00'));
        $this->assertSame('member-view', $grant->claims()['audience']);

        foreach ([
            ['expires' => '2026-08-04T10:05:01+00:00', 'audience' => 'member-view'],
            ['expires' => '2026-08-04T10:01:00+00:00', 'audience' => 'untrusted-application'],
        ] as $case) {
            try {
                $assets->grant($asset->id, $case['audience'], 'member.asset.read', new DateTimeImmutable($case['expires']));
                $this->fail('An invalid grant boundary must be rejected.');
            } catch (MemberIdentityException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_asset_grants_require_owner_guardian_or_exact_asset_permission(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $owner = $this->registerAdult($admin, $registrationContext, '900000000010', '9900000000000010', 'owner@example.test', 'owner-password');
        $asset = DB::table('member_verification_assets')
            ->where('member_id', $owner->memberId)
            ->where('type', 'profile_photo')
            ->where('is_current', true)
            ->first();
        $expiresAt = new DateTimeImmutable('2026-08-04T10:01:00+00:00');

        $ownerContext = $this->bindContext(User::query()->findOrFail($owner->userId), 'member.asset.read', [], []);
        $assets = app(MemberVerificationAssetService::class);
        $ownerGrant = $assets->grant($asset->id, 'member-view', 'member.asset.read', $expiresAt);
        $this->assertSame('synthetic-profile-900000000010', $assets->retrieve($ownerGrant, 'member-view', 'member.asset.read'));

        $unauthorized = User::factory()->create();
        $this->bindContext($unauthorized, 'member.asset.read', [], []);
        $assets = app(MemberVerificationAssetService::class);
        $this->expectException(MemberIdentityException::class);
        $assets->grant($asset->id, 'member-view', 'member.asset.read', $expiresAt);
        $this->assertNotNull($ownerContext);
    }

    public function test_authorized_guardian_and_exact_asset_permission_can_grant_a_dependent_asset(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $guardian = $this->registerAdult($admin, $registrationContext, '900000000011', '9900000000000011', 'guardian-asset@example.test', 'guardian-password');
        $childContext = $this->bindContext($admin, 'member.registration');
        $child = app(MemberRegistrationService::class)->register(new MemberRegistrationData(
            operationId: 'dependent-asset-registration',
            email: null,
            password: null,
            name: 'Synthetic Dependent',
            birthDate: new DateTimeImmutable('2012-08-05'),
            administrativeGender: 'unspecified',
            nik: '900000000012',
            kk: '9900000000000011',
            phone: null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Kia, $this->object($childContext, 'dependent-kia'), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->object($childContext, 'dependent-profile'), 'image/jpeg'),
            guardianMemberIds: [$guardian->memberId],
        ));
        $asset = DB::table('member_verification_assets')
            ->where('member_id', $child->memberId)
            ->where('type', 'kia')
            ->where('is_current', true)
            ->first();
        $expiresAt = new DateTimeImmutable('2026-08-04T10:01:00+00:00');

        $this->bindContext(User::query()->findOrFail($guardian->userId), 'member.asset.read', [], []);
        $assets = app(MemberVerificationAssetService::class);
        $guardianGrant = $assets->grant($asset->id, 'member-view', 'member.asset.read', $expiresAt);
        $this->assertSame('synthetic-dependent-kia', $assets->retrieve($guardianGrant, 'member-view', 'member.asset.read'));

        $this->bindContext($admin, 'member.asset.read', ['administrator'], ['member.asset.read']);
        $assets = app(MemberVerificationAssetService::class);
        $adminGrant = $assets->grant($asset->id, 'member-view', 'member.asset.read', $expiresAt);
        $this->assertSame('member-view', $adminGrant->claims()['audience']);
    }

    public function test_pending_replacement_keeps_approved_current_until_approval(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $memberResult = $this->registerAdult($admin, $registrationContext, '900000000013', '9900000000000013', null, 'adult-password');
        $member = Member::query()->findOrFail($memberResult->memberId);
        $old = DB::table('member_verification_assets')
            ->where('member_id', $member->id)
            ->where('type', 'profile_photo')
            ->where('is_current', true)
            ->first();
        $replacementContext = $this->bindContext($admin, 'member.registration', ['administrator'], ['member.registration.manage']);
        $replacement = app(MemberVerificationAssetService::class)->recordForRegistration(
            $member,
            new VerificationAssetInput(
                VerificationAssetType::ProfilePhoto,
                $this->object($replacementContext, 'pending-profile'),
                'image/jpeg',
                $old->id,
            ),
            $replacementContext,
        );

        $this->assertDatabaseHas('member_verification_assets', ['id' => $old->id, 'review_status' => 'approved', 'is_current' => true]);
        $this->assertDatabaseHas('member_verification_assets', ['id' => $replacement, 'review_status' => 'pending', 'is_current' => false]);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'identity_status' => 'verified']);

        $this->bindContext($admin, 'member.identity.verify');
        app(MemberVerificationAssetService::class)->review($replacement, true);

        $this->assertDatabaseHas('member_verification_assets', ['id' => $old->id, 'is_current' => false]);
        $this->assertDatabaseHas('member_verification_assets', ['id' => $replacement, 'review_status' => 'approved', 'is_current' => true, 'replaces_id' => $old->id]);
        $this->assertSame(1, DB::table('member_verification_assets')->where('member_id', $member->id)->where('type', 'profile_photo')->where('review_status', 'approved')->where('is_current', true)->count());
    }

    public function test_identity_document_slot_rejects_stale_kia_and_atomically_moves_from_kia_to_ktp(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $guardian = $this->registerAdult($admin, $registrationContext, '900000000027', '9900000000000027', null, 'guardian-password');
        $childContext = $this->bindContext($admin, 'member.registration');
        $child = app(MemberRegistrationService::class)->register(new MemberRegistrationData(
            operationId: 'document-slot-child',
            email: null,
            password: null,
            name: 'Document Slot Child',
            birthDate: new DateTimeImmutable('2010-08-05'),
            administrativeGender: 'unspecified',
            nik: '900000000028',
            kk: '9900000000000027',
            phone: null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Kia, $this->object($childContext, 'document-slot-kia'), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->object($childContext, 'document-slot-profile'), 'image/jpeg'),
            guardianMemberIds: [$guardian->memberId],
        ));
        $oldKia = DB::table('member_verification_assets')->where('member_id', $child->memberId)->where('type', 'kia')->where('is_current', true)->first();
        $pendingContext = $this->bindContext($admin, 'member.registration', ['administrator'], ['member.registration.manage']);
        $pendingKia = app(MemberVerificationAssetService::class)->recordForRegistration(
            Member::query()->findOrFail($child->memberId),
            new VerificationAssetInput(VerificationAssetType::Kia, $this->object($pendingContext, 'document-slot-stale-kia'), 'image/jpeg', $oldKia->id),
            $pendingContext,
        );
        DB::table('members')->where('id', $child->memberId)->update(['birth_date' => '2008-08-03']);

        $this->bindContext($admin, 'member.identity.verify');
        try {
            app(MemberVerificationAssetService::class)->review($pendingKia, true);
            $this->fail('A stale KIA approval after age 17 must fail closed.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseHas('member_verification_assets', ['id' => $oldKia->id, 'is_current' => true]);
        $this->assertDatabaseHas('members', ['id' => $child->memberId, 'identity_document_type' => 'kia']);

        $adultContext = $this->bindContext($admin, 'member.registration', ['administrator'], ['member.registration.manage']);
        $pendingKtp = app(MemberVerificationAssetService::class)->recordForRegistration(
            Member::query()->findOrFail($child->memberId),
            new VerificationAssetInput(VerificationAssetType::Ktp, $this->object($adultContext, 'document-slot-ktp'), 'image/jpeg'),
            $adultContext,
        );
        $this->assertDatabaseHas('members', ['id' => $child->memberId, 'identity_document_type' => 'kia']);

        $this->bindContext($admin, 'member.identity.verify');
        app(MemberVerificationAssetService::class)->review($pendingKtp, true);
        $this->assertDatabaseHas('member_verification_assets', ['id' => $oldKia->id, 'is_current' => false]);
        $this->assertSame(1, DB::table('member_verification_assets')->where('member_id', $child->memberId)->whereIn('type', ['ktp', 'kia'])->where('review_status', 'approved')->where('is_current', true)->count());
        $this->assertDatabaseHas('members', ['id' => $child->memberId, 'identity_document_type' => 'ktp']);
    }

    public function test_asset_recording_rolls_back_demotion_when_insertion_fails(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $result = $this->registerAdult($admin, $registrationContext, '900000000029', '9900000000000029', null, 'adult-password');
        $member = Member::query()->findOrFail($result->memberId);
        $old = DB::table('member_verification_assets')->where('member_id', $member->id)->where('type', 'profile_photo')->where('is_current', true)->first();
        $uploader = User::factory()->create();
        $context = $this->bindContext($uploader, 'member.registration');
        $object = $this->object($context, 'asset-rollback');
        $uploader->delete();
        try {
            app(MemberVerificationAssetService::class)->recordForRegistration(
                $member,
                new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $object, 'image/jpeg', $old->id),
                $context,
            );
            $this->fail('A missing uploader foreign key must fail the asset transaction.');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('member_verification_assets', ['id' => $old->id, 'is_current' => true]);
        $this->assertSame(1, DB::table('member_verification_assets')->where('member_id', $member->id)->where('type', 'profile_photo')->where('is_current', true)->count());
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
        app(MemberVerificationAssetService::class)->recordForRegistration(
            $childModel,
            new VerificationAssetInput(VerificationAssetType::Ktp, $ktp, 'image/jpeg'),
            $transitionContext,
        );

        $transitionContext = $this->bindContext($admin, 'member.age-transition');
        $transition = app(Age17TransitionService::class)->transition($child->memberId, 'age-transition-1');
        $this->assertNotNull($transition->temporaryCredential);
        $this->assertSame('active', User::query()->findOrFail($child->userId)->account_status);
        $this->assertTrue(User::query()->findOrFail($child->userId)->must_change_password);
        $this->assertDatabaseHas('members', ['id' => $child->memberId, 'identity_document_type' => 'ktp']);
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

    public function test_assisted_recovery_rejects_dependent_without_issuing_a_credential(): void
    {
        $admin = User::factory()->create();
        $adultContext = $this->bindContext($admin, 'member.registration');
        $guardian = $this->registerAdult($admin, $adultContext, '900000000014', '9900000000000014', null, 'guardian-password');
        $childContext = $this->bindContext($admin, 'member.registration');
        $child = app(MemberRegistrationService::class)->register(new MemberRegistrationData(
            operationId: 'dependent-recovery-registration',
            email: null,
            password: null,
            name: 'Synthetic Recovery Dependent',
            birthDate: new DateTimeImmutable('2012-08-05'),
            administrativeGender: 'unspecified',
            nik: '900000000015',
            kk: '9900000000000014',
            phone: null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Kia, $this->object($childContext, 'recovery-kia'), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->object($childContext, 'recovery-profile'), 'image/jpeg'),
            guardianMemberIds: [$guardian->memberId],
        ));
        $identity = DB::table('member_verification_assets')->where('member_id', $child->memberId)->where('type', 'kia')->where('is_current', true)->first();
        $profile = DB::table('member_verification_assets')->where('member_id', $child->memberId)->where('type', 'profile_photo')->where('is_current', true)->first();
        $childUser = User::query()->findOrFail($child->userId);
        $passwordHash = $childUser->password;

        $this->bindContext($admin, 'member.assisted-recovery');
        try {
            app(AssistedRecoveryService::class)->recover(new AssistedRecoveryData(
                'dependent-recovery',
                '900000000015',
                '9900000000000014',
                $identity->id,
                $profile->id,
            ));
            $this->fail('Dependent recovery must be rejected.');
        } catch (MemberIdentityException) {
            $this->addToAssertionCount(1);
        }
        $childUser->refresh();
        $this->assertSame($passwordHash, $childUser->password);
        $this->assertTrue($childUser->login_enabled === false);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'member.assisted-recovery')->where('outcome', 'success')->count());
    }

    public function test_identity_verification_permission_cannot_run_unrelated_administrator_operations(): void
    {
        $admin = User::factory()->create();
        $registrationContext = $this->bindContext($admin, 'member.registration');
        $member = $this->registerAdult($admin, $registrationContext, '900000000016', '9900000000000016', null, 'adult-password');
        $asset = DB::table('member_verification_assets')->where('member_id', $member->memberId)->where('type', 'profile_photo')->where('is_current', true)->first();
        $rejected = function (callable $operation): void {
            try {
                $operation();
                $this->fail('A narrow verification permission must not authorize this operation.');
            } catch (MemberIdentityException) {
                $this->addToAssertionCount(1);
            }
        };

        $this->bindContext($admin, 'member.account-state', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(AccountStateService::class)->suspend($member->userId));

        $this->bindContext($admin, 'member.guardian.manage', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(MemberGuardianService::class)->add('missing-child', 'missing-guardian'));

        $this->bindContext($admin, 'member.assisted-recovery', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(AssistedRecoveryService::class)->recover(new AssistedRecoveryData('narrow-recovery', 'nik', 'kk', 'identity', 'profile')));

        $this->bindContext($admin, 'member.age-transition', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(Age17TransitionService::class)->transition($member->memberId, 'narrow-age-transition'));

        $this->bindContext($admin, 'member.registration', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(MemberRegistrationService::class)->register($this->adultData('narrow-registration', '900000000017', '9900000000000017', $registrationContext, 'adult-password')));

        $this->bindContext($admin, 'member.asset.read', ['administrator'], ['member.identity.verify']);
        $rejected(fn () => app(MemberVerificationAssetService::class)->grant($asset->id, 'member-view', 'member.asset.read', new DateTimeImmutable('2026-08-04T10:01:00+00:00')));
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

    private function adultData(
        string $operationId,
        string $nik,
        string $kk,
        AuthenticatedContext $context,
        string $password,
        ?string $email = 'adult@example.test',
        RegistrationSource $source = RegistrationSource::Administrator,
    ): MemberRegistrationData {
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
            registrationSource: $source,
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
    private function bindContext(User $user, string $purpose, ?array $roles = null, ?array $permissions = null): AuthenticatedContext
    {
        $roles ??= ['administrator'];
        $permissions ??= [
            'member.registration.manage',
            'member.identity.verify',
            'member.asset.read',
            'member.guardian.manage',
            'member.account.manage',
            'member.assisted-recovery',
            'member.age-transition',
        ];
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $user->id),
            operationId: new CorrelationId('operation-'.strtolower(str_replace('-', '', (string) Str::uuid()))),
            sessionId: LocalId::fromString('session-'.(string) $user->id),
            roles: $roles,
            permissions: $permissions,
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
