<?php

declare(strict_types=1);

namespace App\Modules\Operator;

use App\Modules\Member\Application\Contracts\InteractiveOperatorAccessResolver;
use App\Modules\Member\Application\Contracts\ShiftScheduleClosureHandler;
use App\Modules\Member\Application\Contracts\TrustedOperatorIdentityVerificationContextResolver;
use App\Modules\Member\Application\Contracts\TrustedOperatorSiteContextResolver;
use App\Modules\Operator\Application\Services\InteractiveOperatorAccessService;
use App\Modules\Operator\Application\Services\RadiographySessionLocatorService;
use App\Modules\Operator\Infrastructure\OperatorActiveSiteResolver;
use App\Modules\Operator\Infrastructure\TrustedOperatorIdentityVerificationContextResolver as OperatorTrustedOperatorIdentityVerificationContextResolver;
use App\Modules\Operator\Infrastructure\TrustedOperatorSiteContextResolver as OperatorTrustedOperatorSiteContextResolver;
use App\Shared\Authorization\ActiveSiteResolver;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class OperatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Operator');
        $this->app->scoped(ActiveSiteResolver::class, OperatorActiveSiteResolver::class);
        $this->app->scoped(InteractiveOperatorAccessResolver::class, InteractiveOperatorAccessService::class);
        $this->app->scoped(TrustedOperatorIdentityVerificationContextResolver::class, OperatorTrustedOperatorIdentityVerificationContextResolver::class);
        $this->app->scoped(TrustedOperatorSiteContextResolver::class, OperatorTrustedOperatorSiteContextResolver::class);
        $this->app->scoped(ShiftScheduleClosureHandler::class, RadiographySessionLocatorService::class);
    }
}
