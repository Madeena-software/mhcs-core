<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Member\Application\Services\MemberContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureMemberPortalAccess
{
    public function __construct(private MemberContextResolver $members) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $user->account_status !== 'active'
            || ! ($user->login_enabled ?? false)
        ) {
            return $this->failClosed($request);
        }

        if ($user->must_change_password) {
            return redirect()->route('password.change-required');
        }

        $member = $this->members->resolveForUserId((string) $user->getAuthIdentifier());
        if ($member === null || ! $this->members->isEligibleAdult($member)) {
            return $this->failClosed($request);
        }

        $request->attributes->set('member_context', $member);

        return $next($request);
    }

    private function failClosed(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'identifier' => 'Akun Member tidak dapat diakses saat ini.',
        ]);
    }
}
