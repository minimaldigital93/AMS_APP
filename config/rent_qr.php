<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant rent KHQR — the LOCAL channel
    |--------------------------------------------------------------------------
    |
    | What is left of the rent-payment QR after khqr.cc was retired, and the
    | shape of it is the point: there is no gateway here at all.
    |
    | A rent QR is built on this server from the landlord's own Bakong account
    | id, shown to the tenant, and confirmed BY THE LANDLORD after they see the
    | money in their banking app. Nothing is signed, nothing is posted, nothing
    | is polled — so this channel costs zero metered requests and cannot drain
    | anybody's allowance, which is precisely why it survived the migration
    | while the API channel did not.
    |
    | It is also NOT the direct Bakong Open API integration. That token belongs
    | to the platform operator and pays for SUBSCRIPTIONS (see config/bakong.php);
    | rent money settles straight into the landlord's own bank and never passes
    | through the platform's credentials. Wiring rent through the operator's
    | token would put every landlord's tenants on one ~100/day allowance.
    |
    */

    // Minutes a rent QR stays payable before the checkout modal gives up on it.
    // Generous compared with the subscription QR (6 minutes) because nothing is
    // being held open at a provider — the window only bounds how long the
    // landlord's screen keeps offering the same transaction id.
    'ttl' => (int) env('RENT_QR_TTL', 30),

    // Fallback when a landlord has saved no currency of their own.
    'currency' => env('RENT_QR_CURRENCY', 'USD'),

    // Build an example QR for an account that has configured no Bakong id yet,
    // so the checkout flow is demonstrable on a dev machine. Hard-disabled in
    // production: an example QR collects nothing, and a tenant shown one would
    // be told to pay into a demonstration.
    'demo' => (bool) env('RENT_QR_DEMO', false) && env('APP_ENV') !== 'production',

];
