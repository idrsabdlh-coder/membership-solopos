<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire-pending')->hourly();
Schedule::command('subscriptions:transition')->everyMinute();
Schedule::command('subscriptions:send-notifications')
    ->dailyAt('09:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
Schedule::command('app:cleanup-expired-epaper-downloads')->hourly();
