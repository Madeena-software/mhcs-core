<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Contracts;

use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsCalculationStatus;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsReportResult;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsSession;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsUploadResult;

interface AiPacsAdapterContract
{
    /**
     * Authenticate against AI PACS and establish a session.
     *
     * @throws \App\Modules\ImageGateway\Domain\ImageGatewayException
     */
    public function authenticate(): AiPacsSession;

    /**
     * Upload an authorized DICOM file/stream to AI PACS for analysis.
     *
     * @param string|resource $dicomPayload
     * @throws \App\Modules\ImageGateway\Domain\ImageGatewayException
     */
    public function uploadStudy(mixed $dicomPayload, string $filename, ?AiPacsSession $session = null): AiPacsUploadResult;

    /**
     * Poll calculation status for an uploaded study.
     *
     * @throws \App\Modules\ImageGateway\Domain\ImageGatewayException
     */
    public function pollCalculationStatus(string|int $studyIdentifier, ?AiPacsSession $session = null): AiPacsCalculationStatus;

    /**
     * Retrieve the original AI Image Report PDF bytes.
     *
     * @throws \App\Modules\ImageGateway\Domain\ImageGatewayException
     */
    public function retrieveOriginalReport(string|int $studyIdentifier, ?AiPacsSession $session = null): AiPacsReportResult;
}
