<?php

declare(strict_types=1);

namespace App\Modules\Member;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Contracts\OperatorIdentityVerificationContract;
use App\Modules\Member\Application\Contracts\OperatorPaperConsentContract;
use App\Modules\Member\Application\Contracts\OperatorSiteReferenceSynchronizer;
use App\Modules\Member\Application\Contracts\OperatorVitalSignsContract;
use App\Modules\Member\Application\Services\MemberCredentialIdentifierResolver;
use App\Modules\Member\Application\Services\Mvp04AttendanceService;
use App\Modules\Member\Application\Services\Mvp04OperatorIdentityVerificationService;
use App\Modules\Member\Application\Services\Mvp04OperatorSiteReferenceService;
use App\Modules\Member\Application\Services\Mvp04PaperConsentService;
use App\Modules\Member\Application\Services\Mvp04VitalSignsService;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Security\CredentialIdentifierResolver;
use App\Shared\Security\CredentialVerifier;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Time\Clock;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class MemberServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Member');
        $this->app->scoped(OperatorAttendanceContract::class, Mvp04AttendanceService::class);
        $this->app->scoped(OperatorIdentityVerificationContract::class, Mvp04OperatorIdentityVerificationService::class);
        $this->app->scoped(OperatorPaperConsentContract::class, Mvp04PaperConsentService::class);
        $this->app->scoped(OperatorVitalSignsContract::class, Mvp04VitalSignsService::class);
        $this->app->scoped(OperatorSiteReferenceSynchronizer::class, Mvp04OperatorSiteReferenceService::class);
        $this->app->singleton(CredentialIdentifierResolver::class, MemberCredentialIdentifierResolver::class);
        $this->app->singleton(CredentialVerifier::class, function ($app): CredentialVerifier {
            return new CredentialVerifier(
                $app->make(ProtectedIdentifierService::class),
                $app->make(AuditStore::class),
                $app->make(AuthenticatedContextProvider::class),
                $app->make(Clock::class),
                $app->make(CredentialIdentifierResolver::class),
            );
        });
    }
}
