<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flip lapsed subscriptions to 'expired' (access is also gated lazily by middleware).
Schedule::command('subscriptions:expire')->dailyAt('00:10');

// Safety net: finalize paid-but-unnotified KHQR rows, expire stale pending QRs.
Schedule::command('khqr:reconcile')->everyFiveMinutes();
