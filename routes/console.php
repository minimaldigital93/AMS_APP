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
// skip() rather than a commented-out line: when the khqr.cc profile has no
// active Bakong token the net cannot confirm anything, so every run is quota
// spent on a question the gateway will not answer — and a commented schedule is
// how that gets left off permanently. KHQRPAY_RECONCILE_ENABLED=false is the
// off switch; `php artisan schedule:list` still shows the entry.
Schedule::command('khqr:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->skip(fn () => ! config('services.khqrpay.reconcile_enabled'));
