<?php

/*
|--------------------------------------------------------------------------
| Bakong Open API (National Bank of Cambodia)
|--------------------------------------------------------------------------
|
| Direct integration with the official Bakong Open API, replacing the KHQRPay
| (khqr.cc) middleman. Specification: "Bakong OpenAPI Documentation" v1.0.2,
| May 2021, NBC. Nothing here assumes an endpoint, parameter or capability the
| published document does not specify.
|
| TWO FACTS drive every default below, and both are unforgiving:
|
|  1. Bakong meters the upstream token per CALENDAR DAY (~100 requests on this
|     account) and a REFUSED request is charged exactly like a successful one.
|     So an app that keeps asking a spent token spends the rest of the day
|     discovering it is spent.
|
|  2. The Open API has NO WEBHOOK. All eight documented endpoints are outbound
|     request/response. Under KHQRPay a signed callback was the primary
|     settlement path and polling was only the safety net; here polling is the
|     ONLY path. Every poll is metered, so the polling budget is a design
|     constraint rather than a tuning knob.
|
| Defaults are therefore chosen so an untouched install makes ZERO requests,
| and a configured one spends roughly 8 calls on a checkout rather than 20.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | The single answer to "does this installation talk to Bakong at all?", and
    | the first thing BakongProviderClient refuses on — before the scheduler, a
    | command, a page load, a status poll, a queue job or a diagnostic report.
    |
    | The default is OFF, deliberately and asymmetrically. Shipping it off on an
    | install that wants Bakong costs one line of .env; shipping it on costs a
    | metered token drained by something nobody remembered was running. An
    | ABSENT variable must therefore mean disabled.
    |
    | Cash, bank transfer and the landlord's manual static-KHQR channel are
    | unaffected by this switch: the manual channel never contacts Bakong, which
    | is exactly why tenant rent defaults to it (see docs/BAKONG_MIGRATION.md).
    |
    */
    'enabled' => (bool) env('BAKONG_API_ENABLED', false),

    /*
    | The API root, supplied by NBC during integrator onboarding. The published
    | document writes it as {{baseUrl}} and never states it, so it is NOT
    | guessed anywhere in this codebase.
    |
    | An empty value is a SECOND off switch: with nowhere to send a request,
    | BakongProviderClient refuses rather than constructing a URL against an
    | empty host. That matters because a half-configured .env is the ordinary
    | state of a machine in the middle of being set up.
    */
    'base_url' => rtrim((string) env('BAKONG_API_BASE_URL', ''), '/'),

    /*
    | Local simulation: build a real KHQR payload, render it, and settle it on a
    | timer without contacting anyone. Lets the whole flow be demonstrated and
    | developed with no allowance spent and no credentials present.
    |
    | Hard-disabled in production so it can never auto-confirm real money, and
    | treated as "feature on, transmission forbidden" by the client — the same
    | split the KHQRPay integration uses.
    */
    'demo' => (bool) env('BAKONG_DEMO', false) && env('APP_ENV') !== 'production',

    /*
    | Seconds a demo payment waits before settling itself. The delay is the
    | point: it exercises the spinner, the poll loop and the "check now" button
    | rather than jumping straight to a confirmed page, so what gets rehearsed
    | is the customer's actual experience.
    */
    'demo_settle_after' => (int) env('BAKONG_DEMO_SETTLE_AFTER', 15),

    /*
    |--------------------------------------------------------------------------
    | Integrator identity
    |--------------------------------------------------------------------------
    |
    | Bakong issues an access token to an INTEGRATOR — an (email, organization,
    | project) triple — and not to a merchant. This is the whole reason tenant
    | rent stays on the manual channel: there is no per-landlord credential to
    | issue, so one shared token would put every landlord's rent collection
    | under a single ~100/day ceiling.
    |
    | request_token emails a 20-character code to this address; bakong:token
    | verify exchanges it for a JWT. renew_token takes this address alone.
    |
    | THE TOKEN ITSELF IS NOT AN ENVIRONMENT VARIABLE. It is issued at runtime
    | and stored encrypted in bakong_tokens, so that renewal does not require a
    | deployment and a rotated token is never sitting in a shell history, a
    | config cache or a .env backup.
    |
    */
    'integrator' => [
        'email' => env('BAKONG_EMAIL'),
        'organization' => env('BAKONG_ORGANIZATION'),
        'project' => env('BAKONG_PROJECT'),
    ],

    /*
    | Renew the token this many days before its JWT `exp`. The sample tokens in
    | the NBC document carry an exp roughly 93 days after iat, and expiry is
    | read out of the JWT locally — asking the API when a token expires would
    | spend the allowance to learn something the token already states.
    */
    'token_renew_days' => (int) env('BAKONG_TOKEN_RENEW_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Platform KHQR identity (subscription payments)
    |--------------------------------------------------------------------------
    |
    | Where subscription money lands, and the name/city printed inside the QR.
    | The account id in the payload is what decides where funds settle — not the
    | token used to ask about the transaction afterwards.
    |
    | Landlord rent QRs do NOT read these: they carry that landlord's own
    | merchant_payment_settings.bakong_account_id.
    |
    */
    'account_id' => env('BAKONG_ACCOUNT_ID'),
    'merchant_name' => env('BAKONG_MERCHANT_NAME', env('APP_NAME', 'AMS')),
    'merchant_city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),
    'currency' => env('BAKONG_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Quota protection
    |--------------------------------------------------------------------------
    |
    | Five independent bounds. They are the only things standing between a
    | forgotten poller and a day with no allowance left for a real payment, so
    | each one is here because the others do not cover its case.
    |
    */

    /*
    | HARD CEILING on live requests per calendar day, reserved atomically under
    | a lock and FAILING CLOSED when the ledger cannot be read.
    |
    | Deliberately BELOW Bakong's own limit rather than equal to it: the point of
    | an internal ceiling is to stop short of the external one, so that hitting
    | it is a local event we can see and recover from instead of an upstream
    | refusal affecting every other integration on the token. 80 against ~100
    | leaves a reserve for whatever is not counted here.
    |
    | 0 disables the ceiling — the backward-compatibility seam, and not a
    | setting any deployment taking real payments should use.
    */
    'daily_request_limit' => (int) env('BAKONG_DAILY_REQUEST_LIMIT', 80),

    /*
    | Minimum seconds between live requests about the SAME transaction, claimed
    | atomically BEFORE the request so it holds across tabs, users, PHP-FPM
    | workers, queue workers and the reconcile run alike.
    |
    | MUST STAY WELL ABOVE THE BROWSER POLL INTERVAL (10s in the checkout views)
    | or it absorbs nothing — a cooldown equal to the poll interval lets every
    | poll through, which is how the KHQRPay integration learned this.
    */
    'verify_cooldown' => (int) env('BAKONG_VERIFY_COOLDOWN', 60),

    /*
    | Minutes a minted QR stays payable. This also caps how long ONE abandoned
    | tab can poll: past expiry the row is terminal and verification
    | short-circuits locally, for free.
    |
    | 6 minutes with a 60s cooldown is ~6 calls; that product is the point.
    */
    'qr_ttl' => (int) env('BAKONG_QR_TTL', 6),

    /*
    | The most a SINGLE checkout session may ever cost, across the browser
    | poller, the "check now" button and the reconcile net combined.
    |
    | The cooldown caps the RATE and qr_ttl caps the WINDOW; this caps their
    | PRODUCT, which is the number that actually matters when something goes
    | wrong in a way neither of the other two anticipated. 0 disables it.
    */
    'max_verify_attempts' => (int) env('BAKONG_MAX_VERIFY_ATTEMPTS', 8),

    /*
    | Minutes to stop calling after a refusal that will be just as true on the
    | next request: 401/403 (token), 429 (allowance), 5xx (Bakong unwell), or an
    | errorCode naming a credential problem.
    |
    | NOT tripped by a timeout — a timeout says nothing about the token, and the
    | cooldown already prevents an immediate retry.
    */
    'failure_backoff' => (int) env('BAKONG_FAILURE_BACKOFF', 15),

    /*
    | Minutes to stop calling after Bakong itself answers 429. Independent of
    | the daily ceiling above because it reacts to what the provider actually
    | sent, so it still protects an installation that set no ceiling at all.
    */
    'rate_limit_backoff' => (int) env('BAKONG_RATE_LIMIT_BACKOFF', 5),

    /*
    |--------------------------------------------------------------------------
    | Reconciliation safety net
    |--------------------------------------------------------------------------
    |
    | Under KHQRPay this net existed to catch payments whose WEBHOOK failed.
    | Bakong sends no webhook, so there is no delivery failure for it to rescue
    | — a payment either gets confirmed by a poll or it does not. That makes the
    | net far less valuable here and exactly as expensive, so it ships OFF and
    | is applied as a scheduler skip() rather than a commented-out line.
    |
    | Turn it on only for a deployment where payers routinely close the tab
    | before confirmation, and watch bakong:usage afterwards.
    |
    */
    'reconcile_enabled' => (bool) env('BAKONG_RECONCILE_ENABLED', false),

    /*
    | Minutes past a QR's expiry the net keeps asking. This is the quota bound
    | on the net: "leave it open and ask again" has no exit when the gateway
    | never answers, so the bound must come from how long the asking lasts.
    */
    'reconcile_grace' => (int) env('BAKONG_RECONCILE_GRACE', 30),

    /*
    |--------------------------------------------------------------------------
    | Deeplink (open-in-Bakong-app)
    |--------------------------------------------------------------------------
    |
    | generate_deeplink_by_qr turns a KHQR string into a short link that opens
    | the payer's Bakong app directly — a capability KHQRPay never offered, and
    | a real improvement on a phone where scanning your own screen is awkward.
    |
    | It is OFF by default because it costs a metered request per checkout and
    | the QR alone is already payable. Note the published parameter table lists
    | no Authorization header for this endpoint; confirm with NBC before relying
    | on it either way.
    |
    */
    'deeplink' => [
        'enabled' => (bool) env('BAKONG_DEEPLINK_ENABLED', false),
        'app_icon_url' => env('BAKONG_DEEPLINK_ICON_URL'),
        'app_name' => env('BAKONG_DEEPLINK_APP_NAME', env('APP_NAME', 'AMS')),
        'callback' => env('BAKONG_DEEPLINK_CALLBACK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP timeouts
    |--------------------------------------------------------------------------
    |
    | A request must never hang indefinitely: a checkout page is waiting on it,
    | and a worker blocked on a socket is a worker not serving anyone. Kept
    | short — a slow answer about a payment is worth no more than a fast one,
    | and the cooldown will allow another attempt shortly.
    |
    | There is deliberately NO automatic retry anywhere in this integration. The
    | daily ceiling counts upstream requests, so a retry spends two against one
    | reservation, and a request that times out has still been charged.
    |
    */
    'connect_timeout' => (int) env('BAKONG_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('BAKONG_TIMEOUT', 8),

];
