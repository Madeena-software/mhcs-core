<?php

declare(strict_types=1);

namespace App\Shared\Context;

use App\Shared\Identity\LocalId;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

final readonly class LaravelAuthenticatedContextProvider implements AuthenticatedContextProvider
{
    public function __construct(private AuthFactory $auth) {}

    public function current(): AuthenticatedContext
    {
        $user = $this->auth->guard()->user();

        if ($user === null) {
            return AuthenticatedContext::anonymous();
        }

        $sessionId = null;

        if (function_exists('session') && app()->bound('session.store')) {
            $sessionId = hash('sha256', (string) session()->getId());
        }

        return new AuthenticatedContext(
            actorId: LocalId::fromString((string) $user->getAuthIdentifier()),
            operationId: $this->operationId(),
            sessionId: $sessionId === null || $sessionId === '' ? null : LocalId::fromString($sessionId),
            roles: self::claims($user, 'trusted_roles'),
            permissions: self::claims($user, 'trusted_permissions'),
            purpose: 'authenticated-session',
        );
    }

    private function operationId(): CorrelationId
    {
        if (app()->bound('request')) {
            $request = app('request');
            $operationId = $request->attributes->get('mhcs.operation_id');

            if ($operationId instanceof CorrelationId) {
                return $operationId;
            }

            $operationId = CorrelationId::random();
            $request->attributes->set('mhcs.operation_id', $operationId);

            return $operationId;
        }

        return CorrelationId::random();
    }

    /** @return list<string> */
    private static function claims(object $user, string $attribute): array
    {
        $claims = $user->{$attribute} ?? [];

        return is_array($claims) ? array_values(array_filter($claims, 'is_string')) : [];
    }
}
