# Migrating from KHQRPay to the official Bakong Open API

Branch: `feature/direct-bakong-api`. **Not deployed.** Nothing has been run
against production, and the integration ships switched off.

Source of truth for the API: *Bakong OpenAPI Documentation*, v1.0.2, May 2021,
National Bank of Cambodia —
`https://bakong.nbc.gov.kh/download/KHQR/integration/Bakong%20Open%20API%20Document.pdf`.
Nothing in this document or in the code invents an endpoint, a parameter, a
response field or a capability the PDF does not specify.

---

## Why

```
before:  AMS_APP  →  KHQRPay (khqr.cc)  →  Bakong / NBC  →  customer's bank
after:   AMS_APP  →  Bakong Open API    →  Bakong / NBC  →  customer's bank
```

---

## The three decisions this is built on

Taken 2026-09-19. Reversing any of them changes the architecture, not a config
value.

### 1. Hybrid token model — the AMS token covers subscriptions only

Bakong issues a token to an **integrator** (`email` + `organization` +
`project`), not to a merchant. That breaks the assumption underneath the
existing `settlement_target: platform|merchant` split, which exists because
khqr.cc gave every landlord their own profile and therefore their own allowance.

- **Flow A — SaaS subscriptions** (money to the platform): verified with the AMS
  integrator token. Low volume, high value, one credential. **This is the flow
  that migrated.**
- **Flow B — tenant rent** (money to each landlord): unchanged. It stays on the
  **manual channel**, where the KHQR is built from that landlord's own
  `bakong_account_id`, the tenant scans and pays, and the landlord confirms in
  their banking app. **Zero Bakong requests.**
- A landlord who registers their own integrator token can opt in to automatic
  verification later; the per-target budget is already in place for it.

Money settles to the landlord either way: the account id **inside the QR**
decides where funds land, not the token used to ask about them afterwards.

### 2. There is no webhook — polling is the only settlement signal

All eight documented endpoints are outbound request/response. There is **no
callback, no push, and no signing scheme for one**. This is the most
consequential difference from KHQRPay, where a signed callback was the *primary*
settlement path and polling was only the safety net.

Two consequences run through the whole design:

- The thing that spends the allowance is also the only thing that can see money
  arrive, so **every refusal must stay a refusal** — there is no second channel
  to catch what a wrong guess discards.
- `BakongGateway::validateWebhook()` **always returns false**, which is a
  security property rather than a gap. `/khqr/callback` is public and
  CSRF-exempt; without this, a forged POST naming a real transaction id would be
  the cheapest possible way to activate a subscription for free.

### 3. Conservative verification budget

Roughly **8 live calls per checkout**, so a ~100/day allowance supports a
working day rather than a dozen abandoned tabs.

| Setting | Value | Why |
|---|---|---|
| `BAKONG_VERIFY_COOLDOWN` | 60s | must stay well above the 10s browser poll |
| `BAKONG_QR_TTL` | 6 min | caps how long one abandoned tab can poll |
| `BAKONG_MAX_VERIFY_ATTEMPTS` | 8 | caps the *product* of rate × window |
| `BAKONG_DAILY_REQUEST_LIMIT` | 80 | safety margin under Bakong's ~100 |
| `BAKONG_RECONCILE_ENABLED` | false | no webhook to back up, so nothing to reconcile *against* |

Plus an **"I have paid — check now"** button, which spends a check at the one
moment it is most likely to succeed.

---

## The official API, as specified

Base URL is `{{baseUrl}}` in the document — NBC supplies it at onboarding. It is
**not guessed anywhere in this codebase**; an empty `BAKONG_API_BASE_URL`
disables the integration.

| # | Name | Method | Path | Auth |
|---|---|---|---|---|
| 1 | Request token | POST | `/v1/request_token` | none |
| 2 | Verify (email code) | POST | `/v1/verify` | none |
| 3 | Renew token | POST | `/v1/renew_token` | none |
| 4 | Generate deeplink by QR | POST | `/v1/generate_deeplink_by_qr` | not listed in the doc |
| 5 | Check transaction by md5 | POST | `/v1/check_transaction_by_md5` | `Bearer <token>` |
| 6 | Check transaction by full hash | POST | `/v1/check_transaction_by_hash` | `Bearer <token>` |
| 7 | Check transaction by short hash | POST | `/v1/check_transaction_by_short_hash` | `Bearer <token>` |
| 8 | Check Bakong account | POST | `/v1/check_bakong_account` | `Bearer <token>` |

