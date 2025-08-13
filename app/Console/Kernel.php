<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Nightly stock mutation report generation at 23:30
        $schedule->command('reports:generate-stock-mutation')->dailyAt('23:30');
        // Nightly stock movement per-product report generation at 23:45
        $schedule->command('reports:generate-stock-movement')->dailyAt('23:45');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
