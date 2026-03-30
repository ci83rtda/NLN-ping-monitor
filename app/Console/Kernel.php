<?php

namespace App\Console;

use App\Console\Commands\GetDevices;
use App\Console\Commands\PingDevices;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command(GetDevices::class)->cron('0 0,6,12,18 * * *')->timezone('America/Bogota')->withoutOverlapping();
        $schedule->command(PingDevices::class)->everyfiveMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
