<?php

use Illuminate\Support\Facades\Schedule;

// Shared hosting (cPanel) has no Supervisor: the cron that runs schedule:run also works the queue,
// so C2B payments and webhooks are processed within about a minute.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=5')->everyMinute()->withoutOverlapping(5)->runInBackground();

Schedule::command('coinbox:sweep')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();

// Billing: invoices on the 1st for the previous month; float reminders daily.
Schedule::command('coinbox:invoices')->monthlyOn(1, '06:00')->timezone('Africa/Nairobi')->withoutOverlapping();
// Yesterday's collections and reversals, emailed at 3am Nairobi.
Schedule::command('coinbox:daily-report')->dailyAt('03:00')->timezone('Africa/Nairobi')->withoutOverlapping();
Schedule::command('coinbox:low-float')->dailyAt('09:00')->timezone('Africa/Nairobi');
