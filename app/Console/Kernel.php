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
        // Close cast payouts on the 1st (末締め)
        $schedule->command('casts:close-month')->monthlyOn(1, '03:10');
        // Disburse cast payouts daily (翌月末)
        $schedule->command('casts:process-payouts')->dailyAt('03:40');
        // Calculate and store monthly earned rankings on the 15th of every month at 03:00
        $schedule->command('rankings:monthly-earned')->monthlyOn(15, '03:00');
        // Auto-exit over budget reservations every minute
        $schedule->command('reservations:auto-exit')->everyMinute();
        // Quarterly evaluations: run on 1st of Jan/Apr/Jul/Oct at 04:00
        $schedule->command('grades:quarterly --auto-downgrade')->cron('0 4 1 1,4,7,10 *');
        // Reset quarterly points: run on 1st of Jan/Apr/Jul/Oct at 00:01 (just after midnight)
        $schedule->command('points:reset-quarterly')->cron('1 0 1 1,4,7,10 *');
        // Process exceeded pending transfers every hour
        $schedule->command('points:process-exceeded-pending')->hourly();
        // Process pending payments every hour (for 2-day delayed capture)
        $schedule->command('payments:process-pending')->hourly();
        // Process pending automatic payments every hour (for 2-day delayed capture)
        $schedule->command('payments:process-pending-automatic')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}