**Envelope:** `{data, errorCode, responseCode, responseMessage}`. `responseCode`
is 0 success / 1 fail; `errorCode` narrows it — **1** transaction not found,
**2** static QR unsupported, **3** transaction failed, **4** deeplink provider
error, **5** missing fields, **6** unauthorized, **7** email server down, **8**
email already registered, **9** cannot connect, **10** not registered, **11**
account id not found. HTTP codes: 200, 400, 401, 403, 404, **429**, 500.

**Token lifecycle.** `request_token` emails a 20-character code; `verify`
exchanges it for a JWT; `renew_token` takes only the email. NBC's sample tokens
carry `exp` ≈ **93 days** after `iat`, and expiry is read out of the JWT locally
— asking the API when a token expires would pay a metered request for
information the token already states.

### Verified against the document, not assumed

`BakongQrPayloadTest` reproduces the checksum of the sample QR printed in
section 4.2, which pins the CRC algorithm and the payload structure together.
It also records a detail worth knowing: the document renders the spaces in that
sample's merchant name and city as `+`. Read as literal plus signs the checksum
does not match; read as spaces it matches exactly.

---

## What Bakong does NOT provide

| Missing | What KHQRPay did | What AMS does instead |
|---|---|---|
| Webhook / callback | signed POST to `/khqr/callback` | polling only, under a conservative budget |
| Hosted checkout page | browser redirected to khqr.cc | on-site QR; **no `redirect()->away()`** |
| QR image generation | returned a hosted PNG URL | `BakongQrService`, rendered locally as SVG |
| Per-merchant credentials | one profile per landlord | hybrid token model (decision 1) |
| Refunds | (not used) | out of scope; unchanged |

---

## Architecture

> **ONE APPLICATION LAYER MAY MAKE EXTERNAL BAKONG REQUESTS.**
> Controllers, jobs, commands, models, Blade views and scheduled tasks never
> call Bakong directly.

```
                          AMS_APP
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
    Payment UI          Scheduler            Artisan
   (checkout views)  (reconcile, token)  (token/usage/diagnose)
         └───────────────────┼───────────────────┘
                             ↓
                   BakongTransactionService     business rules,
                   BakongQrService              three-valued verify
                   BakongTokenService
                             ↓
          ┌──────────────────────────────────────────┐
          │           BakongProviderClient           │  ← the ONLY Http:: to NBC
          │   11 gates, every refusal BEFORE the     │
          │   request, because a refused request     │
          │   costs exactly what a sale costs        │
          └──────────────────────────────────────────┘
                             ↓            ↕ BakongQuotaLedger
                  Official Bakong Open API   (budget, cooldown, backoff)
                             ↓
                       Bakong / NBC
                             ↓
                     customer's bank
```

### `BakongProviderClient` — the eleven gates

In order, cheapest and most absolute first, the one that spends the day's
allowance last:

| # | Gate | Refuses when |
|---|---|---|
| 1 | `bakong_disabled` | `BAKONG_API_ENABLED` is false. Absolute. |
| 2 | `demo_mode` | the local simulation must never transmit |
| 3 | `not_configured` | no base URL — a half-configured `.env` is normal mid-setup |
| 4 | `invalid_request` | unknown reason, endpoint, target, or a row-bound reason with no row |
| 5 | `no_token` | no usable token. **An expired token counts as absent** — a 401 costs what a sale costs |
| 6 | `no_active_payment` | the row is not a live payment session. *A database row is not a payment.* |
| 7 | `rate_limited` | Bakong answered 429 recently |
| 8 | `provider_backoff` | a refusal that will still be true next time |
| 9 | `verify_cooldown` | same transaction asked about inside the cooldown, or **right now** by another process |
| 10 | `max_attempts_reached` | this session has already cost as much as any session needs |
| 11 | `daily_budget_exhausted` | the ceiling. Reserved atomically, **fails closed** |

