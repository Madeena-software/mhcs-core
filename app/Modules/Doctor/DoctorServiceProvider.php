<?php

declare(strict_types=1);

namespace App\Modules\Doctor;

use App\Modules\Doctor\Application\Contracts\ClinicalReopenGuard;
use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Modules\Doctor\Application\Services\ClinicalFinalizationGuard;
use App\Modules\Doctor\Infrastructure\UnavailableClinicalStatusSource;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class DoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Doctor');
        $this->app->bind(ClinicalReopenGuard::class, ClinicalFinalizationGuard::class);
        $this->app->bind(ClinicalStatusSource::class, UnavailableClinicalStatusSource::class);
    }
}
