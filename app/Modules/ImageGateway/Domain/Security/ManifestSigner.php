<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

use App\Shared\Security\KeyMaterial;

final readonly class ManifestSigner
{
    /** @param array<string, KeyMaterial> $keys */
    public function __construct(private array $keys) {}

    public function sign(ConversionManifest $manifest): SignedManifest
    {
        $key = $this->keys[$manifest->keyId] ?? null;

        if ($key === null) {
            throw new ManifestVerificationException('Manifest signing key is unknown.');
        }

        return new SignedManifest($manifest, $key->digest($this->canonical($manifest)));
    }

    public function verify(SignedManifest $signed): ConversionManifest
    {
        $manifest = $signed->manifest;
        $key = $this->keys[$manifest->keyId] ?? null;

        if ($key === null || ! hash_equals($key->digest($this->canonical($manifest)), $signed->signature)) {
            throw new ManifestVerificationException('Manifest signature is invalid.');
        }

        return $manifest;
    }

    private function canonical(ConversionManifest $manifest): string
    {
        return json_encode($manifest->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