Gates 5 and 8 have an exception list: the three token endpoints and the manual
diagnostic are exempt from the **backoff**, because they are what *fixes* a
backed-off credential. None of them is exempt from the 429 or the ceiling.

### Why it was built beside `KhqrProviderClient` rather than as a refactor of it

The original plan was a shared `ProviderQuotaLedger`. In the building it turned
out the genuinely shared part was small — two cache wrappers — because the daily
ceiling counts rows in a Bakong-specific table. So the primitives went into
`BakongQuotaLedger` and the KHQRPay client was left **untouched**: it was pinned
by a fork-based concurrency suite, it was due to be retired wholesale, and
refactoring a live payment guard on its way out is risk with no payoff.

That judgement paid off in Phase 21: the old client was **deleted in one
commit** rather than unpicked from a shared abstraction.

---

## Files

**Created**

```
config/bakong.php
app/Models/BakongApiCall.php
app/Models/BakongToken.php
app/Services/Bakong/BakongProviderClient.php     ← the only Http:: to NBC
app/Services/Bakong/BakongQuotaLedger.php        ← budget, cooldown, backoff
app/Services/Bakong/BakongQrService.php          ← EMV payload + local SVG
app/Services/Bakong/BakongQr.php
app/Services/Bakong/BakongResult.php
app/Services/Bakong/BakongTransactionService.php
app/Services/Bakong/BakongTokenService.php
app/Services/Payment/Gateways/BakongGateway.php
app/Services/Payment/SubscriptionCheckout.php    ← which provider, in one place
app/Console/Commands/BakongTokenCommand.php
app/Console/Commands/ShowBakongUsage.php
app/Console/Commands/DiagnoseBakong.php
app/Console/Commands/ReconcileBakongPayments.php
resources/views/components/bakong-qr.blade.php
database/migrations/2026_09_19_0000{01,02,03}_*.php
tests/Feature/Bakong/*.php                       (7 files)
docs/BAKONG_MIGRATION.md
```

**Modified**

```
app/Models/KhqrPayment.php                 + isActiveBakongSession(), usesBakong()
app/Services/Payment/PaymentManager.php    + the 'bakong' driver
app/Http/Controllers/SubscriptionController.php
app/Http/Controllers/Admin/BillingController.php
resources/views/subscribe/checkout.blade.php
resources/views/admin/billing/checkout.blade.php
routes/console.php                         + token renewal, reconcile (both skipped by default)
lang/{en,km}/messages.php
.env.example  ·  phpunit.xml  ·  composer.json (bacon/bacon-qr-code)
```

**Untouched, deliberately:** the `PaymentStatus` state machine, `finalize()` /
`finalizeSubscription()`, `PaymentReversalService`, `IncomeRecordingService`,
every rent-collection and bill path, `KhqrProviderClient`, and every existing
`khqr_payments` row.

---

## Database changes

Additive and backward compatible. **No renames, no drops, no data migration, no
existing payment row altered.**

`khqr_payments.provider` already existed and already defaulted to `'khqrpay'`,
and `PaymentManager` already resolved a driver per row — that column is the
migration seam and it predates this work.

| Change | Purpose |
|---|---|
| `khqr_payments.qr_payload` (text, null) | the exact EMV string rendered |
| `khqr_payments.qr_md5` (char 32, null, indexed) | the verification key |
| `khqr_payments.provider_hash` (string, null) | Bakong's transaction hash, for audit |
| new `bakong_tokens` | the integrator token, `encrypted` at rest |
| new `bakong_api_calls` | durable per-call request accounting |

**Why `qr_payload` is stored rather than rebuilt.** `check_transaction_by_md5`
is keyed on the md5 of the exact string shown to the payer. Rebuilding it later
would mean every input — merchant name, city, currency, account id, even
whitespace — must still produce byte-identical output months later. One settings
edit and a paid transaction becomes permanently unverifiable.

