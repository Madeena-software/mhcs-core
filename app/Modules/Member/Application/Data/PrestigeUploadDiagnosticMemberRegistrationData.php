<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use App\Shared\Validation\NonclinicalValidationContext;
use InvalidArgumentException;

final readonly class PrestigeUploadDiagnosticMemberRegistrationData
{
    public string $contextKey;

    public string $markerNamespace;

    public string $markerValue;

    public string $displayName;

    public function __construct(public string $operationId, public string $userId, public string $subjectKey)
    {
        if (trim($this->operationId) === '' || trim($this->userId) === '' || ! in_array($this->subjectKey, ['gbsuparta', 'ipang'], true)) {
            throw new InvalidArgumentException('Prestige diagnostic registration data is invalid.');
        }
        $this->contextKey = NonclinicalValidationContext::PRESTIGE_KEY;
        $this->markerNamespace = NonclinicalValidationContext::PRESTIGE_MARKER_NAMESPACE;
        $this->markerValue = $this->subjectKey;
        $this->displayName = $this->subjectKey;
    }
}
