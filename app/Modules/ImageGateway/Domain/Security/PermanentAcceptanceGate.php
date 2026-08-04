<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Domain\Security;

final readonly class PermanentAcceptanceGate
{
    public function __construct(private ManifestSigner $signer) {}

    /**
     * @param  array<string, string>  $expectedIdentifiers
     */
    public function accept(SignedManifest $signed, ValidationEvidence $evidence, array $expectedIdentifiers): void
    {
        $manifest = $this->signer->verify($signed);

        if (
            ! $evidence->valid
            || $evidence->conversionJobId !== $manifest->conversionJobId
            || $evidence->radiographChecksum !== $manifest->radiographChecksum
            || $evidence->gainChecksum !== $manifest->gainChecksum
            || $evidence->metadataChecksum !== $manifest->metadataChecksum
            || ! hash_equals($signed->signature, $evidence->manifestSignature)
            || $evidence->identifiers !== $expectedIdentifiers
        ) {
            throw new AcceptanceException('Permanent image acceptance evidence does not match the frozen manifest.');
        }
    }
}
