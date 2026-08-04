<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

final readonly class OperatorMemberProjection
{
    private const FIELDS = ['member_id', 'display_name', 'masked_identifier'];

    /** @param array<string, mixed> $fields */
    private function __construct(private array $fields) {}

    /** @param array<string, mixed> $fields */
    public static function fromTrustedContext(array $fields, AuthenticatedContext $context, string $purpose): self
    {
        if (
            $context->actorId === null
            || $context->operationId === null
            || $context->siteId === null
            || ! in_array('operator', $context->roles, true)
            || ! in_array($purpose, $context->permissions, true)
            || trim($purpose) === ''
            || $context->purpose !== $purpose
        ) {
            throw new MemberProjectionException('A trusted context and named purpose are required.');
        }

        foreach ($fields as $key => $value) {
            if (! in_array($key, self::FIELDS, true) || is_array($value) || is_object($value) || is_resource($value)) {
                throw new MemberProjectionException('Member projections require explicit scalar allowlisted fields.');
            }

            if (
                $key === 'masked_identifier'
                && is_string($value)
                && preg_match('/[*xX•]/u', $value) !== 1
            ) {
                throw new MemberProjectionException('Member projections cannot contain an unmasked identifier.');
            }
        }

        return new self(array_intersect_key($fields, array_flip(self::FIELDS)));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->fields;
    }
}
