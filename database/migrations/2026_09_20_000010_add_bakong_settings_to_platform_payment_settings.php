<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move the Bakong operating config out of .env and into the settings page.
 *
 * The payout identity already lived here; the switch, the integrator identity
 * and the quota guards did not, so changing any of them meant SSH, an editor,
 * and `config:cache` — which is how the daily limit stays at whatever it was
 * first set to, and how a wrong integrator email survives ninety days.
 *
 * EVERY COLUMN IS NULLABLE, AND NULL IS NOT A VALUE — it means "not set here,
 * read .env". That is what keeps a fresh install, a CI run and this very
 * deployment working before anyone opens the page, and it is the same
 * backward-compatibility seam BakongPlatformIdentity already uses for a blank
 * account id. A boolean therefore has THREE states here, which is the point:
 * true, false, and "I have not expressed an opinion".
 *
 * Note what is NOT here: base_url, the demo switches, and the HTTP timeouts.
 * base_url is the host this app POSTs a bearer token to — putting it in a web
 * form means a compromised superadmin session can redirect the NBC credential
 * to a server of its choosing, which is credential exfiltration wearing a
 * settings page. It is also a value that never changes. It stays in .env, where
 * changing it requires shell access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->boolean('bakong_enabled')->nullable()->after('bakong_account_id');

            // Registration identity. The email is also renew_token's entire
            // payload, which is why it needs to be visible and correctable by
            // the person who read the verification code out of the inbox.
            $table->string('bakong_email')->nullable()->after('bakong_enabled');
            $table->string('bakong_organization')->nullable()->after('bakong_email');
            $table->string('bakong_project')->nullable()->after('bakong_organization');

            // Quota guards. Tuning these is an operations decision made while
            // watching the meter on this same page.
            $table->unsignedSmallInteger('bakong_daily_request_limit')->nullable()->after('bakong_project');
            $table->unsignedSmallInteger('bakong_verify_cooldown')->nullable()->after('bakong_daily_request_limit');
            $table->unsignedSmallInteger('bakong_qr_ttl')->nullable()->after('bakong_verify_cooldown');
            $table->unsignedSmallInteger('bakong_max_verify_attempts')->nullable()->after('bakong_qr_ttl');
            $table->boolean('bakong_reconcile_enabled')->nullable()->after('bakong_max_verify_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('platform_payment_settings', function (Blueprint $table) {
            $table->dropColumn([
                'bakong_enabled',
                'bakong_email',
                'bakong_organization',
                'bakong_project',
                'bakong_daily_request_limit',
                'bakong_verify_cooldown',
                'bakong_qr_ttl',
                'bakong_max_verify_attempts',
                'bakong_reconcile_enabled',
            ]);
        });
    }
};
