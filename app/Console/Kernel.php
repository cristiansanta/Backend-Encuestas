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
        // Actualizar estados de encuestas cada hora
        $schedule->command('surveys:update-states')
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // También ejecutar al iniciar la aplicación
        $schedule->command('surveys:update-states')
                 ->everyMinute()
                 ->between('00:00', '00:01')
                 ->withoutOverlapping();
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