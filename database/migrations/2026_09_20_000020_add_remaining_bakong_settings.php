<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the Bakong operating config, so "it is configured in Payment
 * Settings" is true without an asterisk.
 *
 * Nullable for the same reason as the first batch: null is "not set here, read
 * .env", which is what keeps an existing row — opinionated about none of this —
 * from silently zeroing a backoff or a renewal window on deploy.
 *
 * What stays in .env, and why it is not an oversight:
 *
 *   • base_url — the host this app POSTs a bearer token to. A form that can
 *     repoint it turns a borrowed superadmin session into credential theft.
 *   • demo / demo_settle_after — a "pretend the payment succeeded" switch. It
 *     is already hard-disabled in production; putting it on a production screen
 *     would be handing someone the ability to mark subscriptions paid.
 *   • connect_timeout / timeout — HTTP plumbing, not an operator's decision.
 *   • deeplink.* — an unbuilt feature, and two of its four values are URLs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            // Days before expiry that the scheduler renews the token. Renewal
            // spends one request, so this is a quota decision as much as a
            // lifecycle one.
            $table->unsignedSmallInteger('bakong_token_renew_days')->nullable()->after('bakong_reconcile_enabled');

            // NBC's own ceiling. DISPLAY ONLY — it is what the meter compares
            // our ceiling against. Editable because if NBC changes the figure,
            // a hard-coded 100 makes every reading on the page quietly wrong.
            $table->unsignedSmallInteger('bakong_upstream_daily_limit')->nullable()->after('bakong_token_renew_days');

            // Minutes of silence after a failure / after a rate-limit refusal.
            // Both exist so a broken upstream costs a handful of requests a day
            // rather than the whole allowance.
            $table->unsignedSmallInteger('bakong_failure_backoff')->nullable()->after('bakong_upstream_daily_limit');
            $table->unsignedSmallInteger('bakong_rate_limit_backoff')->nullable()->after('bakong_failure_backoff');

            // How long the reconcile sweep leaves a payment alone before
            // asking about it.
            $table->unsignedSmallInteger('bakong_reconcile_grace')->nullable()->after('bakong_rate_limit_backoff');
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn([
                'bakong_token_renew_days',
                'bakong_upstream_daily_limit',
                'bakong_failure_backoff',
                'bakong_rate_limit_backoff',
                'bakong_reconcile_grace',
            ]);
        });
    }
};
