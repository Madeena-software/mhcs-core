<?php

declare(strict_types=1);

namespace App\Modules\Operator;

use App\Modules\Operator\Infrastructure\OperatorActiveSiteResolver;
use App\Shared\Authorization\ActiveSiteResolver;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class OperatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Operator');
        $this->app->scoped(ActiveSiteResolver::class, OperatorActiveSiteResolver::class);
    }
}
