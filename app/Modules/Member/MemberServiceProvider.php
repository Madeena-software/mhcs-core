<?php

declare(strict_types=1);

namespace App\Modules\Member;

use App\Shared\Topology\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

final class MemberServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->make(ModuleRegistry::class)->register('Member');
    }
}
