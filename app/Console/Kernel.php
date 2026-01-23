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
        // Set timezone for all scheduled tasks to Asia/Tokyo
        // Note: Times are specified in Asia/Tokyo timezone
        $schedule->useTimezone('Asia/Tokyo');

        // Close cast payouts on the 1st (末締め)
        $schedule->command('casts:close-month')
            ->monthlyOn(1, '03:10')
            ->withoutOverlapping()
            ->onOneServer();

        // Disburse cast payouts daily (翌月末)
        $schedule->command('casts:process-payouts')
            ->dailyAt('03:40')
            ->withoutOverlapping()
            ->onOneServer();

        // Calculate and store monthly earned rankings on the 15th of every month at 03:00
        $schedule->command('rankings:monthly-earned')
            ->monthlyOn(15, '03:00')
            ->withoutOverlapping()
            ->onOneServer();

        // Auto-exit over budget reservations every minute
        $schedule->command('reservations:auto-exit')
            ->everyMinute()
            ->withoutOverlapping(2) // Allow 2 minutes overlap protection
            ->runInBackground(); // Run in background to avoid blocking

        // Auto-cancel reservations where cast didn't start timer (every 15 minutes)
        $schedule->command('reservations:auto-cancel-no-start')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->onOneServer();

        // Process scheduled refunds for cancelled reservations (daily at 23:59)
        $schedule->command('reservations:process-scheduled-refunds')
            ->dailyAt('23:59')
            ->withoutOverlapping()
            ->onOneServer();

        // Quarterly evaluations: run on 1st of Jan/Apr/Jul/Oct at 04:00
        $schedule->command('grades:quarterly --auto-downgrade')
            ->cron('0 4 1 1,4,7,10 *')
            ->withoutOverlapping()
            ->onOneServer();

        // Reset quarterly points: run on 1st of Jan/Apr/Jul/Oct at 00:01 (just after midnight)
        $schedule->command('points:reset-quarterly')
            ->cron('1 0 1 1,4,7,10 *')
            ->withoutOverlapping()
            ->onOneServer();

        // Process exceeded pending transfers every hour
        $schedule->command('points:process-exceeded-pending')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        // Process pending payments every hour (for 2-day delayed capture)
        $schedule->command('payments:process-pending')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        // Process pending automatic payments every hour (for 2-day delayed capture)
        $schedule->command('payments:process-pending-automatic')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}


