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
        // how hard the public status poll can hammer the provider.
        'verify_cooldown' => (int) env('KHQRPAY_VERIFY_COOLDOWN', 4),
        // Minutes a minted QR stays payable before it's considered expired.
        'qr_ttl' => (int) env('KHQRPAY_QR_TTL', 30),
        // Preflight the HOSTED-CHECKOUT endpoint (not just the read-only
        // check-transaction one) before handing a customer's browser to
        // khqr.cc. It is the only probe that catches a profile which can answer
        // queries but cannot take money ("Bakong Token Required") — the state
        // that used to dump customers on a raw JSON page. It costs one signed
        // request against a THROWAWAY transaction id per checkout burst; turn it
        // off only if khqr.cc objects to the unused sessions it opens.
        'handoff_preflight' => (bool) env('KHQRPAY_HANDOFF_PREFLIGHT', true),
    ],

];
