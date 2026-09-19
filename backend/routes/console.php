<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Step 3 (specs/08): prod runs `schedule:run` every minute via cron.
Schedule::command('reminders:dispatch')->everyMinute();
