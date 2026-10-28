<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('coinbox:sweep')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
