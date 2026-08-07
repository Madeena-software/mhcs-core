<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\KeyMaterial;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class EncryptedLocalObjectStore implements PrivateObjectStore
{
    public function __construct(
        private KeyMaterial $key,
        private KeyMaterial $grantKey,
        private Clock $clock,
    ) {}

    public function put(string $contents, AuthenticatedContext $context, string $purpose): PrivateObject
    {
        $this->assertContext($context, $purpose);
        $key = OpaqueObjectKey::fromString('objects/'.Str::uuid());
        $encrypted = $this->encrypt($contents);
        $disk = Storage::disk('local');

        if (! $disk->put((string) $key, $encrypted)) {
            throw new ObjectAccessException('Private object persistence failed.');
        }

        $createdAt = $this->clock->now();
        $metadata = json_encode([
            'checksum' => hash('sha256', $contents),
            'bytes' => strlen($contents),
            'encryption' => 'AES-256-GCM',
            'created_at' => $createdAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        if (! $disk->put((string) $key.'.meta.json', $metadata)) {
            $disk->delete((string) $key);
            throw new ObjectAccessException('Private object metadata persistence failed.');
        }

        return new PrivateObject(
            key: $key,
            checksum: hash('sha256', $contents),
            bytes: strlen($contents),
            encryption: 'AES-256-GCM',
            createdAt: $createdAt,
        );
    }

    public function delete(PrivateObject $object): void
    {
        $disk = Storage::disk('local');
        $disk->delete((string) $object->key);
        $disk->delete((string) $object->key.'.meta.json');
    }

    public function grant(
        PrivateObject $object,
        AuthenticatedContext $context,
        string $audience,
        string $purpose,
        DateTimeImmutable $expiresAt,
    ): AccessGrant {
        $this->assertContext($context, $purpose);

        return AccessGrant::issue(
            target: 'private-object:'.$object->key,
            actorId: (string) $context->actorId,
            audience: $audience,
            purpose: $purpose,
            issuedAt: $this->clock->now(),
            expiresAt: $expiresAt,
            correlationId: (string) $context->operationId,
            key: $this->grantKey,
        );
    }

    public function get(AccessGrant $grant, AuthenticatedContext $context, string $audience, string $purpose): string
    {
        $target = $grant->target();

        if (! str_starts_with($target, 'private-object:')) {
            throw new ObjectAccessException('Access grant target is not a private object.');
        }

        $key = OpaqueObjectKey::fromString(substr($target, strlen('private-object:')));
        $grant->verify($context, $audience, $purpose, $target, $this->clock->now(), $this->grantKey);
        $encrypted = Storage::disk('local')->get((string) $key);

        if (! is_string($encrypted)) {
            throw new ObjectAccessException('Private object does not exist.');
        }

        try {
            $metadata = json_decode(
                (string) Storage::disk('local')->get((string) $key.'.meta.json'),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $plaintext = $this->decrypt($encrypted);

            if (
                ! is_array($metadata)
                || ($metadata['encryption'] ?? null) !== 'AES-256-GCM'
                || ($metadata['bytes'] ?? null) !== strlen($plaintext)
                || ($metadata['checksum'] ?? null) !== hash('sha256', $plaintext)
            ) {
                throw new ObjectAccessException('Private object metadata does not match its contents.');
            }

            return $plaintext;
        } catch (Throwable $exception) {
            if ($exception instanceof ObjectAccessException) {
                throw $exception;
            }

            throw new ObjectAccessException('Private object decryption failed.', previous: $exception);
        }
    }

    private function assertContext(AuthenticatedContext $context, string $purpose): void
    {
        if (
            $context->actorId === null
            || $context->operationId === null
            || $context->purpose !== $purpose
            || trim($purpose) === ''
        ) {
            throw new ObjectAccessException('Trusted authorization and purpose are required.');
        }
    }

    private function encrypt(string $contents): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($contents, 'aes-256-gcm', $this->key->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new ObjectAccessException('Private object encryption failed.');
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    private function decrypt(string $contents): string
    {
        $decoded = base64_decode($contents, true);

        if ($decoded === false || strlen($decoded) < 28) {
            throw new ObjectAccessException('Private object ciphertext is malformed.');
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key->encryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new ObjectAccessException('Private object ciphertext is invalid.');
        }

        return $plaintext;
    }
}
