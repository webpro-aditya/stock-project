<?php

namespace App\Console;

use App\Console\Commands\NSECommonSegment;
use App\Console\Commands\NSEMemberSegment;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        NSECommonSegment::class,
        NSEMemberSegment::class
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('run:nsemember')
            ->dailyAt('05:00')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('run:nsecommon')
            ->dailyAt('06:00')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->onOneServer();
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
