<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prelaunch:email-outbox:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('prelaunch:media:cleanup-orphans --force --older-than=24')->daily()->withoutOverlapping();
Schedule::command('prelaunch:media:capacity --json')->everyFiveMinutes()->withoutOverlapping();
