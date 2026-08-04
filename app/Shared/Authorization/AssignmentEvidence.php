<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Shared\Identity\LocalId;
use InvalidArgumentException;

final readonly class AssignmentEvidence
{
    public function __construct(
        public LocalId $assignmentId,
        public LocalId $actorId,
        public LocalId $siteId,
        public int $version,
    ) {
        if ($this->version < 1) {
            throw new InvalidArgumentException('Assignment evidence must have a positive version.');
        }
    }
}
