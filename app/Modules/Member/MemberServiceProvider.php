<?php

declare(strict_types=1);

namespace App\Modules\Member;

use App\Modules\Member\Application\Contracts\OperatorAttendanceContract;
use App\Modules\Member\Application\Contracts\OperatorSiteReferenceSynchronizer;
use App\Modules\Member\Application\Services\MemberCredentialIdentifierResolver;
use App\Modules\Member\Application\Services\Mvp04AttendanceService;
use App\Modules\Member\Application\Services\Mvp04OperatorSiteReferenceService;
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
