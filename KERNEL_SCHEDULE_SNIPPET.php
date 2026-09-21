<?php

// Paste this INSIDE your existing app/Console/Kernel.php,
// in the schedule(Schedule $schedule) method.
// Do not replace the whole file — just add this one line
// (plus imports if needed) to what's already there.

$schedule->command('yieldkit:fetch-daily-commissions')
    ->dailyAt('08:00')
    ->withoutOverlapping()   // skip if yesterday's run is still going
    ->onOneServer();         // safe if you run more than one app server
