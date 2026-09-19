# Migrating from KHQRPay to the official Bakong Open API

Status: **in progress** on `feature/direct-bakong-api`.
Source of truth for the API: *Bakong OpenAPI Documentation, v1.0.2, May 2021*,
National Bank of Cambodia — `https://bakong.nbc.gov.kh/download/KHQR/integration/Bakong%20Open%20API%20Document.pdf`.
Nothing in this document or in the code invents an endpoint, a parameter, a
response field or a capability that the PDF does not specify.

---

## Why

Today AMS_APP reaches Bakong through a third-party middleman:

```
AMS_APP  →  KHQRPay (khqr.cc)  →  Bakong / NBC  →  customer's bank
```

The target removes the middleman:

```
AMS_APP  →  Bakong Open API  →  Bakong / NBC  →  customer's bank
```

---

## The three decisions this migration is built on

Taken 2026-09-19. They are the reason the design looks the way it does, and
reversing any of them changes the architecture, not just a config value.

### 1. Hybrid token model — the AMS token covers subscriptions only

Bakong issues an access token to an **integrator** (`email` + `organization` +
`project`), *not* to a merchant. That breaks the assumption underneath the
existing `settlement_target: platform|merchant` split, which exists precisely
because khqr.cc gave every landlord their own profile and therefore their own
allowance.

So:

- **Flow A — SaaS subscriptions** (money to the platform): verified with the
  AMS integrator token. Low volume, high value, one credential. This is the
  flow that migrates.
- **Flow B — tenant rent** (money to each landlord): defaults to the **manual
  channel**, which already exists — the KHQR is built locally from that
  landlord's own `bakong_account_id`, the tenant scans and pays, the landlord
  confirms in their banking app. **This costs zero Bakong requests.**
- A landlord who registers their *own* Bakong integrator token may opt in to
  automatic verification later. Until they do, nothing about their rent
  collection touches the allowance.

The money still settles to the landlord either way: the account id in the QR
payload is what decides where funds land, not the token that asks about it.

### 2. There is no webhook — polling is the only settlement signal

The Open API has eight endpoints and every one of them is an outbound
request/response. There is **no callback, no webhook, no push**. This is the
single most consequential difference from KHQRPay, where a signed callback to
`POST /khqr/callback` was the *primary* settlement path and polling was only the
safety net.

After migration, polling is the only path — against a token Bakong meters per
calendar day, where a refused request costs exactly as much as a successful one.

### 3. Conservative verification budget

Roughly **8 live calls maximum per checkout**, so a ~100/day allowance supports
a working day's subscriptions rather than a dozen abandoned tabs:

| Setting | Value | Why |
|---|---|---|
| `BAKONG_VERIFY_COOLDOWN` | 60s | must stay well above the 10s browser poll |
| `BAKONG_QR_TTL` | 6 min | caps how long one abandoned tab can poll |
| `BAKONG_MAX_VERIFY_ATTEMPTS` | 8 | caps the *product* of rate × window |
| `BAKONG_DAILY_REQUEST_LIMIT` | 80 | safety margin under Bakong's ~100 |
| `BAKONG_RECONCILE_ENABLED` | false | no webhook to back up, so nothing to reconcile *against* |

Plus an explicit **"I've paid — check now"** button, so a payer who has actually
paid spends a call at the moment it can succeed, instead of the page spending
calls on their behalf while they are still opening their banking app.

---

## The official API, as specified

Base URL is `{{baseUrl}}` in the document — NBC publishes it during onboarding.
It is **not** guessed anywhere in this codebase; it comes from
`BAKONG_API_BASE_URL` and an empty value disables the integration.

| # | Name | Method | Path | Auth |
|---|---|---|---|---|
| 1 | Request token | POST | `/v1/request_token` | none |
| 2 | Verify (email code) | POST | `/v1/verify` | none |
| 3 | Renew token | POST | `/v1/renew_token` | none |
| 4 | Generate deeplink by QR | POST | `/v1/generate_deeplink_by_qr` | not listed in the doc's parameter table |
| 5 | Check transaction by md5 | POST | `/v1/check_transaction_by_md5` | `Bearer <token>` |
| 6 | Check transaction by full hash | POST | `/v1/check_transaction_by_hash` | `Bearer <token>` |
| 7 | Check transaction by short hash | POST | `/v1/check_transaction_by_short_hash` | `Bearer <token>` |
| 8 | Check Bakong account | POST | `/v1/check_bakong_account` | `Bearer <token>` |

