<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Application\Services\AdultActivationService;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Application\Services\MemberContextResolver;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Modules\Member\Application\Services\MemberVerificationAssetService;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Filament\Resources\Members\Pages\CreateMember;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use DateTimeImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AdminMemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mhcs.security.identifier_key' => str_repeat('i', 32), 'mhcs.security.grant_key' => str_repeat('g', 32)]);
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
    }

    public static function photos(): array
    {
        $cases = [];
        foreach ([false, true] as $ktp) {
            foreach ([false, true] as $profile) {
                foreach ([false, true] as $verify) {
                    $cases[(int) $ktp.'-'.(int) $profile.'-'.(int) $verify] = [$ktp, $profile, $verify];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('photos')]
    public function test_domain_registration_is_pending_and_replays(bool $ktp, bool $profile, bool $verify): void
    {
        $admin = $this->admin($verify);
        $this->actingAs($admin);
        $data = $this->data($ktp, $profile);
        $service = app(MemberRegistrationService::class);
        $result = $service->registerAdminAssisted($data);
        $replay = $service->registerAdminAssisted($data);
        $this->assertSame($result->memberId, $replay->memberId);
        $this->assertTrue($replay->replayed);
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('member_verification_assets', (int) $ktp + (int) $profile);
        $this->assertDatabaseHas('members', ['id' => $result->memberId, 'identity_document_type' => $ktp ? 'ktp' : null, 'identity_status' => 'pending_verification']);
        $member = Member::query()->findOrFail($result->memberId);
        $this->assertNotEmpty($member->encrypted_nik);
        $this->assertNotEmpty($member->nik_lookup_digest);
        $this->assertNotSame($data->nik, $result->medicalRecordNumber);
        $user = User::query()->findOrFail($result->userId);
        $this->assertSame('pending_activation', $user->account_status);
        $this->assertFalse($user->login_enabled);
        $this->assertTrue($user->must_change_password);
        $this->assertFalse($user->canAuthenticate());
        $this->assertFalse(Auth::getProvider()->validateCredentials($user, ['password' => 'synthetic-never-issued']));
        $this->assertDatabaseCount('authorization_role_assignments', 1);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'member.registration')->where('actor_id', $admin->id)->count());
        foreach (DB::table('member_verification_assets')->get() as $asset) {
            $this->assertSame('pending', $asset->review_status);
            $this->assertFalse((bool) $asset->is_current);
            $this->assertNull($asset->reviewed_by_user_id);
            $this->assertNull($asset->reviewed_at);
        }
        if (! $ktp && ! $profile) {
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    #[DataProvider('photos')]
    public function test_livewire_registration_returns_to_safe_directory(bool $ktp, bool $profile, bool $verify): void
    {
        $this->actingAs($this->admin($verify));
        $form = $this->form();
        if ($ktp) {
            $form['identity_document'] = UploadedFile::fake()->image('ktp.jpg');
        }
        if ($profile) {
            $form['profile_photo'] = UploadedFile::fake()->image('profile.jpg');
        }
        $component = Livewire::test(CreateMember::class)->fillForm($form)->call('create')->assertHasNoFormErrors()->assertRedirect('/admin/members');
        $member = Member::query()->sole();
        $this->assertDatabaseCount('member_verification_assets', (int) $ktp + (int) $profile);
        $component->assertSet('data', []);
        $this->get('/admin/members')->assertOk()->assertSee($member->medical_record_number)->assertDontSee($form['nik']);
        $this->get('/admin/members/'.$member->id)->assertOk()->assertSee('Menunggu verifikasi')->assertDontSee($form['nik'])->assertDontSee('objects/');
    }

    public static function photoFormats(): array
    {
        return [['jpg'], ['png'], ['webp']];
    }

    #[DataProvider('photoFormats')]
    public function test_only_jpeg_and_png_are_accepted_before_private_persistence(string $extension): void
    {
        $this->actingAs($this->admin());
        $component = Livewire::test(CreateMember::class)->fillForm($this->form() + [
            'identity_document' => UploadedFile::fake()->image('ktp.'.$extension),
            'profile_photo' => UploadedFile::fake()->image('profile.'.$extension),
        ])->call('create');
        if ($extension === 'webp') {
            $component->assertHasFormErrors(['identity_document', 'profile_photo']);
            $this->assertDatabaseCount('members', 0);
            $this->assertDatabaseCount('member_verification_assets', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('objects'));
        } else {
            $component->assertHasNoFormErrors()->assertRedirect('/admin/members');
            $this->assertDatabaseCount('member_verification_assets', 2);
            foreach (DB::table('member_verification_assets')->pluck('private_object_key') as $key) {
                Storage::disk('local')->assertExists($key);
            }
        }
    }

    public function test_creation_handler_rejects_webp_content_even_with_a_jpeg_filename(): void
    {
        $this->actingAs($this->admin());
        $file = UploadedFile::fake()->image('synthetic.webp');
        Storage::disk('local')->put('livewire-tmp/synthetic.jpg', $file->getContent());
        $component = Livewire::test(CreateMember::class)->instance();
        try {
            (new \ReflectionMethod(CreateMember::class, 'handleRecordCreation'))->invoke($component, $this->form() + [
                'identity_document' => new TemporaryUploadedFile('synthetic.jpg', 'local'),
            ]);
            $this->fail('WebP content must be rejected by the creation handler.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.identity_document', $exception->errors());
        }
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_verification_assets', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('objects'));
    }

    public function test_completed_pending_registration_cannot_receive_assets_through_registration_entry(): void
    {
        $this->actingAs($this->admin(true));
        $data = $this->data();
        $result = app(MemberRegistrationService::class)->registerAdminAssisted($data);
        $member = Member::query()->findOrFail($result->memberId);
        $asset = $this->data(true)->identityDocument;
        $context = app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true);
        $auditCount = DB::table('audit_events')->count();
        DB::transaction(function () use ($member, $asset, $context, $data): void {
            $assets = app(MemberVerificationAssetService::class);
            $this->reject(fn () => $assets->recordPendingForAdminRegistration($member, $asset, $context, $data->operationId));
            $this->reject(fn () => $assets->recordPendingForAdminRegistration($member, $asset, $context, 'missing-operation'));
            DB::table('member_operations')->insert([
                'id' => (string) Str::uuid(), 'operation_type' => 'registration', 'operation_id' => 'another-pending-operation',
                'payload_hash' => str_repeat('a', 64), 'status' => 'pending',
                'result' => json_encode(['member_id' => (string) Str::uuid()], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->reject(fn () => $assets->recordPendingForAdminRegistration($member, $asset, $context, 'another-pending-operation'));
            DB::table('member_operations')->where('operation_id', 'another-pending-operation')->delete();
        });
        $this->assertDatabaseCount('member_verification_assets', 0);
        $this->assertDatabaseCount('audit_events', $auditCount);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'identity_status' => 'pending_verification', 'identity_document_type' => null]);
    }

    public function test_create_access_and_submission_are_denied_to_ineligible_accounts(): void
    {
        $this->get('/admin/members/create')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/members/create')->assertForbidden();
        foreach ([['account_status' => 'suspended'], ['login_enabled' => false], ['must_change_password' => true]] as $state) {
            $this->actingAs($this->admin(false, $state))->get('/admin/members/create')->assertForbidden();
        }
        $this->actingAs($this->admin(false, [], ['member.admin.access', 'member.account.read']))->get('/admin/members/create')->assertForbidden();
        $this->actingAs($this->admin(false, [], ['member.admin.access', 'member.registration.manage']))->get('/admin/members/create')->assertForbidden();
        $this->assertDatabaseCount('members', 0);
    }

    public static function revocations(): array
    {
        return [['permission'], ['read'], ['role'], ['suspended'], ['login'], ['password']];
    }

    #[DataProvider('revocations')]
    public function test_submit_rechecks_authority_after_mount(string $revocation): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $component = Livewire::test(CreateMember::class)->fillForm($this->form());
        match ($revocation) {
            'permission', 'read' => DB::table('authorization_permission_assignments')->where('user_id', $admin->id)->where('permission', $revocation === 'read' ? 'member.account.read' : 'member.registration.manage')->update(['active' => false]),
            'role' => DB::table('authorization_role_assignments')->where('user_id', $admin->id)->update(['active' => false]),
            'suspended' => DB::table('users')->where('id', $admin->id)->update(['account_status' => 'suspended']),
            'login' => DB::table('users')->where('id', $admin->id)->update(['login_enabled' => false]),
            'password' => DB::table('users')->where('id', $admin->id)->update(['must_change_password' => true]),
        };
        $component->call('create')->assertForbidden();
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_operations', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_authority_is_rechecked_after_mount(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $component = Livewire::test(CreateMember::class);
        DB::table('authorization_permission_assignments')->where('user_id', $admin->id)->where('permission', 'member.registration.manage')->update(['active' => false]);
        $component->call('_startUpload', 'data.identity_document', [['name' => 'ktp.jpg', 'size' => 100, 'type' => 'image/jpeg']], false)->assertForbidden();
    }

    public static function invalidForm(): array
    {
        return [
            ['nik', null], ['nik', ''], ['nik', '123'], ['nik', '990000000000000x'],
            ['birth_date', 'invalid'], ['birth_date', '2099-01-01'], ['birth_date', '2020-01-01'],
            ['kk', 'bad'], ['email', 'bad'], ['administrative_gender', 'other'],
        ];
    }

    #[DataProvider('invalidForm')]
    public function test_ui_rejects_invalid_required_and_optional_demographics(string $field, mixed $value): void
    {
        $this->actingAs($this->admin());
        Livewire::test(CreateMember::class)->fillForm(array_replace($this->form(), [$field => $value]))->call('create')->assertHasFormErrors([$field]);
        $this->assertDatabaseCount('members', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public static function invalidDomain(): array
    {
        return [
            [['nik' => '']], [['nik' => ' ']], [['nik' => '123']], [['nik' => '990000000000000x']],
            [['kk' => '123']], [['email' => 'bad']], [['password' => 'never-accept-credentials']],
            [['birthDate' => new DateTimeImmutable('2020-01-01')]], [['birthDate' => new DateTimeImmutable('2099-01-01')]],
            [['registrationSource' => RegistrationSource::Online]], [['registrationSource' => RegistrationSource::WalkIn]],
            [['guardianMemberIds' => ['guardian']]], [['externalIdentifiers' => [['namespace' => 'test', 'value' => 'test']]]],
        ];
    }

    #[DataProvider('invalidDomain')]
    public function test_scoped_service_rejects_invalid_or_expanded_payload(array $override): void
    {
        $this->actingAs($this->admin());
        $this->reject(fn () => app(MemberRegistrationService::class)->registerAdminAssisted($this->data(false, false, $override)));
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('member_operations', 0);
    }

    public function test_legacy_registration_still_requires_both_assets(): void
    {
        $this->actingAs($this->admin(true));
        foreach ([[false, false], [true, false], [false, true]] as [$ktp, $profile]) {
            $this->reject(fn () => app(MemberRegistrationService::class)->register($this->data($ktp, $profile)));
        }
        $this->assertDatabaseCount('members', 0);
    }

    public function test_changed_payload_actor_and_legacy_entry_cannot_replay_scoped_operation(): void
    {
        $admin = $this->admin(true);
        $this->actingAs($admin);
        $data = $this->data(true, true);
        $service = app(MemberRegistrationService::class);
        $original = $service->registerAdminAssisted($data);
        foreach ([['nik' => '9900000000000002'], ['name' => 'Changed'], ['phone' => 'changed'], ['identityDocument' => null], ['profilePhoto' => null], ['profilePhoto' => $this->data(false, true)->profilePhoto]] as $override) {
            // A content change must differ from the original synthetic bytes.
            if (isset($override['profilePhoto'])) {
                $context = app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true);
                $override['profilePhoto'] = new VerificationAssetInput(VerificationAssetType::ProfilePhoto, app(PrivateObjectStore::class)->put('changed-content', $context, 'member.registration'), 'image/jpeg');
            }
            $this->reject(fn () => $service->registerAdminAssisted($this->data(false, false, array_replace(get_object_vars($data), $override))));
        }
        $this->reject(fn () => $service->register($data));
        $this->actingAs($this->admin(true));
        $this->reject(fn () => $service->registerAdminAssisted($data));
        $this->actingAs($admin);
        $this->assertSame($original->memberId, $service->registerAdminAssisted($data)->memberId);
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('member_verification_assets', 2);
    }

    public function test_duplicate_nik_and_email_rollback_without_orphans(): void
    {
        $this->actingAs($this->admin());
        $service = app(MemberRegistrationService::class);
        $service->registerAdminAssisted($this->data(false, false, ['email' => 'synthetic@example.test']));
        foreach ([['operationId' => 'duplicate-nik', 'email' => 'other@example.test'], ['operationId' => 'duplicate-email', 'nik' => '9900000000000002', 'email' => 'synthetic@example.test']] as $override) {
            $override['kk'] = '9900000000000099';
            $this->reject(fn () => $service->registerAdminAssisted($this->data(false, false, $override)));
        }
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('families', 0);
        $this->assertDatabaseCount('member_operations', 1);
    }

    public static function uploadFailures(): array
    {
        return [[1], [2]];
    }

    #[DataProvider('uploadFailures')]
    public function test_partial_private_write_failure_cleans_attempt_objects(int $failure): void
    {
        $this->actingAs($this->admin());
        $store = app(PrivateObjectStore::class);
        $mock = \Mockery::mock(PrivateObjectStore::class);
        $count = 0;
        $mock->shouldReceive('putStream')->andReturnUsing(function (...$args) use ($store, &$count, $failure) {
            $object = $store->putStream(...$args);
            if (++$count === $failure) {
                throw new \RuntimeException('synthetic write failure');
            }

            return $object;
        });
        $mock->shouldReceive('delete')->andReturnUsing(fn ($object) => $store->delete($object));
        app()->instance(PrivateObjectStore::class, $mock);
        Livewire::test(CreateMember::class)->fillForm($this->form() + ['identity_document' => UploadedFile::fake()->image('ktp.jpg'), 'profile_photo' => UploadedFile::fake()->image('profile.jpg')])->call('create')->assertHasFormErrors(['name'])->assertDontSee('synthetic write failure');
        $this->assertSame([], Storage::disk('local')->allFiles('objects'));
        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('member_operations', 0);
        $this->assertDatabaseCount('member_verification_assets', 0);
    }

    public function test_audit_failure_rolls_back_and_cleans_uploads_and_all_rows(): void
    {
        $this->actingAs($this->admin());
        $audit = app(AuditStore::class);
        $mock = \Mockery::mock(AuditStore::class);
        $mock->shouldReceive('append')->andReturnUsing(function ($event) use ($audit) {
            if ($event->action === 'member.registration') {
                throw new \RuntimeException('synthetic SQL secret marker');
            }
            $audit->append($event);
        });
        app()->instance(AuditStore::class, $mock);
        Livewire::test(CreateMember::class)->fillForm($this->form() + ['kk' => '9900000000000099', 'identity_document' => UploadedFile::fake()->image('ktp.jpg'), 'profile_photo' => UploadedFile::fake()->image('profile.jpg')])->call('create')->assertHasFormErrors(['name'])->assertDontSee('synthetic SQL secret marker');
        foreach (['members', 'families', 'member_operations', 'member_verification_assets', 'audit_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('objects'));
    }

    public static function photoCombinations(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    #[DataProvider('photoCombinations')]
    public function test_ui_retry_cleans_new_objects_and_keeps_committed_evidence(bool $ktp, bool $profile): void
    {
        $this->actingAs($this->admin());
        $component = Livewire::test(CreateMember::class);
        $retry = clone $component;
        $form = $this->form();
        if ($ktp) {
            $form['identity_document'] = UploadedFile::fake()->image('ktp.jpg');
        }
        if ($profile) {
            $form['profile_photo'] = UploadedFile::fake()->image('profile.jpg');
        }
        $component->fillForm($form)->call('create')->assertHasNoFormErrors();
        $objects = Storage::disk('local')->allFiles('objects');
        $audits = DB::table('audit_events')->count();
        $retry->fillForm($form)->call('create')->assertHasNoFormErrors();
        $this->assertSame($objects, Storage::disk('local')->allFiles('objects'));
        $this->assertSame($audits, DB::table('audit_events')->count());
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('member_verification_assets', (int) $ktp + (int) $profile);
    }

    public function test_missing_evidence_does_not_enable_booking_nonclinical_or_activation(): void
    {
        $this->actingAs($this->admin(true, [], ['member.admin.access', 'member.account.read', 'member.registration.manage', 'member.identity.verify', 'member.account.manage']));
        $result = app(MemberRegistrationService::class)->registerAdminAssisted($this->data());
        $member = Member::query()->findOrFail($result->memberId);
        $resolver = app(MemberContextResolver::class);
        $this->assertFalse($resolver->isIdentityEligibleForBooking($member));
        $this->assertFalse($resolver->isExactNonclinicalValidationIdentity($member));
        $this->reject(fn () => app(AdultActivationService::class)->activate($member->id, 'activation-attempt'));
        $this->assertDatabaseHas('users', ['id' => $result->userId, 'account_status' => 'pending_activation', 'login_enabled' => false]);
        foreach (['bookings', 'member_guardians', 'member_external_identifiers', 'operator_profiles'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->actingAs(User::query()->findOrFail($result->userId))->get('/member/dashboard')->assertRedirect('/login');
    }

    public function test_scoped_service_rejects_ineligible_or_unauthorized_actor(): void
    {
        foreach ([[], ['account_status' => 'suspended'], ['login_enabled' => false], ['must_change_password' => true]] as $state) {
            $admin = $this->admin(false, $state, $state === [] ? ['member.admin.access', 'member.account.read'] : null);
            $this->actingAs($admin);
            $data = new MemberRegistrationData('unauthorized-operation', null, null, 'Synthetic', new DateTimeImmutable('1985-08-04'), 'unspecified', '9900000000000001', null, null, RegistrationSource::Administrator, null, null);
            $this->reject(fn () => app(MemberRegistrationService::class)->registerAdminAssisted($data));
        }
        $this->assertDatabaseCount('members', 0);
    }

    public function test_supplied_assets_require_correct_slots_and_private_metadata(): void
    {
        $this->actingAs($this->admin(true));
        $service = app(MemberRegistrationService::class);
        $ktp = $this->data(true, false)->identityDocument;
        $profile = $this->data(false, true)->profilePhoto;
        foreach ([['identityDocument' => $profile], ['profilePhoto' => $ktp], ['identityDocument' => new VerificationAssetInput(VerificationAssetType::Kia, $ktp->object)], ['identityDocument' => new VerificationAssetInput(VerificationAssetType::Ktp, $ktp->object, 'image/jpeg', 'replacement')]] as $override) {
            $this->reject(fn () => $service->registerAdminAssisted($this->data(false, false, $override)));
        }
        foreach ([
            new PrivateObject(OpaqueObjectKey::fromString('public/fake'), str_repeat('a', 64), 1, new DateTimeImmutable),
            new PrivateObject(OpaqueObjectKey::fromString('objects/fake'), 'bad', 1, new DateTimeImmutable),
            new PrivateObject(OpaqueObjectKey::fromString('objects/fake'), str_repeat('a', 64), 0, new DateTimeImmutable),
        ] as $object) {
            $this->reject(fn () => $service->registerAdminAssisted($this->data(false, false, ['identityDocument' => new VerificationAssetInput(VerificationAssetType::Ktp, $object)])));
            $this->assertDatabaseCount('members', 0);
            $this->assertDatabaseCount('member_operations', 0);
        }
        $result = $service->registerAdminAssisted($this->data());
        $member = Member::query()->findOrFail($result->memberId);
        $context = app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true);
        $assets = app(MemberVerificationAssetService::class);
        $forged = new AuthenticatedContext(actorId: LocalId::fromString('different-actor'), roles: ['administrator'], permissions: ['member.registration.manage'], purpose: 'member.registration');
        $this->reject(fn () => $assets->recordPendingForAdminRegistration($member, $ktp, $forged, 'synthetic-operation'));
        $this->actingAs($this->admin(false, [], ['member.account.read']));
        $this->reject(fn () => $assets->recordPendingForAdminRegistration($member, $ktp, $context, 'synthetic-operation'));
        $this->assertDatabaseCount('member_verification_assets', 0);
    }

    public function test_uploads_reject_forged_mime_oversize_and_invalid_limits_before_private_writes(): void
    {
        $this->actingAs($this->admin());
        $files = [UploadedFile::fake()->createWithContent('fake.jpg', '<html>synthetic forged image</html>'), UploadedFile::fake()->image('big.jpg')->size(2)];
        config(['mhcs.upload.max_file_bytes' => 1024]);
        foreach ($files as $file) {
            Livewire::test(CreateMember::class)->fillForm($this->form() + ['identity_document' => $file])->call('create')->assertHasFormErrors(['identity_document']);
        }
        foreach ([null, 0, -1, 'invalid'] as $limit) {
            config(['mhcs.upload.max_file_bytes' => $limit]);
            Livewire::test(CreateMember::class)->fillForm($this->form() + ['identity_document' => UploadedFile::fake()->image('ktp.jpg')])->call('create')->assertHasFormErrors(['identity_document']);
        }
        $this->assertDatabaseCount('members', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('objects'));
    }

    public function test_legacy_baseline_hash_and_operation_remain_replayable_and_cannot_cross_entry(): void
    {
        $this->actingAs($this->admin(true, ['id' => '00000000-0000-4000-8000-000000000001']));
        $data = $this->data(true, true);
        $service = app(MemberRegistrationService::class);
        $original = $service->register($data);
        // Golden SHA-256 uses the payload encoding from fb253543, with fixed synthetic inputs.
        $baselineHash = '38a23e0e4e95f883a6b331c6e3fd9ca5e9dee21fc483d024367d7352a8523af3';
        $this->assertSame($baselineHash, DB::table('member_operations')->where('operation_id', $data->operationId)->value('payload_hash'));
        $this->reject(fn () => $service->registerAdminAssisted($data));
        $replay = $service->register($data);
        $this->assertTrue($replay->replayed);
        $this->assertSame($original->memberId, $replay->memberId);
        $this->assertSame('verified', $original->identityStatus);
        $this->assertSame('active', $original->accountStatus);
        $this->assertDatabaseCount('members', 1);
    }

    private function reject(callable $action): void
    {
        try {
            $action();
            $this->fail('Invalid registration must be rejected.');
        } catch (MemberIdentityException|\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    private function form(): array
    {
        return ['name' => 'Synthetic Adult', 'nik' => '9900000000000001', 'birth_date' => '1985-08-04', 'administrative_gender' => 'unspecified'];
    }

    private function data(bool $ktp = false, bool $profile = false, array $override = []): MemberRegistrationData
    {
        $context = app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true);
        $asset = fn (VerificationAssetType $type) => new VerificationAssetInput($type, app(PrivateObjectStore::class)->put('synthetic-'.$type->value, $context, 'member.registration'), 'image/jpeg');

        return new MemberRegistrationData(...array_replace([
            'operationId' => 'synthetic-operation', 'email' => null, 'password' => null,
            'name' => 'Synthetic Adult', 'birthDate' => new DateTimeImmutable('1985-08-04'),
            'administrativeGender' => 'unspecified', 'nik' => '9900000000000001', 'kk' => null, 'phone' => null,
            'registrationSource' => RegistrationSource::Administrator,
            'identityDocument' => $ktp ? $asset(VerificationAssetType::Ktp) : null,
            'profilePhoto' => $profile ? $asset(VerificationAssetType::ProfilePhoto) : null,
        ], $override));
    }

    private function admin(bool $verify = false, array $overrides = [], ?array $permissions = null): User
    {
        $user = User::factory()->create($overrides);
        DB::table('authorization_role_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role' => 'administrator', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissions ?? array_merge(['member.admin.access', 'member.account.read', 'member.registration.manage'], $verify ? ['member.identity.verify'] : []) as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'permission' => $permission, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $user;
    }
}
