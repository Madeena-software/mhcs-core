<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Member\Application\Services\MedicalRecordNumberGenerator;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class MvpMemberSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! (bool) env('MHCS_ALLOW_PRODUCTION_MVP_SEED', false)) {
            throw new RuntimeException('MvpMemberSeeder is limited to local and testing environments.');
        }

        $identifiers = app(ProtectedIdentifierService::class);
        $mrn = app(MedicalRecordNumberGenerator::class);
        $objects = app(PrivateObjectStore::class);

        foreach ([
            ['name' => 'Synthetic Beta Member One', 'email' => 'mvp-member-one@example.test', 'nik' => '900000000101', 'birth_date' => '1988-01-10'],
            ['name' => 'Synthetic Beta Member Two', 'email' => 'mvp-member-two@example.test', 'nik' => '900000000102', 'birth_date' => '1992-02-20'],
            ['name' => 'Synthetic Beta Member Three', 'email' => 'mvp-member-three@example.test', 'nik' => '900000000103', 'birth_date' => '1995-03-30'],
            ['name' => 'Synthetic Beta Member Four', 'email' => 'mvp-member-four@example.test', 'nik' => '900000000104', 'birth_date' => '1997-04-15'],
            ['name' => 'Synthetic Beta Member Five', 'email' => 'mvp-member-five@example.test', 'nik' => '900000000105', 'birth_date' => '2000-05-25'],
        ] as $account) {
            $existing = User::query()->where('email', $account['email'])->first();

            if ($existing !== null) {
                if (DB::table('members')->where('user_id', $existing->id)->count() !== 1) {
                    throw new RuntimeException('An existing synthetic account has an invalid Member link.');
                }

                $this->command?->info("{$account['email']} already exists; its credential was not changed.");

                continue;
            }

            $plaintext = bin2hex(random_bytes(24));
            $protected = $identifiers->protect($account['nik']);
            $userId = (string) Str::uuid();
            $memberId = (string) Str::uuid();
            $context = new AuthenticatedContext(
                actorId: LocalId::fromString($userId),
                operationId: CorrelationId::random(),
                roles: ['administrator'],
                permissions: ['member.registration.manage', 'member.identity.verify'],
                purpose: 'member.registration',
            );
            $identityObject = $objects->put('synthetic-ktp-'.$account['nik'], $context, 'member.registration');
            $profileObject = $objects->put('synthetic-profile-'.$account['nik'], $context, 'member.registration');

            DB::transaction(function () use ($account, $protected, $plaintext, $userId, $memberId, $mrn, $identityObject, $profileObject): void {
                $now = now();
                DB::table('users')->insert([
                    'id' => $userId,
                    'email' => $account['email'],
                    'email_verified_at' => null,
                    'password' => Hash::make($plaintext),
                    'remember_token' => null,
                    'account_status' => 'active',
                    'login_enabled' => true,
                    'must_change_password' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('members')->insert([
                    'id' => $memberId,
                    'user_id' => $userId,
                    'family_id' => null,
                    'medical_record_number' => $mrn->generate(),
                    'identity_status' => 'verified',
                    'identity_document_type' => 'ktp',
                    'encrypted_nik' => $protected['encrypted_display'],
                    'nik_lookup_digest' => $protected['lookup_digest'],
                    'name' => $account['name'],
                    'birth_date' => $account['birth_date'],
                    'administrative_gender' => 'unspecified',
                    'registration_source' => 'administrator',
                    'phone' => null,
                    'current_address' => null,
                    'emergency_contact_name' => null,
                    'emergency_contact_relationship' => null,
                    'emergency_contact_phone' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                foreach ([
                    ['type' => 'ktp', 'object' => $identityObject],
                    ['type' => 'profile_photo', 'object' => $profileObject],
                ] as $asset) {
                    /** @var PrivateObject $object */
                    $object = $asset['object'];
                    DB::table('member_verification_assets')->insert([
                        'id' => (string) Str::uuid(),
                        'member_id' => $memberId,
                        'type' => $asset['type'],
                        'private_object_key' => (string) $object->key,
                        'checksum' => $object->checksum,
                        'bytes' => $object->bytes,
                        'format' => 'text/plain',
                        'review_status' => 'approved',
                        'is_current' => true,
                        'uploaded_by_user_id' => $userId,
                        'reviewed_by_user_id' => $userId,
                        'reviewed_at' => $now,
                        'replaces_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

            MvpCredentialFile::append($account['email'], $plaintext);
        }

        $this->command?->info('Synthetic Member accounts are ready.');
    }
}