**Token lifecycle.** `request_token` takes `{email, organization, project}` and
emails a 20-character code. `verify` exchanges `{code}` for a JWT.
`renew_token` takes only `{email}` and returns a fresh JWT. The sample tokens in
the document carry `exp` roughly **93 days** after `iat`, so expiry is read out
of the JWT locally and never asked for over the wire.

**Envelope.** Every response is
`{data, errorCode, responseCode, responseMessage}`. `responseCode` is `0` for
success and `1` for failure; `errorCode` narrows it:

| errorCode | Meaning |
|---|---|
| 1 | Transaction could not be found |
| 2 | Static QR code not supported |
| 3 | Transaction failed |
| 4 | Error requesting deeplink from provider |
| 5 | Missing required fields |
| 6 | Unauthorized |
| 7 | Email server down |
| 8 | Email already registered |
| 9 | Cannot connect to server |
| 10 | Not registered yet |
| 11 | Account ID not found |

HTTP codes used: 200, 400, 401, 403, 404, **429 (too many requests)**, 500.

**Transaction lookup.** `check_transaction_by_md5` takes the md5 of the KHQR
string you generated, and returns `{hash, fromAccountId, toAccountId, currency,
amount, description}`. That means the payload AMS renders is the verification
key — it must be byte-stable between rendering and checking.

---

## What Bakong does NOT provide

Not gaps to be worked around silently — each one is replaced by explicit AMS
logic, and each is called out here so nobody later assumes it exists.

| Missing | What KHQRPay did | What AMS does instead |
|---|---|---|
| **Webhook / callback** | signed POST to `/khqr/callback` | polling only, under a conservative budget (decision 3) |
| **Hosted checkout page** | browser redirected to khqr.cc to pay | on-site QR page; no `redirect()->away()` |
| **QR image generation** | returned a hosted PNG URL | built locally (`BakongQrService`) and rendered locally |
| **Per-merchant credentials** | one profile per landlord | hybrid token model (decision 1) |
| **Refunds** | (not used) | out of scope; unchanged |

---

## Architecture

> **ONE APPLICATION LAYER MAY MAKE EXTERNAL BAKONG REQUESTS.**
> Controllers, jobs, commands, models, Blade views and scheduled tasks must
> never call Bakong directly. Every request is a closure handed to
> `BakongProviderClient::call()`, so the guards cannot be forgotten by the next
> person to add a call site — because the guards are not at the call site.

```
                          AMS_APP
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
    Payment UI          Scheduler            Artisan
   (checkout views)    (reconcile)      (bakong:token/usage/diagnose)
         └───────────────────┼───────────────────┘
                             ↓
                   BakongTransactionService     business rules,
                   BakongQrService              three-valued verify
                   BakongTokenService
                             ↓
          ┌──────────────────────────────────────────┐
          │           BakongProviderClient           │  ← the ONLY Http:: to NBC
          │  gates, in order, all refusals BEFORE    │
          │  the request is made                     │
          └──────────────────────────────────────────┘
                             ↓
                  Official Bakong Open API
                             ↓
                       Bakong / NBC
                             ↓
                     customer's bank
```

### Why this generalizes the existing guard layer rather than replacing it

`KhqrProviderClient` already implements — and has already paid for in production
incidents — exactly the protection Bakong needs: ordered pre-request gates, an
atomic per-transaction cooldown, a fail-closed daily budget reserved under lock,
per-credential failure backoff, a per-session attempt cap, and per-reason
accounting. Building a second, naive stack beside it would mean two ceilings and
two cooldowns guarding one metered token.

The concurrency-critical primitives are therefore extracted into
`App\Services\Payment\ProviderQuotaLedger` and shared. `KhqrProviderClient` is
deliberately **left untouched** during the migration: it is pinned by a
fork-based concurrency suite, it is being retired wholesale in Phase 21, and
refactoring a live payment guard on the way out is a risk with no payoff.

---

## Database changes

Additive and backward compatible. **No renames, no drops, no data migration, no
existing payment row altered.**

`khqr_payments.provider` already exists and already defaults to `'khqrpay'`, and
`PaymentManager` already resolves a driver per row — that column is the
migration seam and it was built before this migration started. New rows are
written as `'bakong'`; every existing row keeps answering through
`KhqrPayGateway` forever.

