<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\KeyMaterial;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class PlainLocalObjectStore implements PrivateObjectStore
{
    public function __construct(
        private KeyMaterial $grantKey,
        private Clock $clock,
    ) {}

    public function put(string $contents, AuthenticatedContext $context, string $purpose): PrivateObject
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new ObjectAccessException('Private object stream could not be opened.');
        }
        fwrite($stream, $contents);
        rewind($stream);

        try {
            return $this->putStream($stream, strlen($contents), hash('sha256', $contents), $context, $purpose);
        } finally {
            fclose($stream);
        }
    }

    public function putStream($stream, int $bytes, string $checksum, AuthenticatedContext $context, string $purpose, ?OpaqueObjectKey $key = null): PrivateObject
    {
        $this->assertContext($context, $purpose);
        $this->assertStreamMetadata($stream, $bytes, $checksum);
        $key ??= OpaqueObjectKey::fromString('objects/'.Str::uuid());
        $disk = $this->disk();
        rewind($stream);

        if (! $disk->writeStream((string) $key, $stream)) {
            throw new ObjectAccessException('Private object persistence failed.');
        }

        $createdAt = $this->clock->now();
        $metadata = json_encode([
            'checksum' => $checksum,
            'bytes' => $bytes,
            'created_at' => $createdAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);
        if (! $disk->put((string) $key.'.meta.json', $metadata)) {
            $disk->delete((string) $key);
            throw new ObjectAccessException('Private object metadata persistence failed.');
        }

        return new PrivateObject($key, $checksum, $bytes, $createdAt);
    }

    public function putStreamAsync($stream, int $bytes, string $checksum, AuthenticatedContext $context, string $purpose, ?OpaqueObjectKey $key = null): PromiseInterface
    {
        $this->assertContext($context, $purpose);
        $this->assertStreamMetadata($stream, $bytes, $checksum);
        $key ??= OpaqueObjectKey::fromString('objects/'.Str::uuid());
        $disk = $this->disk();

        if (! $disk instanceof AwsS3V3Adapter) {
            return Create::promiseFor($this->putStream($stream, $bytes, $checksum, $context, $purpose, $key));
        }

        $createdAt = $this->clock->now();
        $metadata = json_encode([
            'checksum' => $checksum,
            'bytes' => $bytes,
            'created_at' => $createdAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);
        rewind($stream);
        $client = $disk->getClient();
        $config = $disk->getConfig();
        $object = $client->putObjectAsync([
            'Bucket' => $config['bucket'],
            'Key' => (string) $key,
            'Body' => $stream,
            'ACL' => 'private',
        ]);

        return $object->then(function () use ($client, $config, $key, $metadata, $checksum, $bytes, $createdAt): PrivateObject {
            return $client->putObjectAsync([
                'Bucket' => $config['bucket'],
                'Key' => (string) $key.'.meta.json',
                'Body' => $metadata,
                'ACL' => 'private',
                'ContentType' => 'application/json',
            ])->then(fn () => new PrivateObject($key, $checksum, $bytes, $createdAt));
        });
    }

    public function delete(PrivateObject $object): void
    {
        $disk = $this->disk();
        $disk->delete((string) $object->key);
        $disk->delete((string) $object->key.'.meta.json');
    }

    public function grant(PrivateObject $object, AuthenticatedContext $context, string $audience, string $purpose, DateTimeImmutable $expiresAt): AccessGrant
    {
        $this->assertContext($context, $purpose);

        return AccessGrant::issue(
            'private-object:'.$object->key,
            (string) $context->actorId,
            $audience,
            $purpose,
            $this->clock->now(),
            $expiresAt,
            (string) $context->operationId,
            $this->grantKey,
        );
    }

    public function get(AccessGrant $grant, AuthenticatedContext $context, string $audience, string $purpose): string
    {
        $stream = $this->getStream($grant, $context, $audience, $purpose);
        $contents = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($contents)) {
            throw new ObjectAccessException('Private object could not be read.');
        }

        return $contents;
    }

    public function getStream(AccessGrant $grant, AuthenticatedContext $context, string $audience, string $purpose)
    {
        $target = $grant->target();
        if (! str_starts_with($target, 'private-object:')) {
            throw new ObjectAccessException('Access grant target is not a private object.');
        }
        $key = OpaqueObjectKey::fromString(substr($target, strlen('private-object:')));
        $grant->verify($context, $audience, $purpose, $target, $this->clock->now(), $this->grantKey);
        $disk = $this->disk();
        $metadata = json_decode((string) $disk->get((string) $key.'.meta.json'), true, 512, JSON_THROW_ON_ERROR);
        $stream = $disk->readStream((string) $key);
        if (! is_resource($stream) || ! is_array($metadata)) {
            throw new ObjectAccessException('Private object does not exist.');
        }
        $actualBytes = 0;
        $hash = hash_init('sha256');
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                fclose($stream);
                throw new ObjectAccessException('Private object integrity could not be checked.');
            }
            $actualBytes += strlen($chunk);
            hash_update($hash, $chunk);
        }
        if (($metadata['bytes'] ?? null) !== $actualBytes || ($metadata['checksum'] ?? null) !== hash_final($hash)) {
            fclose($stream);
            throw new ObjectAccessException('Private object metadata does not match its contents.');
        }
        rewind($stream);

        return $stream;
    }

    private function assertContext(AuthenticatedContext $context, string $purpose): void
    {
        if ($context->actorId === null || $context->operationId === null || $context->purpose !== $purpose || trim($purpose) === '') {
            throw new ObjectAccessException('Trusted authorization and purpose are required.');
        }
    }

    private function assertStreamMetadata($stream, int $bytes, string $checksum): void
    {
        if (! is_resource($stream) || $bytes < 1 || preg_match('/\A[0-9a-f]{64}\z/i', $checksum) !== 1) {
            throw new ObjectAccessException('Private object stream metadata is invalid.');
        }
    }

    private function disk(): Filesystem
    {
        $name = config('mhcs.private_object_disk');
        if (! is_string($name) || trim($name) === '' || ! array_key_exists($name, config('filesystems.disks', []))) {
            throw new ObjectAccessException('Private object disk is not configured.');
        }

        return Storage::disk($name);
    }
}