**Why accounting is a table.** The KHQRPay ceiling lives in `Cache`, so
`cache:clear` — the command someone reaches for when a gateway misbehaves —
hands the day a fresh allowance; and a per-day total cannot answer *"why did we
use 73 requests today?"*. The table records allowed **and** refused attempts
with the reason for each, and never a token, header or request body.

---

## Environment variables

Full annotated copy in `.env.example`. **Defaults make an untouched install
issue zero Bakong requests.**

| Variable | Default | Meaning |
|---|---|---|
| `BAKONG_API_ENABLED` | `false` | master switch |
| `BAKONG_API_BASE_URL` | *(empty)* | from NBC; empty also disables |
| `BAKONG_EMAIL` / `_ORGANIZATION` / `_PROJECT` | *(empty)* | integrator identity |
| `BAKONG_ACCOUNT_ID` | *(empty)* | where subscription money lands — **fallback**, see below |
| `BAKONG_MERCHANT_NAME` / `_CITY` / `BAKONG_CURRENCY` | app name / Phnom Penh / USD | printed inside the QR — **fallback** |
| `BAKONG_DAILY_REQUEST_LIMIT` | `80` | ceiling; 0 disables |
| `BAKONG_VERIFY_COOLDOWN` | `60` | seconds between calls per transaction |
| `BAKONG_QR_TTL` | `6` | minutes a QR stays payable |
| `BAKONG_MAX_VERIFY_ATTEMPTS` | `8` | live calls one session may cost |
| `BAKONG_FAILURE_BACKOFF` / `_RATE_LIMIT_BACKOFF` | `15` / `5` | minutes |
| `BAKONG_RECONCILE_ENABLED` / `_GRACE` | `false` / `30` | the safety net |
| `BAKONG_DEEPLINK_*` | off | open-in-Bakong-app links |
| `BAKONG_DEMO` | `false` | local simulation, hard-off in production |
| `BAKONG_CONNECT_TIMEOUT` / `BAKONG_TIMEOUT` | `3` / `8` | seconds; **no retries anywhere** |

**The payout identity lives in Superadmin → Payment Settings**, not in `.env`.
`BakongPlatformIdentity::current()` reads `platform_payment_settings` first and
falls back to the variables above — the same order `KhqrCredentials::platform()`
already uses, and for the same reason: the person who needs to change a payout
account is not the person with shell access. A blank column falls *through*
rather than overriding with emptiness, so a row saved before these fields
existed keeps working. `bakong:diagnose` prints which source is in force.

**The token is never an environment variable.** It is issued at runtime and
stored encrypted, so a rotated token never sits in a shell history, a config
cache or a `.env` backup.

---

## Commands

```bash
php artisan bakong:token status              # free, offline
php artisan bakong:token request             # 1 request — emails a code
php artisan bakong:token verify              # 1 request — prompts for the code
php artisan bakong:token import              # 0 requests — store a token you already hold
php artisan bakong:token renew [--if-due]    # 1 request; --if-due is the scheduler's
php artisan bakong:usage [--days=7]          # free, offline
php artisan bakong:diagnose [--live]         # free; --live spends exactly 1
php artisan bakong:reconcile [--dry-run]     # off by default
```

## Scheduler

| Entry | Cadence | Skipped unless |
|---|---|---|
| `bakong:token renew --if-due` | daily 03:20 | `BAKONG_API_ENABLED` |
| `bakong:reconcile` | every 15 min | `BAKONG_API_ENABLED` **and** `BAKONG_RECONCILE_ENABLED` |

`khqr:reconcile` is left exactly as it was. Both new entries are applied as
`->skip()` rather than commented-out lines, so `schedule:list` still shows them.

**No queue changes.** Nothing about this integration is queued: verification is
request-scoped, and a queued verify would only add a second uncoordinated source
of metered calls.

---

## Tests

901 passing, including 7 new Bakong files.

