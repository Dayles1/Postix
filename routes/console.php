<?php

//routes/console.php
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('groups:check-pending')->everyMinute()->runInBackground();
Schedule::command('bans:sync')->everyMinute();

// Group members into the accounts' contacts, once a day. The time and the
// switch live in the panel (Contact sync -> Settings), so the scheduler asks
// every minute whether this one is the minute.
Schedule::command('telegram:sync-contacts --trigger=schedule')
    ->everyMinute()
    ->when(fn () => app(\App\Application\Telegram\Services\ContactSyncSettings::class)->dueNow())
    ->runInBackground()
    ->withoutOverlapping();
