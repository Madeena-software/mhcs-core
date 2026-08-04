<?php

declare(strict_types=1);

namespace App\Shared\Context;

use App\Shared\Identity\LocalId;
use InvalidArgumentException;

final readonly class AuthenticatedContext
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public ?LocalId $actorId = null,
        public ?CorrelationId $operationId = null,
        public ?LocalId $sessionId = null,
        array $roles = [],
        array $permissions = [],
        public ?LocalId $siteId = null,
        public ?LocalId $caseId = null,
        public ?string $purpose = null,
    ) {
        $this->roles = $this->claims($roles, 'role');
        $this->permissions = $this->claims($permissions, 'permission');

        if ($this->purpose !== null && trim($this->purpose) === '') {
            throw new InvalidArgumentException('A declared purpose cannot be empty.');
        }
    }

    /** @var list<string> */
    public readonly array $roles;

    /** @var list<string> */
    public readonly array $permissions;

    public static function anonymous(): self
    {
        return new self;
    }

    public function forPurpose(string $purpose): self
    {
        return new self(
            actorId: $this->actorId,
            operationId: $this->operationId,
            sessionId: $this->sessionId,
            roles: $this->roles,
            permissions: $this->permissions,
            siteId: $this->siteId,
            caseId: $this->caseId,
            purpose: $purpose,
        );
    }

    /**
     * @param  array<int|string, mixed>  $claims
     * @return list<string>
     */
    private function claims(array $claims, string $label): array
    {
        $values = [];

        foreach ($claims as $claim) {
            if (! is_string($claim) || trim($claim) === '') {
                throw new InvalidArgumentException("Each {$label} claim must be a non-empty string.");
            }

            $values[] = trim($claim);
        }

        return array_values(array_unique($values));
    }
}