| File | What it pins |
|---|---|
| `BakongZeroRequestTest` | **zero** outbound requests with the switch off, asserted at the HTTP layer via `Http::assertNothingSent()` — not against a flag someone remembered to check |
| `BakongConcurrencyTest` | **real forked processes**: 79 of 80 spent + 8 workers → exactly 1 call; 8 processes on one transaction → exactly 1 call; per-target isolation |
| `BakongQrPayloadTest` | CRC against its canonical check value **and** NBC's own sample QR; tag 62 vs 99; KHR whole numbers; byte-stability |
| `BakongTokenTest` | renewal from the JWT, not the API; 20-char code rejected locally; token encrypted and never in output |
| `BakongVerificationTest` | only a 2xx may say unpaid; 7 refusal shapes each leave the row **open**; amount/currency checked; the webhook that cannot exist is rejected |
| `BakongProviderSwitchTest` | config decides new payments, the **row** decides existing ones |
| `BakongCommandsTest` | reports never spend the allowance they report on |

Development used mocks exclusively. **The real API has not been contacted.** The
suite runs under `Http::preventStrayRequests()`.

---

## Production deployment plan — NOT YET RUN, needs explicit approval

Nothing below has been executed. No production file, database, `.env` or
credential has been touched.

### Before anything

1. `mysqldump` the production database, and keep it until the migration is
   confirmed good.
2. Confirm the NBC base URL and that the integrator registration
   (email/organization/project) is the one you want to own this token.
3. Read `docs/BAKONG_MIGRATION.md` (this file) and confirm decisions 1–3 still
   hold.

### Deploy with Bakong OFF first

```bash
git checkout feature/direct-bakong-api     # or merge to main
composer install --no-dev
php artisan migrate --force                # 3 additive migrations
php artisan config:clear && php artisan config:cache
php artisan route:cache && php artisan view:cache
```

At this point **nothing has changed for customers**: `BAKONG_API_ENABLED`
defaults to false, so every subscription checkout still goes through KHQRPay.
Verify that first — take one KHQRPay checkout through to the hosted page.

> **Do not `cache:clear` or `optimize:clear` on production to refresh config.**
> The budget counters live in the database now, but the cooldown slots and both
> backoffs are still cache-backed. Use `config:clear && config:cache`.

### Then obtain the token

```bash
# add BAKONG_API_BASE_URL, BAKONG_EMAIL, BAKONG_ORGANIZATION,
# BAKONG_PROJECT, BAKONG_ACCOUNT_ID to .env — but leave
# BAKONG_API_ENABLED=false for now
php artisan config:cache

php artisan bakong:diagnose          # free; confirms config before spending anything
```

`bakong:token` needs the switch on, so flip it, obtain the token, and confirm
before any customer is routed:

```bash
# BAKONG_API_ENABLED=true
php artisan config:cache
php artisan bakong:token request            # 1 request; code arrives by email
php artisan bakong:token verify             # 1 request; prompts for the code
php artisan bakong:diagnose --live          # 1 request; confirms token + account
php artisan bakong:usage                    # free; expect 3 spent today
```

### Then take one real payment

Renew **your own** subscription through Admin → Billing, scan the QR, pay a real
(small) amount, and confirm:

- the page flips to paid on its own,
- the subscription's `expires_at` extended,
- `bakong:usage` shows a handful of `payment_verification` calls, not dozens.

### Then watch

Leave `BAKONG_RECONCILE_ENABLED=false`. Check `bakong:usage` daily for the first
week. If a day's `payment_verification` count is far above ~8 per checkout,
something is polling that should not be, and the per-reason breakdown will say
what.

---

## Rollback plan

**There is no provider to roll back TO any more.** khqr.cc was removed in Phase
21 (below), so `BAKONG_API_ENABLED=false` is now a *stop* switch rather than a
*switch-over*: the next subscription checkout refuses with a message instead of
falling back.

```bash
# .env — stop taking subscription payments, immediately, no deploy
BAKONG_API_ENABLED=false
php artisan config:clear && php artisan config:cache
```

What that does and does not do:

- **Refuses new checkouts before any row exists**, so there is no orphan session
  left behind and no QR shown to a customer that nobody is watching.
