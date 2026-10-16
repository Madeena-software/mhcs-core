<?php

declare(strict_types=1);

namespace App\Modules\Doctor;

use App\Modules\Doctor\Application\Contracts\ClinicalReopenGuard;
use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;
use App\Modules\Doctor\Application\Contracts\FinalReport;
use App\Modules\Doctor\Application\Contracts\RadiologistAccess;
use App\Modules\Doctor\Application\Services\ClinicalFinalizationGuard;
use App\Modules\Doctor\Application\Services\FinalReportRead;
use App\Modules\Doctor\Application\Services\RadiologistLoginAccess;
use App\Modules\Doctor\Application\Services\RadiologistWorkspace;
use App\Modules\Doctor\Infrastructure\UnknownClinicalSourceInventory;
use App\Modules\Doctor\Infrastructure\WorkspaceClinicalStatusSource;
use App\Modules\Member\Application\Contracts\InteractiveRadiologistAccess;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class DoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FinalReport::class, FinalReportRead::class);
        $this->app->bind(ClinicalSourceInventory::class, UnknownClinicalSourceInventory::class);
        $this->app->make(ModuleRegistry::class)->register('Doctor');
        $this->app->scoped(RadiologistWorkspace::class);
        $this->app->alias(RadiologistWorkspace::class, RadiologistAccess::class);
        $this->app->bind(InteractiveRadiologistAccess::class, RadiologistLoginAccess::class);
        $this->app->bind(ClinicalReopenGuard::class, ClinicalFinalizationGuard::class);
        $this->app->bind(ClinicalStatusSource::class, WorkspaceClinicalStatusSource::class);
    }
}