| Change | Purpose |
|---|---|
| `khqr_payments.qr_payload` (text, null) | the exact EMV string rendered; without it the md5 cannot be recomputed |
| `khqr_payments.qr_md5` (char 32, null, indexed) | the verification key for `check_transaction_by_md5` |
| `khqr_payments.provider_hash` (string, null) | Bakong's returned transaction hash, for audit and full-hash lookup |
| new `bakong_tokens` | integrator token, `encrypted` cast, with `expires_at` decoded from the JWT |
| new `bakong_api_calls` | durable per-call request accounting (see below) |

### Why request accounting needs a table

Today's accounting lives entirely in cache counters
(`KhqrProviderClient::callsOn()` reads `Cache::get`). Two consequences: a
`cache:clear` in production hands the day a fresh allowance, and the question
*"why did AMS_APP use 73 Bakong requests today?"* has no per-call answer. The
table records `date, endpoint, reason, target, khqr_payment_id, http_status,
response_code, duration_ms, blocked_reason` — and never a token, header or
credential.

---

## Environment variables

See `.env.example` for the annotated copy. Defaults are chosen so that an
untouched install makes **zero** Bakong requests.

| Variable | Default | Meaning |
|---|---|---|
| `BAKONG_API_ENABLED` | `false` | master switch — nothing calls Bakong while false |
| `BAKONG_API_BASE_URL` | *(empty)* | from NBC onboarding; empty also disables |
| `BAKONG_EMAIL` / `BAKONG_ORGANIZATION` / `BAKONG_PROJECT` | *(empty)* | integrator registration identity |
| `BAKONG_ACCOUNT_ID` | *(empty)* | platform Bakong account the subscription QRs pay into |
| `BAKONG_DAILY_REQUEST_LIMIT` | `80` | safety margin under Bakong's ~100/day |
| `BAKONG_VERIFY_COOLDOWN` | `60` | minimum seconds between live calls per transaction |
| `BAKONG_QR_TTL` | `6` | minutes a QR stays payable |
| `BAKONG_MAX_VERIFY_ATTEMPTS` | `8` | live calls one checkout session may ever cost |
| `BAKONG_FAILURE_BACKOFF` | `15` | minutes to stop calling after a refusal that will still be true |
| `BAKONG_RECONCILE_ENABLED` | `false` | the polling safety net |
| `BAKONG_DEMO` | `false` | local simulation; never transmits, hard-off in production |

**The token itself is never an environment variable.** It is obtained through
`request_token` → `verify`, stored encrypted in `bakong_tokens`, and renewed
from the JWT's own `exp`.

---

## Migration checklist (Phase 21 — removing KHQRPay)

Nothing is removed until it is proven unused.

| KHQRPay dependency | Replacement | Tested | Safe to remove |
|---|---|---|---|
| `subscriptionCheckoutUrl()` / hosted redirect | on-site QR page | ☐ | ☐ |
| `platformCheckoutFault()` / `probeHandoff()` | not needed — no one-way door | ☐ | ☐ |
| `requestQr()` (Flow B mint) | manual channel / `BakongQrService` | ☐ | ☐ |
| `queryProviderOutcome()` | `BakongTransactionService` | ☐ | ☐ |
| `/khqr/callback` + `KhqrCallbackController` | nothing — Bakong has no webhook | ☐ | ☐ |
| `KhqrProviderClient` | `BakongProviderClient` | ☐ | ☐ |
| `khqr:*` commands | `bakong:*` commands | ☐ | ☐ |
| khqr.cc config keys + `.env` | `config/bakong.php` | ☐ | ☐ |

**Deliberately kept even after KHQRPay is gone:** `WebhookIngestService` and the
`payment_webhooks` table (provider-agnostic, and historical deliveries are audit
evidence), the `PaymentStatus` state machine, `finalize()` /
`finalizeSubscription()`, `PaymentReversalService`, and every existing
`khqr_payments` row.

---

## Testing

- `BakongZeroRequestTest` — with `BAKONG_API_ENABLED=false`, prove **0**
  outbound requests via `Http::assertNothingSent()`, asserted at the HTTP layer
  rather than against a flag someone remembered to check.
- `BakongBudgetTest` — with the limit at 80 and 79 spent, two concurrent
  processes must not both get through.
- `BakongCooldownTest` — concurrent verification of the same transaction makes
  one request, not two.
- `BakongQrPayloadTest` — pinned EMV test vectors; the payload is the md5 key,
  so it must be byte-stable.
- The suite runs under `Http::preventStrayRequests()` (already configured in
  `tests/TestCase.php`).

Development uses mocks exclusively. The real API is contacted only by a single
deliberate smoke test, explicitly authorized, at the very end.
