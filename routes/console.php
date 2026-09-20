<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flip lapsed subscriptions to 'expired' (access is also gated lazily by middleware).
Schedule::command('subscriptions:expire')->dailyAt('00:10');

// NOTE: `khqr:reconcile` used to run here every five minutes. It went with
// khqr.cc in 2026-09 — there is no gateway left to ask, and tenant rent is
// confirmed by the landlord rather than found by a sweep. Its Bakong
// replacement is scheduled below and is deliberately a smaller thing.

// Keep the Bakong access token alive.
//
// This is the ONLY automatic path to a Bakong token request, and it answers
// "nothing to do" on almost every run: renewIfDue() reads the expiry out of the
// JWT locally and only calls out inside the configured window, so a token
// lasting ~93 days costs about four requests a YEAR rather than one a day.
// Asking the API when a token expires would pay a metered request for
// information the token already states.
//
// Daily rather than hourly for the same reason — there is nothing a token
// checked 24 times a day can catch that one check cannot, and the renewal
// window is measured in days.
//
// skip() on the master switch, like every other scheduled Bakong entry: a
// scheduled command must never be trusted to gate itself, and `schedule:list`
// still shows the entry so it is visible rather than commented out.
Schedule::command('bakong:token renew --if-due')
    ->dailyAt('03:20')
    ->withoutOverlapping(10)
    ->skip(fn () => ! \App\Services\Bakong\BakongProviderClient::featureEnabled());

// Bakong safety net: confirm payments that landed after the payer closed the
// page. TWO skips, answering different questions — the master switch says
// whether this installation uses Bakong at all, the reconcile switch whether
// the net specifically is wanted while it is.
//
// It ships OFF, and that is a deliberate downgrade from the KHQRPay version.
// There the net rescued payments whose WEBHOOK failed to arrive. Bakong sends no
// webhook at all, so there is no delivery to fail: a payment is confirmed by a
// poll or it is not. That makes the net far less valuable here and exactly as
// expensive. Switch it on only where payers routinely close the tab before
// confirmation, then watch `bakong:usage`.
//
// Every fifteen minutes, not every five: with a 6-minute QR and a 60-second
// cooldown there is nothing a five-minute sweep can catch that this cannot, and
// three times the runs is three times the spend on a metered token.
Schedule::command('bakong:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->skip(fn () => ! \App\Services\Bakong\BakongProviderClient::featureEnabled())
    ->skip(fn () => ! config('bakong.reconcile_enabled'));
