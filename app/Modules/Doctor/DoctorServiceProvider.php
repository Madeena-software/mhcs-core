<?php

declare(strict_types=1);

namespace App\Modules\Doctor;

use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class DoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Doctor');
    }
}
