<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\Serve;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        // Override the framework serve command defaults.
        Serve::class,
    ];

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // keep default behaviour: load commands if any exist in Console/Commands
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
