<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| PB-20: every minute, settle campaigns whose funding deadline has passed (funded, or every
| pledge refunded). `composer dev` runs the scheduler (schedule:work) alongside the server, so
| this happens automatically locally; in production a cron entry runs `schedule:run` each minute.
| withoutOverlapping() skips a run while the previous one is still going (the settlement itself
| is safe either way: it locks each campaign).
*/
Schedule::command('campaigns:settle-deadlines')->everyMinute()->withoutOverlapping();
