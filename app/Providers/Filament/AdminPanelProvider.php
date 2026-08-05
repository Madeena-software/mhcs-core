<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\EnforceMandatoryPasswordChange;
use App\Modules\Member\Filament\Resources\Members\MemberResource;
use App\Providers\Filament\Pages\AdminLogin;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->brandName('MHCS Administration')
            ->login(AdminLogin::class)
            ->resources([
                MemberResource::class,
            ])
            ->navigationGroups([
                'Member',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnforceMandatoryPasswordChange::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }
}
