<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Operator\Application\Services\OperatorAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnsureOperatorFrontDeskAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(OperatorAuthorization::class)->frontDesk();
        } catch (Throwable) {
            abort(403);
        }

        return $next($request);
    }
}
