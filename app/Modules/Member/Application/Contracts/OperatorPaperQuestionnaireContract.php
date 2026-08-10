<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;
use Illuminate\Http\UploadedFile;

interface OperatorPaperQuestionnaireContract
{
    public const PURPOSE = 'operator.basic-examination.questionnaire';

    /** @return array{questionnaire_id: string, completed_at: string} */
    public function record(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $operatorProfileId,
        string $memberId,
        string $bookingId,
        string $scheduleId,
        string $operationId,
        UploadedFile $photo,
    ): array;
}
