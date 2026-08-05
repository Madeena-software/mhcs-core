<?php

namespace App\Providers;

use App\Shared\Application\Bus\InProcessCommandBus;
use App\Shared\Application\Bus\InProcessQueryBus;
use App\Shared\Application\Contracts\CommandBus;
use App\Shared\Application\Contracts\QueryBus;
use App\Shared\Audit\AuditStore;
use App\Shared\Audit\DatabaseAuditStore;
use App\Shared\Auth\AccountStateUserProvider;
use App\Shared\Authorization\AdminPanelAccessService;
use App\Shared\Authorization\AuthorizationClaimResolver;
use App\Shared\Authorization\DatabaseAuthorizationClaimResolver;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\LaravelAuthenticatedContextProvider;
use App\Shared\Infrastructure\Idempotency\DatabaseIdempotencyStore;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\DatabaseOutboxStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Security\KeyMaterial;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Storage\EncryptedLocalObjectStore;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use App\Shared\Time\SystemClock;
use App\Shared\Topology\ModuleRegistry;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->scoped(AuthorizationClaimResolver::class, DatabaseAuthorizationClaimResolver::class);
        $this->app->scoped(AuthenticatedContextProvider::class, LaravelAuthenticatedContextProvider::class);
        $this->app->scoped(AdminPanelAccessService::class);
        $this->app->singleton(AuditStore::class, DatabaseAuditStore::class);
        $this->app->singleton(ProtectedIdentifierService::class, function ($app): ProtectedIdentifierService {
            return new ProtectedIdentifierService(
                $app->make(Encrypter::class),
                KeyMaterial::fromConfig(config('mhcs.security.identifier_key')),
            );
        });
        $this->app->singleton(PrivateObjectStore::class, function ($app): PrivateObjectStore {
            return new EncryptedLocalObjectStore(
                KeyMaterial::fromConfig(config('mhcs.security.object_key')),
                KeyMaterial::fromConfig(config('mhcs.security.grant_key')),
                $app->make(Clock::class),
            );
        });
        $this->app->singleton(CommandBus::class, fn ($app) => new InProcessCommandBus($app));
        $this->app->singleton(QueryBus::class, fn ($app) => new InProcessQueryBus($app));
        $this->app->singleton(OutboxStore::class, DatabaseOutboxStore::class);
        $this->app->singleton(IdempotencyStore::class, DatabaseIdempotencyStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('mhcs-eloquent', function ($app, array $config): AccountStateUserProvider {
            return new AccountStateUserProvider($app['hash'], $config['model']);
        });
    }
}
