<?php

namespace App\Providers;

use App\Shared\Application\Bus\InProcessCommandBus;
use App\Shared\Application\Bus\InProcessQueryBus;
use App\Shared\Application\Contracts\CommandBus;
use App\Shared\Application\Contracts\QueryBus;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Context\NullAuthenticatedContextProvider;
use App\Shared\Infrastructure\Idempotency\DatabaseIdempotencyStore;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\DatabaseOutboxStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Time\Clock;
use App\Shared\Time\SystemClock;
use App\Shared\Topology\ModuleRegistry;
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
        $this->app->singleton(AuthenticatedContextProvider::class, NullAuthenticatedContextProvider::class);
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
        //
    }
}
