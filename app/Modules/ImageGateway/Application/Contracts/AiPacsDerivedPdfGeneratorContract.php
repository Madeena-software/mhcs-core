<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

use App\Modules\ImageGateway\Domain\AiPacsDerivedPdfResult;

interface AiPacsDerivedPdfGeneratorContract
{
    /**
     * Deterministically generate derived Indonesian MHCS PDF from original vendor PDF and provenance metadata.
     *
     * @param string $originalPdfPath Path to the verified original vendor PDF in local temporary storage
     * @param array<string, mixed> $provenanceData Clinical, demographic, and session provenance metadata
     * @param string $destinationPath Path where the derived PDF must be saved
     * @return AiPacsDerivedPdfResult
     */
    public function generateDerivedPdf(
        string $originalPdfPath,
        array $provenanceData,
        string $destinationPath,
    ): AiPacsDerivedPdfResult;
}
