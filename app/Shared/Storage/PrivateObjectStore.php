<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use App\Shared\Context\AuthenticatedContext;
use DateTimeImmutable;
use GuzzleHttp\Promise\PromiseInterface;

interface PrivateObjectStore
{
    public function put(string $contents, AuthenticatedContext $context, string $purpose): PrivateObject;

    /** @param resource $stream */
    public function putStream($stream, int $bytes, string $checksum, AuthenticatedContext $context, string $purpose, ?OpaqueObjectKey $key = null): PrivateObject;

    /** @param resource $stream */
    public function putStreamAsync($stream, int $bytes, string $checksum, AuthenticatedContext $context, string $purpose, ?OpaqueObjectKey $key = null): PromiseInterface;

    public function delete(PrivateObject $object): void;

    public function grant(
        PrivateObject $object,
        AuthenticatedContext $context,
        string $audience,
        string $purpose,
        DateTimeImmutable $expiresAt,
    ): AccessGrant;

    public function get(AccessGrant $grant, AuthenticatedContext $context, string $audience, string $purpose): string;

    /** @return resource */
    public function getStream(AccessGrant $grant, AuthenticatedContext $context, string $audience, string $purpose);
}
