<?php

declare(strict_types=1);

namespace App\Modules\Member\Filament\Resources\Members\Pages;

use App\Models\User;
use App\Modules\Member\Application\Data\MemberRegistrationData;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Application\Services\MemberRegistrationService;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\Models\Member;
use App\Modules\Member\Filament\Resources\Members\MemberResource;
use App\Shared\Authorization\AdminPanelAccessService;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

final class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected static bool $canCreateAnother = false;

    #[Locked]
    public string $registrationOperationId;

    /** @var list<PrivateObject> */
    private array $attemptObjects = [];

    public function mount(): void
    {
        $this->registrationOperationId = (string) Str::uuid();
        parent::mount();
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::id() === null ? null : User::query()->find(Auth::id());
        abort_unless($user instanceof User && app(AdminPanelAccessService::class)->canAccess($user, 'admin') && MemberResource::canCreate(), 403);
    }

    public function create(bool $another = false): void
    {
        $this->authorizeAccess();
        $this->attemptObjects = [];
        try {
            parent::create(false);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['data.name' => 'Pendaftaran Member tidak dapat diselesaikan. Periksa data atau coba kembali.']);
        } finally {
            foreach ($this->attemptObjects as $object) {
                // Replays and rolled-back attempts must never delete committed evidence.
                if (! DB::table('member_verification_assets')->where('private_object_key', (string) $object->key)->exists()) {
                    app(PrivateObjectStore::class)->delete($object);
                }
            }
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $this->authorizeAccess();
        $context = app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true);
        $inputs = [];
        foreach (['identity_document' => VerificationAssetType::Ktp, 'profile_photo' => VerificationAssetType::ProfilePhoto] as $field => $type) {
            $file = $data[$field] ?? null;
            if ($file === null) {
                $inputs[$field] = null;

                continue;
            }
            $limit = config('mhcs.upload.max_file_bytes');
            if (! is_int($limit) || $limit < 1 || ! $file instanceof TemporaryUploadedFile || $file->getSize() < 1 || $file->getSize() > $limit) {
                throw ValidationException::withMessages(['data.'.$field => 'Foto tidak valid atau melebihi batas unggahan.']);
            }
            $contents = $file->get();
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || @getimagesizefromstring($contents) === false) {
                throw ValidationException::withMessages(['data.'.$field => 'Gunakan foto JPEG, PNG, atau WebP yang valid.']);
            }
            $inputs[$field] = [$type, $contents, $mime];
        }

        foreach ($inputs as $field => $input) {
            if ($input === null) {
                continue;
            }
            [$type, $contents, $mime] = $input;
            $object = new PrivateObject(OpaqueObjectKey::fromString('objects/'.Str::uuid()), hash('sha256', $contents), strlen($contents), app(Clock::class)->now());
            $this->attemptObjects[] = $object;
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('Private upload stream unavailable.');
            }
            try {
                fwrite($stream, $contents);
                rewind($stream);
                $stored = app(PrivateObjectStore::class)->putStream($stream, $object->bytes, $object->checksum, $context, 'member.registration', $object->key);
                $inputs[$field] = new VerificationAssetInput($type, $stored, $mime);
            } finally {
                fclose($stream);
            }
        }

        $result = app(MemberRegistrationService::class)->registerAdminAssisted(new MemberRegistrationData(
            operationId: $this->registrationOperationId,
            email: $data['email'] ?? null, password: null, name: $data['name'],
            birthDate: new DateTimeImmutable($data['birth_date']), administrativeGender: $data['administrative_gender'],
            nik: $data['nik'], kk: $data['kk'] ?? null, phone: $data['phone'] ?? null,
            registrationSource: RegistrationSource::Administrator,
            identityDocument: $inputs['identity_document'], profilePhoto: $inputs['profile_photo'],
        ));

        return Member::query()->findOrFail($result->memberId);
    }

    protected function afterCreate(): void
    {
        foreach (['identity_document', 'profile_photo'] as $field) {
            foreach ($this->data[$field] ?? [] as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    $file->delete();
                }
            }
        }
        $this->data = [];
    }

    protected function getRedirectUrl(): string
    {
        return MemberResource::getUrl('index');
    }
}
