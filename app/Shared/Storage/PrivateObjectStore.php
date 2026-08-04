<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use App\Shared\Context\AuthenticatedContext;
use DateTimeImmutable;

interface PrivateObjectStore
{
    public function put(string $contents, AuthenticatedContext $context, string $purpose): PrivateObject;

    public function grant(
        PrivateObject $object,
        AuthenticatedContext $context,
        string $audience,
        string $purpose,
        DateTimeImmutable $expiresAt,
    ): AccessGrant;

    public function get(AccessGrant $grant, AuthenticatedContext $context, string $audience, string $purpose): string;
}
