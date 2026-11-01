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
        $schedule->command('horizon:snapshot')->everyFiveMinutes();

        // The 1C helper bot: the jobs of a silent worker, a queue nobody takes,
        // and the files of old questions.
        $schedule->command('onec-helper:supervise')->everyMinute()->withoutOverlapping(5);
        $schedule->command('onec-helper:delete-old-files')->daily();
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