- Payments **already minted** keep being routed by `khqr_payments.provider`, not
  by config. With the switch off the outbound call is refused, so those rows stay
  **open** rather than being expired on a request that was never made. Turning it
  back on picks them up again.
- **Nothing is un-migrated.** Every ledger row, payment and subscription is
  untouched.

**Rolling back the CODE is a different question.** Reverting past Phase 21
restores files that expect `services.khqrpay` config and the
`khqrpay_profile_id` / `khqrpay_secret` columns, which the Phase 21 migration
drops. So a revert needs `php artisan migrate:rollback --step=1` as well — and
that gives back the *columns*, never the *values*: an encrypted secret cannot be
un-dropped. Re-issue it at khqr.cc if it is ever wanted again, which it should
not be.

---

## errorCode 17 — the undocumented one that costs a day

NBC's v1.0.2 error list stops at 11. In production the gateway returns:

```
HTTP 200 · responseCode 1 · errorCode 17
"Daily request limit of 100 exceeded. Please try again tomorrow."
```

Coding strictly to the published list made this a *generic* refusal, so it
tripped no backoff and the app re-asked once per cooldown for the rest of the
day — each retry charged exactly like a sale.

`BakongQuotaLedger::markUpstreamExhausted()` now latches it **until local
midnight**, which is what the message actually says, and gate 7b refuses
everything meanwhile — **including token renewal and the operator diagnostic**.
Those two are exempt from the *failure backoff* because they are what fixes a
bad credential; a spent allowance is not a bad credential, and both would be
charged and refused.

It is matched by **message as well as code**, since an undocumented code may not
be the only one. The needles are phrases (`daily request limit`, `quota`,
`too many request`), never the bare word `limit` — a gateway saying *"amount
below minimum limit"* is describing one request, not the account.

**This is separate from our own ceiling, and on a shared token it is the one
that bites.** `daily_request_limit` counts what *we* spend; NBC meters the
*token*, including every request made by anything else holding it. The allowance
can be gone while our ledger reads 6 of 80 — which is exactly what happened.
`bakong:diagnose` reports the two separately for that reason.

---

## Remaining risks

| Risk | Severity | Mitigation / status |
|---|---|---|
| **Polling is the only settlement signal** | high | conservative budget, "check now" button, reconcile net available. Watch `bakong:usage` in week 1. |
| **Response shapes come from a May-2021 document** | medium | every shape is read defensively — a 2xx that is not the envelope is REFUSED, not UNPAID. Confirm against one real payment before relying on it. |
| **`generate_deeplink_by_qr` auth is unstated** | low | the deeplink feature ships **off**; the token is attached opportunistically, never required. Confirm with NBC before enabling. |
| **Tag 62 sub-tag choice** | low | NBC's sample uses sub-tag 08 (purpose); this uses 01 (bill number), the EMVCo field for what we put there. Verification is unaffected — the md5 is over whatever we emit. Check one QR in a real banking app. |
| **Flow B is manual** | accepted | decision 1. Rent is confirmed by the landlord and costs **zero** Bakong requests. Verifying it through the platform token would put every landlord's tenants on one ~100/day allowance. |
| **A closed tab means no confirmation** | medium | **the one real functional loss.** khqr.cc had a genuine server-to-server webhook; NBC publishes none. Mitigations: the "I have paid — check now" button, `BAKONG_RECONCILE_ENABLED=true`, reopening the checkout link, or confirming by hand. |
| ~~`api.qrserver.com` used by the manual channel~~ | **resolved** | Phase 21: the rent QR is rendered locally as an inline `data:` URI. Nothing leaves the server, and the QR no longer vanishes when a third party is unreachable. |
| **One shared allowance if Flow B is ever migrated** | future | the per-target budget exists; a landlord would need their own integrator token first. |

---

## Phase 21 — khqr.cc removed (2026-09-19)

**Done, on the branch, not yet deployed.** The trigger was not a tidy-up: the
upstream token khqr.cc held a copy of was being drained to NBC's daily limit
every day for a month while this app's own ledger showed **six** requests. Every
payment then failed with `errorCode 17`, and a refused request is metered exactly
like a paid one — so the day was gone before anyone could notice. Splitting the
credential was the only way to make the allowance legible.

