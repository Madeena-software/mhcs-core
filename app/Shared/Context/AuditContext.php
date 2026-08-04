<?php

declare(strict_types=1);

namespace App\Shared\Context;

use App\Shared\Identity\LocalId;

final readonly class AuditContext
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public ?CorrelationId $operationId = null,
        public ?LocalId $actorId = null,
        public ?LocalId $sessionId = null,
        public array $roles = [],
        public array $permissions = [],
        public ?LocalId $siteId = null,
        public ?LocalId $caseId = null,
        public ?string $purpose = null,
    ) {}

    public static function fromAuthenticatedContext(AuthenticatedContext $context): self
    {
        return new self(
            operationId: $context->operationId,
            actorId: $context->actorId,
            sessionId: $context->sessionId,
            roles: $context->roles,
            permissions: $context->permissions,
            siteId: $context->siteId,
            caseId: $context->caseId,
            purpose: $context->purpose,
        );
    }
}
