<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:expire-holds')->everyMinute();
// Reminder match dimatikan dulu (opening): match baru langsung dibuka, tombol pengingat disembunyikan.
// Schedule::command('matches:notify-reminders')->everyMinute();
Schedule::command('membership:process-renewals')->dailyAt('08:00')->timezone(config('app.display_timezone'));
Schedule::command('users:purge-scheduled-deletions')->dailyAt('03:00')->timezone(config('app.display_timezone'));
