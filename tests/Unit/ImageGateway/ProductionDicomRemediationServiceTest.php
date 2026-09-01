<?php

declare(strict_types=1);

namespace Tests\Unit\ImageGateway;

use App\Modules\ImageGateway\Application\Contracts\DicomConverter;
use App\Modules\ImageGateway\Application\Services\ProductionDicomRemediationService;
use App\Modules\ImageGateway\Domain\Security\ManifestSigner;
use App\Shared\Audit\AuditStore;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Mockery;
use PHPUnit\Framework\TestCase;

final class ProductionDicomRemediationServiceTest extends TestCase
{
    public function test_only_the_two_approved_modes_and_fixed_targets_exist(): void
    {
        $this->assertSame([
            't005_failed_capture_retry',
            'dcm_zshnsx90_regenerate',
        ], ProductionDicomRemediationService::MODES);
        $this->assertSame('46165c59-1fa6-4f58-9485-a515529c0f76', ProductionDicomRemediationService::T005_ADMISSION_ID);
        $this->assertSame('ed367bcf-4430-496c-a006-f3e8479421d4', ProductionDicomRemediationService::DCM_STUDY_ID);
        $this->assertSame('DCM-ZSHNSX90', ProductionDicomRemediationService::DCM_REFERENCE);
    }

    public function test_invalid_mode_is_rejected_before_any_target_lookup(): void
    {
        $service = new ProductionDicomRemediationService(
            Mockery::mock(PrivateObjectStore::class),
            Mockery::mock(DicomConverter::class),
            new ManifestSigner([]),
            Mockery::mock(AuditStore::class),
            Mockery::mock(Clock::class),
        );

        $this->expectException(\RuntimeException::class);
        $service->run('arbitrary-record-mutation', 'preflight', ProductionDicomRemediationService::REQUIRED_RUNTIME_FIX, ProductionDicomRemediationService::REQUIRED_RUNTIME_FIX);
    }

    public function test_missing_required_mpips_fix_proof_is_rejected_before_preflight(): void
    {
        $service = new ProductionDicomRemediationService(
            Mockery::mock(PrivateObjectStore::class),
            Mockery::mock(DicomConverter::class),
            new ManifestSigner([]),
            Mockery::mock(AuditStore::class),
            Mockery::mock(Clock::class),
        );

        $this->expectException(\RuntimeException::class);
        $service->run(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight', 'unknown', ProductionDicomRemediationService::REQUIRED_RUNTIME_FIX);
    }

    public function test_mpips_revision_must_be_a_sha_and_carry_a_verified_ancestor_proof(): void
    {
        $service = new ProductionDicomRemediationService(
            Mockery::mock(PrivateObjectStore::class),
            Mockery::mock(DicomConverter::class),
            new ManifestSigner([]),
            Mockery::mock(AuditStore::class),
            Mockery::mock(Clock::class),
        );

        $this->expectException(\RuntimeException::class);
        $service->run(ProductionDicomRemediationService::T005_FAILED_CAPTURE_RETRY, 'preflight', 'not-a-revision', 'verified-ancestor:'.ProductionDicomRemediationService::REQUIRED_RUNTIME_FIX);
    }
}
