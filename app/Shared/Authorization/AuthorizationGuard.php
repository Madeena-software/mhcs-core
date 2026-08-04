<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Identity\LocalId;

final readonly class AuthorizationGuard
{
    public function __construct(private AuthenticatedContextProvider $provider) {}

    public function current(string $purpose, ?LocalId $expectedActor = null, ?LocalId $expectedSite = null): AuthenticatedContext
    {
        $context = $this->provider->current();

        if ($context->actorId === null || $context->operationId === null) {
            throw new AuthorizationException('A trusted authenticated context is required.');
        }

        if ($context->purpose !== $purpose) {
            if ($context->purpose !== 'authenticated-session') {
                throw new AuthorizationException('The trusted purpose does not match.');
            }

            $context = $context->forPurpose($purpose);
        }

        if ($expectedActor !== null && ! $context->actorId->equals($expectedActor)) {
            throw new AuthorizationException('The trusted actor does not match.');
        }

        if ($expectedSite !== null && ($context->siteId === null || ! $context->siteId->equals($expectedSite))) {
            throw new AuthorizationException('The trusted site does not match.');
        }

        return $context;
    }

    /** Caller claims are assertions only; they never create authorization context. */
    public function authorizeClaims(array $claims, string $purpose): AuthenticatedContext
    {
        $context = $this->current($purpose);

        foreach (['actor_id', 'site_id', 'case_id'] as $claim) {
            if (! array_key_exists($claim, $claims) || $claims[$claim] === null) {
                continue;
            }

            $trusted = match ($claim) {
                'actor_id' => $context->actorId,
                'site_id' => $context->siteId,
                'case_id' => $context->caseId,
            };

            if ($trusted === null || (string) $trusted !== (string) $claims[$claim]) {
                throw new AuthorizationException("Caller claim [{$claim}] does not match trusted context.");
            }
        }

        if (array_key_exists('assignment_id', $claims) && $claims['assignment_id'] !== null) {
            throw new AuthorizationException('Caller assignment claims require trusted assignment evidence.');
        }

        foreach (['role' => $context->roles, 'permission' => $context->permissions] as $claim => $trusted) {
            if (! array_key_exists($claim, $claims) || $claims[$claim] === null) {
                continue;
            }

            $requested = is_array($claims[$claim]) ? $claims[$claim] : [$claims[$claim]];

            if (
                count(array_filter($requested, 'is_string')) !== count($requested)
                || array_diff($requested, $trusted) !== []
            ) {
                throw new AuthorizationException("Caller claim [{$claim}] is not trusted.");
            }
        }

        return $context;
    }
}
