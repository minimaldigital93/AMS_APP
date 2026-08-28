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
// withoutOverlapping() because every open row in the run costs one live Bakong
// request against a token metered per day: if the gateway is slow a run can
// outlast its five-minute slot, and stacked runs would re-verify the same rows
// concurrently, multiplying quota spend exactly when the gateway is least able
// to answer. The lock is released after ten minutes so a killed run cannot
// wedge the safety net shut.
Schedule::command('khqr:reconcile')->everyFiveMinutes()->withoutOverlapping(10);
