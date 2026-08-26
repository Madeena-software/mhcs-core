<?php

declare(strict_types=1);

namespace Tests\Member;

use App\Models\User;
use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\NonclinicalValidationMemberRegistrationData;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Application\Services\MemberContextResolver;
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
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use App\Shared\Validation\NonclinicalValidationContext;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NonclinicalValidationMemberIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mhcs.security.identifier_key' => str_repeat('i', 32),
            'mhcs.security.grant_key' => str_repeat('g', 32),
        ]);
        $this->app->instance(Clock::class, new FrozenClock(new DateTimeImmutable('2026-08-04T10:00:00+00:00')));
    }

    public function test_fixed_nonclinical_registration_creates_a_null_identity_and_unique_marker(): void
    {
        $user = User::factory()->create();
        $this->bindValidationContext($user);

        $result = app(MemberRegistrationService::class)->registerNonclinicalValidation(
            new NonclinicalValidationMemberRegistrationData(
                operationId: 'real-npz-e2e-v1-'.$user->id,
                userId: (string) $user->id,
            ),
        );

        $member = DB::table('members')->where('id', $result->memberId)->first();

        $this->assertSame('nonclinical_validation', $member->identity_status);
        $this->assertSame('nonclinical_validation', $member->registration_source);
        $this->assertNull($member->identity_document_type);
        $this->assertNull($member->encrypted_nik);
        $this->assertNull($member->nik_lookup_digest);
        $this->assertSame(0, DB::table('member_verification_assets')->where('member_id', $member->id)->count());
        $this->assertDatabaseHas('member_external_identifiers', [
            'member_id' => $member->id,
            'namespace' => 'mhcs.validation',
            'value' => 'real-npz-e2e-v1',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'member.nonclinical-validation.registered',
            'target_id' => $member->id,
        ]);

        $replay = app(MemberRegistrationService::class)->registerNonclinicalValidation(
            new NonclinicalValidationMemberRegistrationData(
                operationId: 'real-npz-e2e-v1-'.$user->id,
                userId: (string) $user->id,
            ),
        );

        $this->assertTrue($replay->replayed);
        $this->assertSame(1, DB::table('members')->count());
        $this->assertSame(1, DB::table('member_external_identifiers')->where('namespace', 'mhcs.validation')->where('value', 'real-npz-e2e-v1')->count());
    }

    public function test_only_the_exact_validation_identity_is_booking_identity_eligible(): void
    {
        $user = User::factory()->create();
        $this->bindValidationContext($user);
        $result = app(MemberRegistrationService::class)->registerNonclinicalValidation(new NonclinicalValidationMemberRegistrationData('validation-policy-'.$user->id, (string) $user->id));
        $member = Member::query()->findOrFail($result->memberId);

        $this->assertTrue(app(MemberContextResolver::class)->isIdentityEligibleForBooking($member));

        DB::table('member_external_identifiers')->where('member_id', $member->id)->update(['value' => 'wrong-context']);
        $member->refresh();
        $this->assertFalse(app(MemberContextResolver::class)->isIdentityEligibleForBooking($member));
    }

    public function test_normal_registration_cannot_request_the_nonclinical_source(): void
    {
        $user = User::factory()->create();
        $context = $this->bindValidationContext($user);

        $this->expectException(MemberIdentityException::class);
        app(MemberRegistrationService::class)->register(new MemberRegistrationData(
            operationId: 'ordinary-request-validation-'.$user->id,
            email: null,
            password: 'not-used',
            name: 'ordinary request',
            birthDate: new DateTimeImmutable('1985-08-04'),
            administrativeGender: 'unspecified',
            nik: '900000000099',
            kk: null,
            phone: null,
            registrationSource: RegistrationSource::NonclinicalValidation,
            identityDocument: new VerificationAssetInput(VerificationAssetType::Ktp, $this->privateObject('ordinary-ktp'), 'image/jpeg'),
            profilePhoto: new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->privateObject('ordinary-photo'), 'image/jpeg'),
        ));
    }

    public function test_validation_members_are_rejected_by_the_genuine_asset_boundary(): void
    {
        $user = User::factory()->create();
        $this->bindValidationContext($user);
        $result = app(MemberRegistrationService::class)->registerNonclinicalValidation(new NonclinicalValidationMemberRegistrationData('validation-assets-'.$user->id, (string) $user->id));
        $member = Member::query()->findOrFail($result->memberId);
        $this->bindAdministratorRegistrationContext($user);

        $this->expectException(MemberIdentityException::class);
        app(MemberVerificationAssetService::class)->recordForRegistration(
            $member,
            new VerificationAssetInput(VerificationAssetType::ProfilePhoto, $this->privateObject('validation-photo'), 'image/jpeg'),
            app(AuthenticatedContextProvider::class)->current(),
        );
    }

    public function test_shared_validation_context_is_canonical_and_member_code_has_no_split_marker(): void
    {
        $this->assertSame('real-npz-e2e-v1', NonclinicalValidationContext::KEY);
        $this->assertSame('mhcs.validation', NonclinicalValidationContext::MARKER_NAMESPACE);

        $source = file_get_contents(app_path('Modules/Member/Application/Services/MemberContextResolver.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString("'real-n'.'pz", $source);
        $this->assertStringNotContainsString('real-npz-e2e-v1', $source);
    }

    public function test_identity_synchronization_preserves_exact_validation_and_rejects_one_sided_signals(): void
    {
        $user = User::factory()->create();
        $this->bindValidationContext($user);
        $result = app(MemberRegistrationService::class)->registerNonclinicalValidation(new NonclinicalValidationMemberRegistrationData('validation-sync-'.$user->id, (string) $user->id));
        $sync = new \ReflectionMethod(MemberVerificationAssetService::class, 'syncIdentityStatus');
        $sync->setAccessible(true);

        $sync->invoke(app(MemberVerificationAssetService::class), $result->memberId);
        $this->assertDatabaseHas('members', [
            'id' => $result->memberId,
            'identity_status' => 'nonclinical_validation',
            'registration_source' => 'nonclinical_validation',
        ]);

        foreach ([
            ['identity_status' => 'verified', 'registration_source' => 'nonclinical_validation'],
            ['identity_status' => 'pending_verification', 'registration_source' => 'nonclinical_validation'],
            ['identity_status' => 'nonclinical_validation', 'registration_source' => 'online'],
        ] as $inconsistent) {
            DB::table('members')->where('id', $result->memberId)->update($inconsistent);

            try {
                $sync->invoke(app(MemberVerificationAssetService::class), $result->memberId);
                $this->fail('Inconsistent validation identity signals must fail closed.');
            } catch (\ReflectionException $exception) {
                throw $exception;
            } catch (MemberIdentityException) {
                $this->addToAssertionCount(1);
            }

            DB::table('members')->where('id', $result->memberId)->update([
                'identity_status' => 'nonclinical_validation',
                'registration_source' => 'nonclinical_validation',
            ]);
        }
    }

    public function test_booking_identity_policy_rejects_each_genuine_identity_field_and_asset_on_validation_member(): void
    {
        $user = User::factory()->create();
        $this->bindValidationContext($user);
        $result = app(MemberRegistrationService::class)->registerNonclinicalValidation(new NonclinicalValidationMemberRegistrationData('validation-fields-'.$user->id, (string) $user->id));

        foreach (['identity_document_type', 'encrypted_nik', 'nik_lookup_digest'] as $field) {
            DB::table('members')->where('id', $result->memberId)->update([$field => 'contradictory']);
            $this->assertFalse(app(MemberContextResolver::class)->isIdentityEligibleForBooking(Member::query()->findOrFail($result->memberId)));
            DB::table('members')->where('id', $result->memberId)->update([$field => null]);
        }

        DB::table('member_verification_assets')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $result->memberId,
            'type' => 'profile_photo',
            'private_object_key' => 'objects/contradictory',
            'checksum' => hash('sha256', 'contradictory'),
            'bytes' => 1,
            'format' => 'image/jpeg',
            'review_status' => 'pending',
            'is_current' => false,
            'uploaded_by_user_id' => $user->id,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'replaces_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(app(MemberContextResolver::class)->isIdentityEligibleForBooking(Member::query()->findOrFail($result->memberId)));
    }

    private function privateObject(string $label): PrivateObject
    {
        return new PrivateObject(
            key: OpaqueObjectKey::fromString('objects/'.$label),
            checksum: hash('sha256', $label),
            bytes: 1,
            createdAt: new DateTimeImmutable('2026-08-04T10:00:00+00:00'),
        );
    }

    private function bindValidationContext(User $user): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $user->id),
            operationId: new CorrelationId('validation-'.Str::uuid()),
            sessionId: LocalId::fromString('session-'.(string) $user->id),
            roles: ['system'],
            permissions: [],
            purpose: 'member.nonclinical-validation',
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

    private function bindAdministratorRegistrationContext(User $user): void
    {
        $context = new AuthenticatedContext(
            actorId: LocalId::fromString((string) $user->id),
            operationId: new CorrelationId('registration-'.Str::uuid()),
            sessionId: LocalId::fromString('session-'.(string) $user->id),
            roles: ['administrator'],
            permissions: ['member.registration.manage', 'member.identity.verify'],
            purpose: 'member.registration',
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
