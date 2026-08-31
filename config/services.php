<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | KHQRPay (khqr.cc) — dynamic KHQR generation + Bakong payment verification.
    | profile_id, secret, and bakong_id come from the merchant dashboard.
    */
    'khqrpay' => [
        'base_url' => env('KHQRPAY_BASE_URL', 'https://khqr.cc'),
        'profile_id' => env('KHQRPAY_PROFILE_ID'),
        'secret' => env('KHQRPAY_SECRET'),
        'bakong_id' => env('KHQRPAY_BAKONG_ID'),
        'merchant_name' => env('KHQRPAY_MERCHANT_NAME', env('APP_NAME', 'AMS')),
        'currency' => env('KHQRPAY_CURRENCY', 'USD'),
        // Demo mode: build a local example KHQR instead of calling the live API,
        // and auto-confirm the payment after a few seconds so the full flow is
        // demonstrable while the real KHQRPay endpoint/signing is pending.
        // Hard-disabled in production so it can never auto-confirm real money.
        'demo' => (bool) env('KHQRPAY_DEMO', false) && env('APP_ENV') !== 'production',
        // Max age (seconds) of a webhook's req_time before it's rejected as a replay.
        'webhook_tolerance' => (int) env('KHQRPAY_WEBHOOK_TOLERANCE', 600),
        // Min seconds between live verify() calls for the same transaction — caps
        // how hard the public status poll can hammer the provider. Must stay
        // above the client poll interval (record_income.blade.php / subscribe
        // checkout.blade.php both poll every 10s) or every poll still fires a
        // live call regardless of this setting.
        'verify_cooldown' => (int) env('KHQRPAY_VERIFY_COOLDOWN', 10),
        // Minutes a minted QR stays payable before it's considered expired.
        'qr_ttl' => (int) env('KHQRPAY_QR_TTL', 30),
        // Minutes to stop making live verify() calls for ALL open transactions on
        // a credential (profile) after that credential is rate-limited/quota-
        // exceeded (HTTP 429) by the provider — one abandoned QR must not burn
        // the whole day's quota for every other open checkout on the same token.
        // Independent of daily_budget below: it reacts to a 429 the provider
        // actually sent, so it still protects an account that hasn't set a
        // budget ceiling at all.
        'rate_limit_backoff' => (int) env('KHQRPAY_RATE_LIMIT_BACKOFF', 5),
        // Preflight the HOSTED-CHECKOUT endpoint (not just the read-only
        // check-transaction one) before handing a customer's browser to
        // khqr.cc. It is the only probe that catches a profile which can answer
        // queries but cannot take money ("Bakong Token Required") — the state
        // that used to dump customers on a raw JSON page. It costs one signed
        // request against a THROWAWAY transaction id per checkout burst; turn it
        // off only if khqr.cc objects to the unused sessions it opens.
        'handoff_preflight' => (bool) env('KHQRPAY_HANDOFF_PREFLIGHT', true),
        // Hard ceiling on LIVE provider calls per settlement target per calendar
        // day. Bakong meters the upstream token daily and a refused request
        // costs the same as a successful one, so once the allowance is gone the
        // app spends the whole day making calls that can only fail — and has
        // nothing left for the payment that actually matters. Past this number
        // verify() stops calling out and answers "refused" (never "unpaid"), so
        // no row is settled or expired on a guess. 0 disables the ceiling.
        'daily_budget' => (int) env('KHQRPAY_DAILY_BUDGET', 0),
        // How long AFTER a QR has expired the khqr:reconcile safety net keeps
        // asking the gateway about it.
        //
        // The net has to outlive the QR: a payment can land in the last seconds
        // before expiry and its webhook can still fail, and then this is the
        // only thing that will ever find it. But it only has to outlive it by a
        // little. Until 2026-08 the net re-verified every open row for a FULL
        // DAY (created_at > now()-24h) — with a 10-minute QR that is 288 live
        // calls per abandoned checkout, against a token allowed ~100 a day,
        // every one of them spent on a QR nobody can pay any more. A gateway
        // that refuses (no Bakong token) can never close the row, so the loop
        // had no exit. This window is what bounds the spend to the checkout
        // attempts that actually happened.
        'reconcile_grace' => (int) env('KHQRPAY_RECONCILE_GRACE', 60),
        // Master switch for that safety net. When the profile has no usable
        // Bakong token the net cannot confirm anything — every run is quota
        // spent on a question the gateway will not answer — and there has to be
        // a way to stop it that isn't editing the schedule and forgetting. Turn
        // it back ON once the token is active, or paid-but-unnotified rows stop
        // being rescued.
        'reconcile_enabled' => (bool) env('KHQRPAY_RECONCILE_ENABLED', true),
    ],

];