### What was deleted

| Removed | Replaced by |
|---|---|
| `KhqrProviderClient`, `KhqrProviderResult` | `BakongProviderClient`, `BakongResult` |
| `KhqrCredentials` | `BakongPlatformIdentity` (identity, not credentials) |
| `KhqrPayGateway` | `BakongGateway` + `ManualGateway` |
| `WebhookIngestService`, `KhqrCallbackController`, `POST /khqr/callback`, its CSRF exemption | **nothing — there is no webhook.** See below. |
| `<x-khqr-diagnostics>`, `admin.billing.diagnostics` | `<x-bakong-usage>` on Superadmin → Payment Settings |
| `platformCheckoutFault()` + both preflight probes | not needed — no one-way door to guard |
| `khqr:diagnose`, `khqr:reconcile`, `khqr:usage`, `khqr:test-qr` | `bakong:diagnose`, `bakong:reconcile`, `bakong:usage` |
| the whole `services.khqrpay` config block | `config/bakong.php` + `config/rent_qr.php` |
| `khqrpay_profile_id` / `khqrpay_secret` / `khqrpay_enabled` columns | dropped — a credential nothing reads is a credential nobody rotates |
| `KhqrPaymentService`'s provider half (2214 → 462 lines) | `Http::` no longer appears in that file at all |

### What deliberately stayed

- **`khqr_payments` and `payment_webhooks` rows.** Money records and their audit
  trail. `provider` still says `khqrpay` on the old ones and still resolves,
  through `RetiredKhqrPayGateway` — a tombstone whose `verify()` returns *false
  meaning no confirmation*, never *unpaid*, and which nothing acts on. Without
  it a settled payment from last quarter would 500 the payments console.
- **`khqr:expire-abandoned`.** Now the only thing that will ever close the open
  `channel = 'api'` rows khqr.cc left behind: automatic expiry requires a
  conclusive unpaid, and there is no gateway left to give one.
- **The rent channel**, rebuilt local: `BakongQrService` renders the QR as an
  inline `data:` URI from the landlord's own `bakong_account_id`, and the
  landlord confirms receipt. Zero requests, no credentials, no third party.

### Two behaviours that changed, and are worth knowing before deploying

1. **There is no webhook, and now no endpoint either.** A subscription is
   confirmed by the checkout page's poll *while it is open* (~60s), by
   `bakong:reconcile` if switched on, or by hand. A payer who closes the tab with
   the net off gets no confirmation. This is the one real functional loss.
2. **A legacy khqr.cc row reports `gateway_error: true` / `gateway_answered:
   false` forever** and is never polled. Bakong has never heard of that
   transaction and would answer "could not be found" — which reads as UNPAID,
   which expires the QR. Settle one by hand: SuperAdmin → Accounts → change plan.

### Deploy order — do not reorder

1. **Before deploying**, find any open khqr.cc rows. The webhook that could still
   settle them is about to be removed:
   ```bash
   php artisan tinker --execute="echo App\Models\KhqrPayment::where('provider','khqrpay')->whereIn('status',App\Enums\PaymentStatus::openValues())->count();"
   ```
   Non-zero: settle or close them first (`khqr:expire-abandoned --dry-run`).
2. Deploy the branch. The migration **drops the khqr.cc credential columns** —
   irreversible as to values.
3. Confirm Superadmin → Payment Settings shows the payout account and a sane
   allowance meter. Opening it costs nothing.
4. Take **one** real subscription payment end to end.
5. Only then remove the NBC token from the khqr.cc dashboard. **Check what else
   uses that khqr.cc profile first** — Smart_sell does, and it will stop taking
   payments the moment the token is revoked.
6. Consider `BAKONG_RECONCILE_ENABLED=true` to cover closed tabs, then watch
   `bakong:usage` for a few days.

**Capacity after the split:** 100 requests/day ÷ ~6 per checkout ≈ **15
subscription payments a day**, all of it now visible in one meter. Tenant rent
adds nothing to that figure.
