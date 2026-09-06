<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsReportResult;

interface AiPacsReportDownloaderContract
{
    /**
     * Download the authentic Image Report PDF using Playwright browser automation.
     *
     * @throws \App\Modules\ImageGateway\Domain\ImageGatewayException
     */
    public function downloadImageReport(
        string|int $studyIdentifier,
        int $aiCalcId,
        string $destinationPath,
        ?string $correlationId = null,
        string $viewerType = 'CR',
        string $pacs = 'fei',
    ): AiPacsReportResult;
}
