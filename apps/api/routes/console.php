<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:reconcile')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('kitchen:release-preorders')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('loyalty:birthdays')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('insights:daily-report')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('analytics:rollup')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('billing:renewals')->dailyAt('09:00')->timezone('Asia/Tehran')->withoutOverlapping()->onOneServer();
Schedule::command('marketplace:refresh')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('backup:run')->dailyAt('03:30')->timezone('Asia/Tehran')->withoutOverlapping()->onOneServer();
Schedule::command('sms:campaigns')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('sms:prune')->dailyAt('04:10')->timezone('Asia/Tehran')->withoutOverlapping()->onOneServer();
