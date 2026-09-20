<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Step 3 (specs/08): prod runs `schedule:run` every minute via cron.
Schedule::command('reminders:dispatch')->everyMinute();
// Step 9 (specs/15): weekly management packs, Monday 08:00 Asia/Manila + mock notify.
Schedule::command('reports:generate --type=weekly --notify')->weeklyOn(1, '8:00')->timezone('Asia/Manila');
