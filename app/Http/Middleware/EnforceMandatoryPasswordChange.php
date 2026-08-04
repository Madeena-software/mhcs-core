<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforceMandatoryPasswordChange
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->account_status !== 'active' || ! ($user->login_enabled ?? false)) {
            return $this->failClosed($request);
        }

        if (
            $user->must_change_password
            && ! in_array($request->route()?->getName(), [
                'password.change-required',
                'password.change-required.update',
                'logout',
            ], true)
        ) {
            return redirect()->route('password.change-required');
        }

        return $next($request);
    }

    private function failClosed(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
