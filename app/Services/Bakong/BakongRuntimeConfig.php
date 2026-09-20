<?php

namespace App\Services\Bakong;

use App\Models\PlatformPaymentSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Let the settings page outrank .env for the Bakong operating config.
 *
 * WHY THIS OVERWRITES config() INSTEAD OF BEING READ DIRECTLY
 *
 * There are 52 `config('bakong.…')` read sites across services, commands, the
 * quota ledger and the views. Rewriting them all to call a resolver would be 52
 * chances to miss one — and the one that got missed would be a gate reading a
 * stale limit while the page showed the new one, which is the exact class of
 * "two places disagree" bug this integration has already been bitten by twice.
 * So the values are pushed INTO config once, at boot, and every existing reader
 * keeps working unchanged and cannot drift.
 *
 * NULL MEANS "NOT SET HERE", NOT "FALSE" OR "ZERO"
 *
 * A null column falls through to .env rather than overriding it with emptiness.
 * That is what lets this deploy onto a live installation whose settings row
 * predates these fields, and what keeps CI and a fresh install working before
 * anyone opens the page. It also means a boolean has three states — true, false
 * and "no opinion" — which is why bakong_enabled is a nullable boolean and not
 * a flag defaulting to false. A `false` default would silently switch payments
 * OFF for every existing installation the moment this migration ran.
 *
 * WHAT IS DELIBERATELY NOT HERE
 *
 *   • base_url — the host this app POSTs a bearer token to. A settings form
 *     that can repoint it is a way to harvest the NBC credential with nothing
 *     more than a borrowed superadmin session. It never changes; it stays in
 *     .env, where changing it costs shell access.
 *   • the demo switches — a "pretend the payment succeeded" toggle does not
 *     belong on a production screen at any privilege level.
 *   • connect_timeout / timeout — HTTP plumbing, not an operator's decision.
 *
 * FAILURE IS SILENT ON PURPOSE. This runs in boot(), before the request has a
 * chance to be anything. A missing table (fresh install, mid-migration) or an
 * unreachable database must leave .env in force rather than take the app down —
 * the same reasoning as the View composers' try/catch in AppServiceProvider.
 */
final class BakongRuntimeConfig
{
    /** Column on platform_payment_settings => dotted config key it overrides. */
    private const MAP = [
        'bakong_enabled' => 'bakong.enabled',
        'bakong_email' => 'bakong.integrator.email',
        'bakong_organization' => 'bakong.integrator.organization',
        'bakong_project' => 'bakong.integrator.project',
        'bakong_daily_request_limit' => 'bakong.daily_request_limit',
        'bakong_verify_cooldown' => 'bakong.verify_cooldown',
        'bakong_qr_ttl' => 'bakong.qr_ttl',
        'bakong_max_verify_attempts' => 'bakong.max_verify_attempts',
        'bakong_reconcile_enabled' => 'bakong.reconcile_enabled',
    ];

    /**
     * What .env said, captured before anything overrode it.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $envDefaults = null;

    /** Push any saved settings over the .env-derived config. */
    public static function apply(): void
    {
        // Captured FIRST and exactly once. After apply() runs, config() no
        // longer knows what .env said — and the settings form needs to, so it
        // can tell the operator what clearing a field falls back to instead of
        // leaving them to guess whether a server variable still holds a value.
        self::$envDefaults ??= self::readCurrent();

        foreach (self::overrides() as $key => $value) {
            config([$key => $value]);
        }
    }

    /**
     * The .env-derived value behind each overridable key.
     *
     * @return array<string, mixed>
     */
    public static function envDefaults(): array
    {
        // Null when apply() has not run — a unit test touching this in
        // isolation — in which case the live config IS still the .env one.
        return self::$envDefaults ?? self::readCurrent();
    }

    /** Test seam: forget the captured defaults so a later apply() re-reads. */
    public static function forgetEnvDefaults(): void
    {
        self::$envDefaults = null;
    }

    /** @return array<string, mixed> */
    private static function readCurrent(): array
    {
        $out = [];

        foreach (self::MAP as $key) {
            $out[$key] = config($key);
        }

        return $out;
    }

    /**
     * The values the saved row expresses an opinion about.
     *
     * @return array<string, mixed>
     */
    public static function overrides(): array
    {
        try {
            if (! Schema::hasTable('platform_payment_settings')) {
                return [];
            }

            $row = PlatformPaymentSetting::current();
        } catch (\Throwable) {
            // No database yet, or no table. .env stays in force.
            return [];
        }

        if ($row === null) {
            return [];
        }

        $out = [];

        foreach (self::MAP as $column => $key) {
            $value = $row->{$column};

            // Null — and only null — means "not set here". An empty string is
            // treated the same way, because a cleared text field must fall back
            // rather than configure the integrator as nobody.
            if ($value === null || $value === '') {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
