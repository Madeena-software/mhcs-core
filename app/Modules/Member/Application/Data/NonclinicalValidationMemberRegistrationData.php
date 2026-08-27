<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use InvalidArgumentException;

final readonly class NonclinicalValidationMemberRegistrationData
{
    public function __construct(
        public string $operationId,
        public string $userId,
    ) {
        if (trim($this->operationId) === '' || trim($this->userId) === '') {
            throw new InvalidArgumentException('Nonclinical validation registration identity is required.');
        }
    }
}
