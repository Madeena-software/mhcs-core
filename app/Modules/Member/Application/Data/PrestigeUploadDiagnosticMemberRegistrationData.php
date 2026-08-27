<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use InvalidArgumentException;

final readonly class PrestigeUploadDiagnosticMemberRegistrationData
{
    public function __construct(
        public string $operationId,
        public string $userId,
        public string $contextKey,
        public string $markerNamespace,
        public string $markerValue,
        public string $displayName,
    ) {
        if (trim($this->operationId) === '' || trim($this->userId) === '' || trim($this->contextKey) === '' || trim($this->markerNamespace) === '' || trim($this->markerValue) === '' || trim($this->displayName) === '') {
            throw new InvalidArgumentException('Prestige diagnostic registration data is required.');
        }
    }
}
