<?php

declare(strict_types=1);

namespace App\Shared\Security;

use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;

final readonly class ProtectedIdentifierService
{
    public function __construct(
        private Encrypter $encrypter,
        private KeyMaterial $key,
    ) {}

    /** @return array{encrypted_display: string, lookup_digest: string} */
    public function protect(string $value): array
    {
        $normalized = $this->normalize($value);

        return [
            'encrypted_display' => $this->encrypter->encryptString($normalized),
            'lookup_digest' => $this->lookupDigest($normalized),
        ];
    }

    public function lookupDigest(string $value): string
    {
        return $this->key->digest($this->normalize($value));
    }

    public function display(string $encrypted): string
    {
        return $this->encrypter->decryptString($encrypted);
    }

    private function normalize(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new InvalidArgumentException('A protected identifier cannot be empty.');
        }

        return $normalized;
    }
}
