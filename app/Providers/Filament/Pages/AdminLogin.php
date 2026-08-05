<?php

declare(strict_types=1);

namespace App\Providers\Filament\Pages;

use App\Shared\Authorization\AdminPanelAccessService;
use App\Shared\Security\CredentialVerifier;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;

final class AdminLogin extends Login
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->to(Filament::getUrl());
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $state = $this->form->getRawState();
        $email = is_array($state) ? $state['email'] ?? null : null;
        $password = is_array($state) ? $state['password'] ?? null : null;

        if (
            ! is_string($email)
            || ! is_string($password)
            || trim($email) === ''
            || trim($password) === ''
            || strlen($email) > 255
            || strlen($password) > 255
        ) {
            $this->fail();
        }

        $result = app(CredentialVerifier::class)->verifyForInteractiveLogin($email, $password);
        $user = $result->user;
        $panelAccess = app(AdminPanelAccessService::class);

        if (
            ! $result->authenticated
            || $user === null
            || ! $panelAccess->canAccess($user, Filament::getCurrentOrDefaultPanel())
        ) {
            if ($result->authenticated && $user !== null) {
                $panelAccess->recordDenied($user);
            }

            $this->fail();
        }

        /** @var StatefulGuard $guard */
        $guard = Filament::auth();
        $guard->login($user);

        if (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $guard->logout();
            $this->fail();
        }

        session()->regenerate();
        session()->forget('url.intended');

        return app(LoginResponse::class);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->label('Email')
                ->required()
                ->maxLength(255)
                ->autocomplete('email')
                ->autofocus(),
            TextInput::make('password')
                ->label('Kata sandi')
                ->password()
                ->revealable(false)
                ->required()
                ->maxLength(255)
                ->autocomplete('current-password'),
        ]);
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Email atau kata sandi tidak sesuai.',
        ]);
    }
}
