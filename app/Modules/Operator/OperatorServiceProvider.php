<?php

declare(strict_types=1);

namespace App\Modules\Operator;

use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class OperatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Operator');
    }
}
