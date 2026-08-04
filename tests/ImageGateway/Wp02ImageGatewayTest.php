<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Domain\Security\AcceptanceException;
use App\Modules\ImageGateway\Domain\Security\ConversionManifest;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Modules\ImageGateway\Domain\Security\ManifestVerificationException;
use App\Modules\ImageGateway\Domain\Security\PermanentAcceptanceGate;
use App\Modules\ImageGateway\Domain\Security\SignedManifest;
use App\Modules\ImageGateway\Domain\Security\UntrustedImageInput;
use App\Modules\ImageGateway\Domain\Security\UntrustedImagePolicy;
use App\Modules\ImageGateway\Domain\Security\UntrustedImagePolicyException;
use App\Modules\ImageGateway\Domain\Security\ValidationEvidence;
use App\Modules\ImageGateway\Infrastructure\ImageWorkerBoundary;
use App\Shared\Security\KeyMaterial;
use DateTimeImmutable;
use Tests\TestCase;

final class Wp02ImageGatewayTest extends TestCase
{
    public function test_input_policy_is_complete_fail_closed_and_bounds_untrusted_metadata(): void
    {
        $policy = UntrustedImagePolicy::fromConfig([
            'file_count' => 2,
            'per_file_bytes' => 10,
            'total_bytes' => 20,
            'decompressed_bytes' => 40,
            'max_width' => 8,
            'max_height' => 8,
            'field_count' => 4,
            'cpu_seconds' => 5,
            'memory_bytes' => 100,
            'execution_seconds' => 6,
            'process_count' => 1,
            'temporary_storage_bytes' => 50,
            'accepted_forms' => ['fixture-container'],
            'recovery_window_seconds' => 10,
            'max_attempts' => 2,
        ]);

        $policy->assertWithin(new UntrustedImageInput(2, 10, 20, 40, 8, 8, 4, 5, 100, 6, 1, 50, 'fixture-container', 10, 2));

        $this->expectException(UntrustedImagePolicyException::class);
        UntrustedImagePolicy::fromConfig([]);
    }

    public function test_input_policy_rejects_every_exceeded_bound(): void
    {
        $policy = new UntrustedImagePolicy(1, 10, 10, 10, 10, 10, 10, 10, 10, 10, 1, 10, ['fixture'], 10, 1);
        $input = new UntrustedImageInput(2, 10, 10, 10, 10, 10, 10, 10, 10, 10, 1, 10, 'fixture', 10, 1);

        $this->expectException(UntrustedImagePolicyException::class);
        $policy->assertWithin($input);
    }

    public function test_manifest_signing_binds_identity_checksums_version_time_correlation_and_key(): void
    {
        $signer = new ManifestSigner(['test-key' => KeyMaterial::from(str_repeat('m', 32))]);
        $manifest = $this->manifest();
        $signed = $signer->sign($manifest);

        $this->assertSame($manifest->toArray(), $signer->verify($signed)->toArray());

        foreach ([
            'conversion_job_id' => 'changed-job',
            'radiograph_checksum' => str_repeat('b', 64),
            'gain_checksum' => str_repeat('c', 64),
            'metadata_checksum' => str_repeat('d', 64),
            'manifest_version' => 2,
            'issued_at' => '2026-08-04T10:01:00+00:00',
            'correlation_id' => 'changed-operation',
            'key_id' => 'unknown-key',
        ] as $field => $value) {
            $changed = $manifest->toArray();
            $changed[$field] = $value;

            try {
                $signer->verify(new SignedManifest(ConversionManifest::fromArray($changed), $signed->signature));
                $this->fail("Manifest mutation {$field} was accepted.");
            } catch (ManifestVerificationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(ManifestVerificationException::class);
        $signer->verify(new SignedManifest($manifest, 'invalid-signature'));
    }

    public function test_permanent_acceptance_requires_explicit_matching_validator_evidence(): void
    {
        $signer = new ManifestSigner(['test-key' => KeyMaterial::from(str_repeat('m', 32))]);
        $signed = $signer->sign($this->manifest());
        $evidence = new ValidationEvidence(
            valid: true,
            conversionJobId: 'job-1',
            radiographChecksum: str_repeat('a', 64),
            gainChecksum: str_repeat('b', 64),
            metadataChecksum: str_repeat('c', 64),
            manifestSignature: $signed->signature,
            identifiers: ['study' => 'study-1'],
        );

        (new PermanentAcceptanceGate($signer))->accept($signed, $evidence, ['study' => 'study-1']);

        $this->expectException(AcceptanceException::class);
        (new PermanentAcceptanceGate($signer))->accept(
            $signed,
            new ValidationEvidence(
                valid: true,
                conversionJobId: 'changed-job',
                radiographChecksum: $evidence->radiographChecksum,
                gainChecksum: $evidence->gainChecksum,
                metadataChecksum: $evidence->metadataChecksum,
                manifestSignature: $evidence->manifestSignature,
                identifiers: $evidence->identifiers,
            ),
            ['study' => 'study-1'],
        );
    }

    public function test_only_image_worker_can_cross_the_declared_boundary(): void
    {
        ImageWorkerBoundary::assertCaller(ImageWorkerBoundary::CALLER);
        $this->assertFalse(ImageWorkerBoundary::limits()['public_proxy']);
        $this->assertFalse(ImageWorkerBoundary::limits()['application_database_credentials']);

        $this->expectException(\RuntimeException::class);
        ImageWorkerBoundary::assertCaller('member');
    }

    private function manifest(): ConversionManifest
    {
        return new ConversionManifest(
            conversionJobId: 'job-1',
            radiographChecksum: str_repeat('a', 64),
            gainChecksum: str_repeat('b', 64),
            metadataChecksum: str_repeat('c', 64),
            manifestVersion: 1,
            issuedAt: new DateTimeImmutable('2026-08-04T10:00:00+00:00'),
            correlationId: 'operation-1',
            keyId: 'test-key',
        );
    }
}
