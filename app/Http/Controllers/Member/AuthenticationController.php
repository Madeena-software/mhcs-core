<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Member\Application\Data\InteractiveLoginState;
use App\Modules\Member\Application\Services\InteractiveMemberLoginService;
use App\Modules\Member\Application\Services\MandatoryPasswordReplacementService;
use App\Modules\Member\Application\Services\MemberContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AuthenticationController extends Controller
{
    public function showLogin(): View
    {
        return view('member.auth.login');
    }

    public function store(Request $request, InteractiveMemberLoginService $login, MemberContextResolver $members): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => 'Masukkan email atau NIK.',
            'password.required' => 'Masukkan kata sandi.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $credentials = $validator->validated();

        $result = $login->authenticate($credentials['identifier'], $credentials['password']);

        if ($result->state === InteractiveLoginState::Failure || $result->user === null) {
            return back()->withErrors(['identifier' => 'Email atau NIK dan kata sandi tidak sesuai.']);
        }

        Auth::guard('web')->login($result->user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        if ($result->state === InteractiveLoginState::PasswordChangeRequired) {
            return redirect()->route('password.change-required');
        }

        $member = $members->resolveForUserId((string) $result->user->getAuthIdentifier());
        if ($member === null) {
            return $this->terminate($request);
        }

        return redirect()->route($members->isComplete($member) ? 'member.dashboard' : 'member.profile');
    }

    public function showPasswordChange(Request $request, MemberContextResolver $members): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $user->refresh();
        if (! $user->must_change_password) {
            $member = $members->resolveForUserId((string) $user->getAuthIdentifier());

            return $member === null
                ? $this->terminate($request)
                : redirect()->route($members->isComplete($member) ? 'member.dashboard' : 'member.profile');
        }

        $member = $members->resolveForUserId((string) $user->getAuthIdentifier());
        if ($member === null || ! $members->isEligibleAdult($member)) {
            return $this->terminate($request);
        }

        return view('member.auth.change-required', ['memberName' => $member->name]);
    }

    public function updatePassword(Request $request, MandatoryPasswordReplacementService $replacement, MemberContextResolver $members): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                'min:12',
                'regex:/[A-Za-z]/',
                'regex:/[0-9]/',
                'not_regex:/^\s|\s$/',
            ],
            'password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required' => 'Masukkan kata sandi saat ini.',
            'password.required' => 'Masukkan kata sandi baru.',
            'password.confirmed' => 'Konfirmasi kata sandi baru harus sama.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
            'password.min' => 'Kata sandi baru minimal 12 karakter.',
            'password.regex' => 'Kata sandi baru harus memiliki huruf dan angka.',
            'password.not_regex' => 'Kata sandi baru tidak boleh diawali atau diakhiri spasi.',
            'password_confirmation.required' => 'Konfirmasi kata sandi baru harus diisi.',
        ]);

        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        try {
            $replacement->replace(
                (string) $user->getAuthIdentifier(),
                $validated['current_password'],
                $validated['password'],
                (string) Str::uuid(),
            );
        } catch (\Throwable) {
            return back()->withErrors([
                'current_password' => 'Kata sandi saat ini tidak sesuai atau perubahan tidak dapat dilakukan.',
            ]);
        }

        $user->refresh();
        Auth::guard('web')->setUser($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $member = $members->resolveForUserId((string) $user->getAuthIdentifier());

        if ($member === null) {
            return $this->terminate($request);
        }

        return redirect()
            ->route($members->isComplete($member) ? 'member.dashboard' : 'member.profile')
            ->with('status', 'Kata sandi berhasil diperbarui.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function terminate(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'identifier' => 'Akun Member tidak dapat diakses saat ini.',
        ]);
    }
}
