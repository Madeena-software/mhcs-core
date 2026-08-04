<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway;

use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class ImageGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Image Gateway');
    }
}
