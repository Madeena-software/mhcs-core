<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Operator\Application\Services\GrabberClientService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuthenticateGrabberClient
{
    public function __construct(
        private GrabberClientService $grabberClients,
    ) {}

    /**
     * Handle an incoming request for authenticated Grabber clients.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Grabber-Token');
        $grabberId = $request->header('X-Grabber-ID');

        if ($token === null || trim($token) === '') {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $client = $this->grabberClients->authenticate($token, is_string($grabberId) ? $grabberId : null);

        if ($client === null) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if (! $client->isActive()) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('grabber_client', $client);

        return $next($request);
    }
}
