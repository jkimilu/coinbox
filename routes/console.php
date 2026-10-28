<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('coinbox:sweep')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();

// Billing: invoices on the 1st for the previous month; float reminders daily.
Schedule::command('coinbox:invoices')->monthlyOn(1, '06:00')->timezone('Africa/Nairobi')->withoutOverlapping();
Schedule::command('coinbox:low-float')->dailyAt('09:00')->timezone('Africa/Nairobi');
