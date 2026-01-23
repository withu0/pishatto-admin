<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// All scheduled tasks use Asia/Tokyo timezone
// Note: Times are specified in Asia/Tokyo timezone

// Close cast payouts on the 1st (末締め)
Schedule::command('casts:close-month')
    ->monthlyOn(1, '03:10')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Disburse cast payouts daily (翌月末)
Schedule::command('casts:process-payouts')
    ->dailyAt('03:40')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Calculate and store monthly earned rankings on the 15th of every month at 03:00
Schedule::command('rankings:monthly-earned')
    ->monthlyOn(15, '03:00')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Auto-exit over budget reservations every minute
// Schedule::command('reservations:auto-exit')
//     ->everyMinute()
//     ->timezone('Asia/Tokyo')
//     ->withoutOverlapping(2) // Allow 2 minutes overlap protection
//     ->runInBackground(); // Run in background to avoid blocking

// Auto-cancel reservations where cast didn't start timer (every 15 minutes)
Schedule::command('reservations:auto-cancel-no-start')
    ->everyFifteenMinutes()
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Process scheduled refunds for cancelled reservations (daily at 23:59)
Schedule::command('reservations:process-scheduled-refunds')
    ->dailyAt('23:59')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Quarterly evaluations: run on 1st of Jan/Apr/Jul/Oct at 04:00
Schedule::command('grades:quarterly --auto-downgrade')
    ->cron('0 4 1 1,4,7,10 *')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Reset quarterly points: run on 1st of Jan/Apr/Jul/Oct at 00:01 (just after midnight)
Schedule::command('points:reset-quarterly')
    ->cron('1 0 1 1,4,7,10 *')
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Process exceeded pending transfers every hour
Schedule::command('points:process-exceeded-pending')
    ->hourly()
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Process pending payments every hour (for 2-day delayed capture)
Schedule::command('payments:process-pending')
    ->hourly()
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();

// Process pending automatic payments every hour (for 2-day delayed capture)
Schedule::command('payments:process-pending-automatic')
    ->hourly()
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping()
    ->onOneServer();
