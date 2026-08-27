<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use App\Shared\Validation\NonclinicalValidationContext;
use InvalidArgumentException;

final readonly class NonclinicalValidationMemberRegistrationData
{
    public string $contextKey;

    public string $markerNamespace;

    public string $markerValue;

    public string $displayName;

    public function __construct(
        public string $operationId,
        public string $userId,
    ) {
        if (trim($this->operationId) === '' || trim($this->userId) === '') {
            throw new InvalidArgumentException('Nonclinical validation registration identity is required.');
        }
        $this->contextKey = NonclinicalValidationContext::KEY;
        $this->markerNamespace = NonclinicalValidationContext::MARKER_NAMESPACE;
        $this->markerValue = NonclinicalValidationContext::KEY;
        $this->displayName = 'Nonclinical validation subject';
    }
}
