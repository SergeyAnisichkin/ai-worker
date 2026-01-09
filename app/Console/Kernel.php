<?php

namespace App\Console;

use App\Console\Commands\GetNewsCommand;
use App\Console\Commands\TestRunCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        GetNewsCommand::class,
        TestRunCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule
            ->command('tt:tt')
            ->everyFifteenMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
