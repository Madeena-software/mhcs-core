<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use App\Shared\Security\KeyMaterial;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PlainLocalObjectStore;
use App\Shared\Storage\PrivateObject;
use App\Shared\Time\FrozenClock;
use Aws\S3\S3Client;
use DateTimeImmutable;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class PlainLocalObjectStoreAsyncTest extends TestCase
{
    public function test_successful_async_write_fulfills_with_private_object_after_metadata(): void
    {
        [$store, $calls] = $this->storeWithClient([Create::promiseFor([]), Create::promiseFor([])]);
        $key = OpaqueObjectKey::fromString('objects/radiograph');
        $stream = $this->stream('radiograph');

        $result = $store->putStreamAsync($stream, 10, hash('sha256', 'radiograph'), $this->context(), 'test', $key)->wait();

        $this->assertInstanceOf(PrivateObject::class, $result);
        $this->assertSame((string) $key, (string) $result->key);
        $this->assertSame([(string) $key, (string) $key.'.meta.json'], array_column($calls->getArrayCopy(), 'Key'));
        $this->assertSame('private', $calls[0]['ACL']);
        $this->assertSame('private', $calls[1]['ACL']);
        $this->assertSame('application/json', $calls[1]['ContentType']);
    }

    public function test_concurrent_async_writes_both_fulfill_with_private_objects(): void
    {
        [$store] = $this->storeWithClient(array_fill(0, 4, Create::promiseFor([])));
        $radiograph = $store->putStreamAsync($this->stream('radiograph'), 10, hash('sha256', 'radiograph'), $this->context(), 'test', OpaqueObjectKey::fromString('objects/radiograph'));
        $gain = $store->putStreamAsync($this->stream('gain'), 4, hash('sha256', 'gain'), $this->context(), 'test', OpaqueObjectKey::fromString('objects/gain'));

        $settled = Utils::settle([$radiograph, $gain])->wait();

        $this->assertSame(['fulfilled', 'fulfilled'], array_column($settled, 'state'));
        $this->assertContainsOnlyInstancesOf(PrivateObject::class, array_column($settled, 'value'));
    }

    public function test_async_write_waits_for_metadata_before_fulfilling(): void
    {
        $primary = new Promise;
        $metadata = new Promise;
        [$store] = $this->storeWithClient([$primary, $metadata]);
        $fulfilled = false;
        $result = $store->putStreamAsync($this->stream('radiograph'), 10, hash('sha256', 'radiograph'), $this->context(), 'test', OpaqueObjectKey::fromString('objects/radiograph'));
        $result->then(static function () use (&$fulfilled): void {
            $fulfilled = true;
        });

        $primary->resolve([]);
        Utils::queue()->run();
        $this->assertFalse($fulfilled);
        $metadata->resolve([]);
        Utils::queue()->run();
        $this->assertTrue($fulfilled);
    }

    public function test_primary_object_failure_rejects_without_starting_metadata(): void
    {
        $primary = Create::rejectionFor(new \RuntimeException('primary failed'));
        [$store, $calls] = $this->storeWithClient([$primary]);

        $this->expectException(\RuntimeException::class);
        try {
            $store->putStreamAsync($this->stream('radiograph'), 10, hash('sha256', 'radiograph'), $this->context(), 'test', OpaqueObjectKey::fromString('objects/radiograph'))->wait();
        } finally {
            $this->assertCount(1, $calls);
        }
    }

    public function test_metadata_failure_rejects_after_primary_object_succeeds(): void
    {
        [$store] = $this->storeWithClient([Create::promiseFor([]), Create::rejectionFor(new \RuntimeException('metadata failed'))]);

        $this->expectException(\RuntimeException::class);
        $store->putStreamAsync($this->stream('radiograph'), 10, hash('sha256', 'radiograph'), $this->context(), 'test', OpaqueObjectKey::fromString('objects/radiograph'))->wait();
    }

    /** @param list<PromiseInterface> $promises */
    private function storeWithClient(array $promises): array
    {
        $calls = new \ArrayObject;
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('putObjectAsync')->andReturnUsing(function (array $arguments) use (&$calls, &$promises) {
            $calls->append($arguments);

            return array_shift($promises) ?? Create::promiseFor([]);
        });
        $disk = Mockery::mock(AwsS3V3Adapter::class);
        $disk->shouldReceive('getClient')->andReturn($client);
        $disk->shouldReceive('getConfig')->andReturn(['bucket' => 'test-bucket']);
        Storage::shouldReceive('disk')->andReturn($disk);

        return [new PlainLocalObjectStore(KeyMaterial::from(str_repeat('k', 32)), new FrozenClock(new DateTimeImmutable('2026-08-26T00:00:00+00:00'))), $calls];
    }

    private function context(): AuthenticatedContext
    {
        return new AuthenticatedContext(
            actorId: LocalId::fromString('actor'),
            operationId: new CorrelationId('operation'),
            purpose: 'test',
        );
    }

    /** @return resource */
    private function stream(string $contents)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
