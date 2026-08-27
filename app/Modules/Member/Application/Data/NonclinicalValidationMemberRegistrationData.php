<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use App\Shared\Validation\NonclinicalValidationContext;
use InvalidArgumentException;

final readonly class NonclinicalValidationMemberRegistrationData
{
    public function __construct(
        public string $operationId,
        public string $userId,
        public string $contextKey = NonclinicalValidationContext::KEY,
        public string $markerNamespace = NonclinicalValidationContext::MARKER_NAMESPACE,
        public string $markerValue = NonclinicalValidationContext::KEY,
        public string $displayName = 'Nonclinical validation subject',
    ) {
        if (trim($this->operationId) === '' || trim($this->userId) === '') {
            throw new InvalidArgumentException('Nonclinical validation registration identity is required.');
        }
    }
}
