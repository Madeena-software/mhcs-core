<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Member\Application\Contracts\InteractiveOperatorAccessResolver;
use App\Modules\Member\Application\Data\InteractiveLoginState;
use App\Modules\Member\Application\Services\InteractiveMemberLoginService;
use App\Modules\Member\Application\Services\MandatoryPasswordReplacementService;
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

    public function showOperatorLogin(): View
    {
        return view('operator.auth.login');
    }

    public function store(Request $request, InteractiveMemberLoginService $login): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => __('Masukkan email atau NIK.'),
            'password.required' => __('Masukkan kata sandi.'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $credentials = $validator->validated();

        $result = $login->authenticate($credentials['identifier'], $credentials['password'], $this->intendedPath($request));

        if ($result->state === InteractiveLoginState::Failure || $result->user === null) {
            return back()->withErrors(['identifier' => __('Email atau NIK dan kata sandi tidak sesuai.')]);
        }

        Auth::guard('web')->login($result->user);
        $request->session()->regenerate();

        if ($result->state === InteractiveLoginState::PasswordChangeRequired) {
            return redirect()->route('password.change-required');
        }

        $request->session()->forget('url.intended');

        return redirect()->to($result->destination ?? '/login');
    }

    public function storeOperatorLogin(
        Request $request,
        InteractiveMemberLoginService $login,
        InteractiveOperatorAccessResolver $operators,
    ): RedirectResponse {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [
            'identifier.required' => __('Enter your email or NIK.'),
            'password.required' => __('Enter your password.'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $credentials = $validator->validated();
        $result = $login->authenticate($credentials['identifier'], $credentials['password'], '/operator');

        if (
            $result->state === InteractiveLoginState::Failure
            || $result->user === null
            || ! $operators->canAccess($result->user)
        ) {
            return back()->withErrors(['identifier' => __('Email or NIK and password do not match.')]);
        }

        Auth::guard('web')->login($result->user);
        $request->session()->regenerate();
        $request->session()->put('url.intended', route('operator.dashboard'));

        if ($result->state === InteractiveLoginState::PasswordChangeRequired) {
            return redirect()->route('password.change-required');
        }

        $request->session()->forget('url.intended');

        return redirect()->route('operator.dashboard');
    }

    public function showPasswordChange(Request $request, InteractiveMemberLoginService $login): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $user->refresh();
        if (! $user->must_change_password) {
            $destination = $login->destinationFor($user);

            return $destination === null
                ? $this->terminate($request)
                : redirect()->to($destination);
        }

        if ($login->destinationFor($user) === null) {
            return $this->terminate($request);
        }

        return view('member.auth.change-required');
    }

    public function updatePassword(Request $request, MandatoryPasswordReplacementService $replacement, InteractiveMemberLoginService $login): RedirectResponse
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
            'current_password.required' => __('Masukkan kata sandi saat ini.'),
            'password.required' => __('Masukkan kata sandi baru.'),
            'password.confirmed' => __('Konfirmasi kata sandi baru harus sama.'),
            'password.different' => __('Kata sandi baru harus berbeda dari kata sandi saat ini.'),
            'password.min' => __('Kata sandi baru minimal 12 karakter.'),
            'password.regex' => __('Kata sandi baru harus memiliki huruf dan angka.'),
            'password.not_regex' => __('Kata sandi baru tidak boleh diawali atau diakhiri spasi.'),
            'password_confirmation.required' => __('Konfirmasi kata sandi baru harus diisi.'),
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
                'current_password' => __('Kata sandi saat ini tidak sesuai atau perubahan tidak dapat dilakukan.'),
            ]);
        }

        $user->refresh();
        Auth::guard('web')->setUser($user);
        $request->session()->regenerate();
        $intendedPath = $this->intendedPath($request);
        $request->session()->forget('url.intended');
        $destination = $login->destinationFor($user, $intendedPath);
        if ($destination === null) {
            return $this->terminate($request);
        }

        return redirect()
            ->to($destination)
            ->with('status', __('Kata sandi berhasil diperbarui.'));
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
            'identifier' => __('Akun Member tidak dapat diakses saat ini.'),
        ]);
    }

    private function intendedPath(Request $request): ?string
    {
        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || trim($intended) === '') {
            return null;
        }

        $parts = parse_url($intended);
        if ($parts === false) {
            return null;
        }

        if (isset($parts['host']) && $parts['host'] !== $request->getHost()) {
            return null;
        }
        if (isset($parts['port']) && $parts['port'] !== $request->getPort()) {
            return null;
        }

        $path = $parts['path'] ?? null;

        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : null;
    }
}
