# CLAUDE.md

Guidance for working in **AMS_APP** — a multi-tenant SaaS Apartment Management System (Laravel 12 / PHP 8.2).

> `PROJECT_GUIDEBOOK.md` is the academic overview doc — where it and this file disagree, trust this file and the code.

---

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2+, Laravel 12, Eloquent ORM |
| Auth | Laravel Breeze + Sanctum (`statefulApi`) |
| Authorization | `spatie/laravel-permission` (role-based) |
| Frontend | Blade + Tailwind CSS 3 + Alpine.js + Vite 7 + Chart.js |
| PDF | `barryvdh/laravel-dompdf` |
| Payments | **Bakong Open API** (NBC, direct) for subscriptions; a locally built KHQR the landlord confirms for tenant rent. khqr.cc retired 2026-09. |
| i18n | English + Khmer (`en`, `km`); `lang/en/messages.php`, `lang/km/messages.php` |
| Tests | Pest 4 |
| DB | MySQL (prod) / SQLite (dev/test) |

---

## Roles & route structure

Four roles, each with its own controller namespace, view folder, and route group in `routes/web.php`:

| Role | Route middleware | Controller namespace | Views |
|------|-----------------|---------------------|-------|
| `superadmin` | `role:superadmin` + prefix `superadmin/` | `App\Http\Controllers\SuperAdmin` | `resources/views/superadmin/` |
| `admin` | `role:admin\|superadmin`, `subscription.active` | `...\Admin` | `.../admin/` |
| `supervisor` | `role:supervisor\|admin\|superadmin`, `subscription.active`, prefix `supervisor/` | `...\Supervisor` | `.../supervisor/` |
| `tenant` | `role:tenant` | `...\Tenant` | `.../tenant/` |

- `/dashboard` redirects each user to their role-appropriate dashboard.
- **Supervisor routes intentionally allow `admin|superadmin`** for preview access — do not tighten to `role:supervisor`.
- Keep controllers within their role namespace; views mirror the controller path (`Supervisor\TenantController` → `views/supervisor/tenants/`).

### Shared panel code — the Admin/Supervisor de-duplication pattern

Admin and Supervisor share most of their module logic. **Never copy a page or
controller between the two panels** — use the shared pattern:

- `App\Http\Controllers\Shared\RevenueExpenseController` is the single abstract
  implementation of Revenue & Expense; `Admin\RevenueExpenseController` and
  `Supervisor\RevenueExpenseController` are thin subclasses that only pin hooks:
  `panel()` ('admin'|'supervisor'), `fiscalPeriodsQuery()`, `ledgerUserId()`,
  `khqrRoutePrefix()`, `missingPeriodRedirect()`, `authorizeOtherExpenseDelete()`.
  All supervisor property guards live in the base and **no-op for admins** via
  `ScopesToSupervisorProperties::seesWholeAccount()`.
- Shared Blade views live in `resources/views/shared/{revenue_expense,tenants,apartments}/`
  and take a `$panel` variable: `@extends('layouts.'.$panel)`,
  `route($panel.'.revenue_expense.record_income')`. Render them with
  `panelView()` (base controller) or `view('shared…', $data + ['panel' => …])`.
- Tenant `index`/`edit` pages are **intentionally separate** per panel
  (`views/admin/tenants/`, `views/supervisor/tenants/`) — the admin page has the
  consolidated "All properties" mode, the supervisor page has income summary
  cards. The two TenantControllers likewise stay separate; keep their
  validation rules in sync (`gender`, `email`, `id_card_number` exist in both).
  Separate pages are **not** licence to answer the same question differently:
  the supervisor page used to hand its active fiscal period to
  `TenantRentProgressCalculator`, widening the payment window to the whole year
  while the percentage still divided by one month's rent — every tenant with
  payment history read "paid" in an unpaid month, to the people whose job is
  collecting it, while the admin page called the same tenant overdue. Rent
  progress is a **current-month** question; the calculator takes no period.
  `tests/Feature/Tenants/RentProgressConsistencyTest.php` asserts both panels
  agree.
- `tests/Feature/SharedPanelViewsTest.php` renders every shared page as both
  roles — keep it passing when touching shared views.

---

## Multi-tenancy — the most important architectural fact

Each customer account is owned by one **admin `User`**. All customer data is isolated per account.

### `BelongsToAccount` trait (`app/Models/Concerns/BelongsToAccount.php`)

Most Eloquent models use this trait. It:
- Adds a global `account` scope that constrains every query to `current_account_id()`.
- Stamps `account_id` on `creating`.
- Rows with `NULL account_id` are treated as legacy/unowned — they stay visible to everyone (for pre-multitenancy fixtures).

When adding a customer-owned model: add `use BelongsToAccount;` and an `account_id` column in the migration.

**Exceptions — models intentionally NOT account-scoped:**
- `Subscription` — read across accounts by the superadmin panel and by the signup flow before auth exists. Never add `BelongsToAccount` to it.

### `current_account_id()` (`app/helpers.php`)

Returns the account id for the current request:
- **Account owner (admin)** → their own `user.id` (the owner's `account_id` points to themselves).
- **Co-admin / Supervisor / Tenant** → their `users.account_id` (which points to the owner).
- **Unauthenticated (login, signup, seeders, console)** → `null` → the scope is a no-op so global lookups still work.

### An account can have more than one admin

Team management (`Admin\UserController`) hands out `admin`, `supervisor` and
`tenant`. An assigned **admin is a co-admin of the same account**, not a second
account: their row keeps `account_id` = the owner's id, so `current_account_id()`
— and with it every `BelongsToAccount` query, the subscription gate and the
billing pages — resolves to the owner's data. Two consequences carry the design:

- **The books hang off the account owner's user id, never the acting admin's.**
  `fiscal_periods.user_id` and `accounts.user_id` are the account's ledger, so
  every admin-side `ledgerUserId()` / `fiscalPeriodsQuery()` /
  `FiscalPeriods::where('user_id', …)` / ledger-row write uses
  `current_account_id()`, **not `Auth::id()`** — including
  `EnsureFiscalPeriodExists`. For the owner the two are identical; for a
  co-admin `Auth::id()` would open a *second* set of books and silently drop
  their income and expenses out of the owner's reports. `Auth::id()` stays
  correct for actor attribution (`managed_by`, `created_by`, `uploaded_by`).
- **The owner row is not a team member.** `authorizeTeamMember()` 403s the
  account owner (their user id *is* the account id), any superadmin, and your
  own row — self-service belongs to Profile, and a co-admin could otherwise
  demote or delete themselves. `updateRole()` refuses the same three.
  `_row`/`_card` mirror the rule as `$rowLocked`.
- Co-admins occupy a **staff seat** (`SubscriptionService::staffCount()` counts
  supervisors *and* admins, excluding the owner) — otherwise the plan's
  `max_staff` cap is bypassed by handing out admin logins.

`tests/Feature/CoAdminTest.php` pins all of it.

### SuperAdmin reads across all accounts

Use `Model::withoutAccountScope()` (or `withoutGlobalScope('account')`) in any superadmin controller or service that needs cross-account data.

---

## Supervisor scoping (separate from account scoping)

Supervisors are further scoped to **properties assigned to them** (`properties.supervisor_id`). They only see floors, rooms, and tenants under their assigned properties.

- Implemented via `App\Http\Controllers\Concerns\ScopesToSupervisorProperties` — include this trait in any Supervisor controller that queries apartments/floors/tenants.
- Admins/superadmins hitting supervisor routes are **not** property-scoped (their account scope already isolates them). The trait's `seesWholeAccount()` check handles this.
- `supervisorPropertyIds()` returns a collection of property IDs assigned to the current user.

---

## Middleware reference

| Alias | Class | Behaviour |
|-------|-------|-----------|
| `role:X` | `RoleMiddleware` | Aborts 401 if not authenticated; 403 if user lacks the pipe-delimited role(s). |
| `subscription.active` | `EnsureSubscriptionActive` | Superadmin is exempt. Admin with no active subscription → `admin.billing.index`. Supervisor with no active subscription → `supervisor.dashboard` with a warning (they can't renew). |
| `fiscal.period` | `EnsureFiscalPeriodExists` | Admin: requires an open `FiscalPeriods` row on their **account** (`current_account_id()`, so co-admins share the owner's); else → `admin.fiscalperiod.create`. Supervisor: requires any admin's open period; else → `supervisor.dashboard` with a warning. |
| `month.close` | `EnsureMonthCloseBacklogClear` | Refuses **write** requests while 2+ finished months sit un-closed (`MonthCloseBacklog`). Superadmin and the payment-reversal route are exempt; admin → the month's close page, supervisor → `back()` with "ask the owner". |
| `SetLocale` | `SetLocale` | Runs on every web request. Priority: `session('locale')` → DB `Settings.app_locale` → `config('app.locale')`. Supported: `en`, `km`. |

---

## Key directories

```
app/
  Http/Controllers/{Admin,Supervisor,Tenant,SuperAdmin,Auth}/
  Http/Controllers/Shared/         ← abstract panel-shared controllers (RevenueExpense)
  Http/Controllers/Concerns/
    ScopesToSupervisorProperties   ← property-level supervisor scoping
    HasFiscalPeriodScope           ← fiscal period helpers shared by Admin + Supervisor
    HasDashboardMonthNavigation    ← month/year nav on dashboards
    HandlesKhqrCheckout            ← KHQR checkout flow helpers
  Http/Middleware/                 ← RoleMiddleware, EnsureSubscriptionActive,
                                      EnsureFiscalPeriodExists, SetLocale
  Models/                          ← Eloquent models
  Models/Concerns/BelongsToAccount ← multi-tenant global scope
  Services/
    Audit/AuditLogger              ← append-only audit log; never throws into caller
    Dashboard/                     ← DashboardStatsService, FiscalPeriodSummaryService,
                                      ApartmentRevenueComparisonService, DashboardCalendarService
    FiscalPeriod/                  ← BalanceSheetService, FiscalPeriodFinancialsService,
                                      FiscalPeriodReportsService, MonthlyPeriodManager
    Bakong/                        ← the WHOLE direct NBC Open API integration
      BakongProviderClient         ← the ONLY place that may talk to NBC
      BakongQuotaLedger            ← ceiling, cooldown, backoffs, exhaustion latch
      BakongQrService              ← builds + renders the EMV/KHQR payload
      BakongTokenService           ← issue/verify/renew; expiry read from the JWT
      BakongTransactionService     ← mint a subscription QR, verify, poll
      BakongUsageReport            ← the offline allowance report (panel + command)
      BakongPlatformIdentity       ← WHERE subscription money lands
    Payment/
      PaymentManager               ← resolves PaymentGateway drivers
      Gateways/BakongGateway       ← subscriptions
      Gateways/ManualGateway       ← tenant rent (landlord confirms)
      Gateways/RetiredKhqrPayGateway ← tombstone so khqr.cc history still reads
      SubscriptionCheckout         ← the one place a subscription payment is minted
      RefundService                ← handles refunds
    Platform/PlatformFinanceService← cross-account platform finance (superadmin)
    Platform/AccountPurgeService   ← full account deletion (rows + files);
                                      soft-delete models never fire DB cascades and
                                      history FKs are RESTRICT — delete children first
    RevenueExpense/                ← BreakEvenService, ExpenseRecordingService,
                                      IncomeRecordingService,
                                      KhqrPaymentService, MonthlyBillingService,
                                      RevenueExpenseQueryService
    Subscription/SubscriptionService
    Tenants/                       ← TenantLeaveProcessor, TenantPendingChargesQuery,
                                      TenantRentProgressCalculator, LeaseSyncService
    TenantLeaveCalculator          ← move-out proration calculator
    NotificationService
  Enums/
    PaymentStatus                  ← payment state machine values + transition rules
    SubscriptionStatus             ← subscription lifecycle values
  Contracts/PaymentGateway         ← interface for payment drivers
  helpers.php                      ← settings(), currency_symbol(), status_label(),
                                      current_account_id()
routes/web.php                     ← all app routes (role groups, SaaS funnel, KHQR webhook)
routes/auth.php                    ← Breeze auth routes
bootstrap/app.php                  ← middleware aliases, trusted proxies, CSRF exemptions
```

Prefer putting business logic in `app/Services/`, not controllers.

---

## Payment system

### State machine (`PaymentStatus` enum)

`KhqrPayment.status` is stored as **VARCHAR, not a DB enum** (a DB enum silently truncated values under MySQL strict mode — don't revert this). Always use `KhqrPayment::transitionTo(PaymentStatus $to)` to change status; it enforces legal transitions and throws on illegal ones. Never `forceFill` status directly.

States: `pending → qr_generated → waiting_payment → paid → refunded`  
Also terminal: `failed`, `expired`, `cancelled`, `rejected`

Open states (still in flight): `pending`, `qr_generated`, `waiting_payment`.

### Subscription status (`SubscriptionStatus` enum)

Similarly stored as VARCHAR. Active-access states: `active` and `trialing` (use `SubscriptionStatus::liveValues()`). Check access with `Subscription::isActive()`.

One free trial per account (`trialUsed()` check). Cancelled status grants access until `expires_at`.

### A plan change takes effect when the money lands, nowhere else

Plan caps are read straight off `subscriptions.plan_id`
(`SubscriptionService::usage()` → `activePlan()` → `activeSubscription()->plan`),
and `activeSubscription()` filters on `status` + `expires_at` only — it has no
idea whether the plan it returns was paid for. So **the plan a customer is
buying must never be written to a live subscription before payment**.
`Admin\BillingController::renew()` stamped it up front until 2026-08: a customer
on Basic who clicked upgrade to Pro, reached the checkout page and closed the tab
kept Pro's room/staff caps free until their Basic term expired.

- The purchase (`plan_id` + `billing_cycle`) rides on the **KhqrPayment**'s
  `checkout_payload`, stamped by `createSubscriptionQr($subscription, $amount,
  $plan, $cycle)`, and `finalizeSubscription()` is the **one** place that
  applies it. It falls back to the subscription's own values, which is what
  keeps rows minted before this existed (and the 2-arg `TestKhqrQr` call)
  working.
- Writing plan/cycle onto a **brand-new** row is fine and `renew()` still does
  it (`firstOrNew` + `status = 'pending'`) — a pending subscription is not an
  active one, so it grants nothing. The signup funnel relies on this: the
  checkout view reads `$payment->subscription->plan->name`.
- An abandoned choice is **deliberately lost**, not remembered. There is
  nowhere to remember it that doesn't also grant it.
- **An upgrade carries every leftover day, at the new plan.** `finalizeSubscription()`
  extends from the existing `expires_at` (not from today) *and* switches
  `plan_id`, so 20 days left on Basic + a Pro month = **50 days of Pro**, with
  the 20 upgraded free. Confirmed as the intended money rule 2026-08; proration
  (converting the unused *value* into fewer new-plan days) and forfeiting the
  remainder were both considered and rejected. Don't "fix" this into proration
  — it changes what every upgrade is worth.
- `SuperAdmin\AccountsController::changePlan()` is the sanctioned override — it
  sets the plan active with no payment, for money collected out-of-band. Note it
  *resets* `expires_at` from today (the paid path **extends** from the existing
  one) and writes no `KhqrPayment`, so it never shows in Superadmin → Payments
  or platform finance revenue.

`tests/Feature/Subscription/BillingCycleTest.php` pins all of it.

### Adding a payment provider

Implement `App\Contracts\PaymentGateway` (`provider()`, `verify()`) and register
the driver in `App\Services\Payment\PaymentManager`. There is deliberately **no
`validateWebhook()`** — see "No webhook exists" below.

Three keys are registered, and only two can mint anything:

| key | what it is |
|-----|-----------|
| `bakong` | SUBSCRIPTIONS, via the direct NBC Open API. |
| `manual` | TENANT RENT: a KHQR built on this server, confirmed by the landlord. |
| `khqrpay` | **RETIRED 2026-09.** `RetiredKhqrPayGateway`, a tombstone. |

The retired key is why the registry was not deleted along with the provider:
`khqr_payments.provider` is **history as much as configuration**, and a settled
payment from last quarter must not 500 the payments console because the gateway
that took it no longer exists. Its `verify()` returns `false` meaning *no
confirmation*, never *unpaid* — and nothing acts on that negative any more.

### khqr.cc is gone — don't bring it back

The KHQRPay middleman was removed in 2026-09 after a month in which the upstream
Bakong token it held a copy of was drained to its daily limit every day, while
this app's own ledger showed **six** requests. Every payment then failed with
`errorCode 17`, and because a refused request is metered exactly like a
successful one, the day was already lost before anyone noticed.

**What went with it:** `KhqrProviderClient`, `KhqrCredentials`,
`KhqrPayGateway`, `WebhookIngestService`, `KhqrCallbackController`, the
`POST /khqr/callback` route and its CSRF exemption, the `<x-khqr-diagnostics>`
popup and `admin.billing.diagnostics`, the two preflight probes, `khqr:diagnose`,
`khqr:reconcile`, `khqr:usage`, `khqr:test-qr`, the whole `services.khqrpay`
config block, and the `khqrpay_profile_id` / `khqrpay_secret` /
`khqrpay_enabled` columns on both payment-settings tables.

**What deliberately stayed:** `khqr_payments` and `payment_webhooks` rows (money
records and their audit trail), the `provider` column, and
`khqr:expire-abandoned` — which is now the *only* thing that will ever close the
open `channel = 'api'` rows khqr.cc left behind, since no gateway remains to
give the conclusive unpaid that automatic expiry requires.

`tests/Feature/Payment/KhqrCcRetiredTest.php` pins this **structurally** rather
than behaviourally — it greps `app/`, `config/`, `routes/` and `resources/views/`
for anything that could form a request or hold a credential. A test that merely
watched one flow would pass while a forgotten scheduler kept calling, which is
exactly how the leak survived so long the first time.

### No webhook exists

The Bakong Open API publishes **no callback of any kind** — all eight documented
endpoints are outbound request/response. The one webhook this app ever had
belonged to khqr.cc and was deleted with it.

That is the single real functional loss of the migration, and it must not be
papered over: a payment is confirmed by **the checkout page's poll while it is
open**, or by `bakong:reconcile` if that net is switched on, or by hand. If the
payer closes the tab before confirmation and the net is off, nothing will notice
the money arrived.

`/khqr/callback` was public **and** CSRF-exempt, so deleting it also removed the
one route where a forged POST naming a real transaction id would have been the
cheapest possible way to activate a subscription for free. Nothing is
CSRF-exempt now (`bootstrap/app.php`); add an exemption back only for an
endpoint that authenticates every request by its own signature.

### Two flows, two providers, and they do NOT share a token

| | Flow A — subscriptions | Flow B — tenant rent |
|---|---|---|
| Money goes to | the platform operator | the landlord's own bank |
| Provider | `bakong` (NBC Open API) | `manual` |
| Payout identity | `BakongPlatformIdentity` → `platform_payment_settings.bakong_account_id`, else `config/bakong.php` | `merchant_payment_settings.bakong_account_id` |
| Confirmed by | polling `check_transaction_by_md5` | **the landlord**, after checking their banking app |
| Costs metered requests | yes — verification only | **no — zero, ever** |
| Config | `config/bakong.php` | `config/rent_qr.php` |

**Rent is NOT wired through the platform's Bakong token, and that is a decision
rather than an omission.** That token is metered at roughly 100 requests a day
for the whole installation: every landlord's every tenant sharing one allowance
would let the busiest building lock out everyone else, and it would route a rent
payment's confirmation through credentials belonging to an account the money
never touches. Rent settles directly with the landlord, whose bank neither this
app nor NBC's token can see — which is precisely why the landlord is the oracle.

`App\Services\RevenueExpense\KhqrPaymentService` is the rent channel **and** the
one place a confirmed payment of either flow is BOOKED (`finalize()`,
`finalizeSubscription()`). The Bakong side calls into it rather than
reimplementing it, so there is exactly one path from "the money arrived" to "the
books say so". **`Http::` does not appear anywhere in that file** — keep it that
way; a rent payment needing a provider again is a new `PaymentGateway` driver,
not an outbound call reintroduced there.

### SaaS signup funnel

`/subscribe` → checkout → KHQR → activate — all in the `guest` middleware group
in `web.php`. **The customer never leaves.** There is no `redirect()->away()`
any more: the QR is built locally and rendered on this app's own page, so a
failure is something this app can still explain instead of a raw JSON body on
someone else's domain. `SubscriptionCheckout::handoffUrl()` survives, always
returning null, as the seam a hosted provider would plug back into — and
`preflightFault()` returns null for the same reason, which is what removed the
two metered probes that used to guard the door.

#### ONE CLIENT, AND MINTING IS FREE

`App\Services\Bakong\BakongProviderClient` is the **only** place in this app that
may talk to NBC. Every outbound request goes through `call()`; controllers, jobs,
commands, models, Blade views and scheduled tasks must never construct one. It
BUILDS the request itself (the call site supplies only a payload), so a call site
cannot express a request the client has not agreed to.

**Creating a QR costs nothing.** The Open API has no QR endpoint — `BakongQrService`
constructs the EMV payload here — so a customer reaching the checkout page spends
no allowance at all. Only verification is metered, which is what lets the whole
budget go on confirming payments rather than creating them. Two consequences:

- The payload is **stored** (`khqr_payments.qr_payload` + `qr_md5`), never
  rebuilt: `check_transaction_by_md5` takes the md5 of that exact string, so a
  later settings edit would otherwise make an already-paid transaction
  permanently unverifiable.
- The QR reaches the browser as an inline `data:` URI. The old manual channel
  handed the payload to `api.qrserver.com` as a query parameter — putting a live
  payment instruction, with the account id and amount, on a third party's server,
  and making the QR vanish whenever that service was unreachable.
- **Every amount-bearing QR must carry KHQR tag 99** — creation + expiration,
  unix milliseconds, 13 digits — or a banking app has no deadline to honour and
  refuses the code outright as "invalid QR code", however correct everything
  else about it is (valid TLV, byte-counted lengths, a verifying CRC-16). It is
  not an EMVCo field, which is exactly why an earlier builder's misuse of it (a
  bill number) got the whole tag deleted along with the misuse in 2026-09 —
  "not an EMVCo field" and "not a required field" are different claims, and a
  test asserting the tag ABSENT let that ship confirmed-green. The expiry
  written into tag 99 is the payment row's own `expires_at`, already set before
  the QR is built, so the deadline the payer's banking app shows and the
  deadline this app enforces are one fact rather than two that can drift; rent
  QRs pass `config/rent_qr.php`'s own lifetime the same way. An expiry already
  in the past is floored at one minute — a QR born expired scans as invalid,
  which is this same bug wearing a different hat.
  `tests/Feature/Payment/BakongQrTimestampTest.php` pins it.

**Eleven gates, all refusals BEFORE the request** (a refused Bakong request is
charged exactly like a paid one), cheapest and most absolute first: `disabled`,
`demo_mode`, `not_configured`, `invalid_request`, `no_token`,
`no_active_payment`, `rate_limited`, `upstream_quota_exhausted`,
`provider_backoff`, `verify_cooldown`, `max_attempts`, `daily_budget`. Every
allowed request and every refusal is written to `bakong_api_calls` with the
reason — which is why `reason` is a required parameter and a request nobody can
account for is refused outright.

#### The token is metered per day, and NBC meters the TOKEN, not this app

`config/bakong.php`; `env` names in `.env.example`.

- **`BAKONG_API_ENABLED`** — master switch, defaults **false**, asymmetrically:
  shipping it off on an install that wants Bakong costs one line of `.env`;
  shipping it on costs a metered token drained by something nobody remembered.
  With it off checkout **refuses** rather than falling back — there is nothing
  left to fall back to, and a session nobody can confirm is worse than none.
  An **empty `BAKONG_API_BASE_URL` is a second off switch** (NBC never publishes
  it in the document, so it is never guessed).
- **`BAKONG_DAILY_REQUEST_LIMIT`** (80) — our own ceiling, reserved atomically
  under a lock and **failing closed**. Deliberately below NBC's ~100 so hitting
  it is a local event we can see, not an upstream refusal affecting everything
  else on the token. `BAKONG_UPSTREAM_DAILY_LIMIT` (100) is **display only**.
- **`BAKONG_VERIFY_COOLDOWN`** (60) — minimum seconds between requests about the
  same transaction, claimed atomically (`Cache::add`) **before** the request.
  Must stay well above the browser poll interval (10s in all checkout views) or
  it absorbs nothing.
- **`BAKONG_QR_TTL`** (6 min) × the cooldown ≈ 6 requests per checkout;
  `BAKONG_MAX_VERIFY_ATTEMPTS` (8) caps that product.
- **`BAKONG_RECONCILE_ENABLED`** — ships **OFF**, and that is a deliberate
  downgrade from the KHQRPay net: there, it rescued payments whose *webhook*
  failed. Bakong sends none, so there is no delivery to fail — a payment is
  confirmed by a poll or it is not. Switch it on only where payers routinely
  close the tab, then watch `bakong:usage`.
- **Don't `cache:clear` / `optimize:clear` in production** to refresh config: the
  cooldown slots, backoffs and exhaustion latch live in the cache. Use
  `config:clear && config:cache`. (The daily *budget* is a table, not a counter —
  that is why `bakong_api_calls` exists.)

**`upstreamExhausted` is a separate finding from our own ceiling, and on a shared
token it is the one that bites.** Our ceiling counts what *we* spent; the latch
records what the TOKEN has spent, including every request made by anything else
holding it, which we cannot see and cannot count. `errorCode 17` (undocumented:
"Daily request limit of 100 exceeded") sets it, latched until local midnight and
exempt from nothing. This is not hypothetical — it is what arrived at a spend of
six while khqr.cc shared the credential.

#### Superadmin → Payment Settings outranks .env for the whole operating config, not just the payout identity

`App\Services\Bakong\BakongRuntimeConfig` pushes any non-null column on
`platform_payment_settings` over `config('bakong.…')` in `boot()`, so the page
and the env vars above are two ways to set the *same* values, not two separate
ones — and the page wins. It overrides by rewriting `config()` itself rather
than adding a resolver: there are 52 `config('bakong.…')` read sites across
services, the quota ledger, commands and views, and a resolver would be 52
chances to miss one — the one missed being a gate enforcing a stale limit while
the page showed the new one, the exact "two places disagree" failure this
integration has already shipped twice.

- **A `null` column means "not set here, read `.env`"**, never false/zero —
  which is what lets this land on an installation whose settings row predates
  these columns, and lets CI and a fresh install work before anyone opens the
  page. `bakong_enabled` is therefore a **nullable boolean**, not a flag
  defaulting to false (a false default would switch payments off for every
  existing install the moment the migration ran).
- **`BakongRuntimeConfig::overridableKeys()`** is asserted against
  `config/bakong.php` in `BakongSettingsOverrideTest` — a config key added
  later without a form field fails the test, instead of being discovered by an
  operator who cannot change it.
- **Not overridable, on purpose, three different reasons:** `base_url` (the
  host this app POSTs the bearer token to — a form that can repoint it turns a
  borrowed superadmin session into credential theft), the demo switches (a
  "mark it paid" control has no business on a production settings screen), and
  the HTTP timeouts / unbuilt deeplink block (plumbing, not an operator
  decision).

**The access token itself is pasteable on the same page too** — the one
CREDENTIAL among fields that are otherwise identity (account id, merchant name,
city — all printed inside every QR a customer scans). What makes exposing it
acceptable is its shape, not its presence: `type=password`, never pre-filled
(`value=""` always), added to `bootstrap/app.php`'s `dontFlash()` (a failed
validation elsewhere on the form must not carry a bearer credential into the
session via `old()`), encrypted at rest, blank-means-keep-the-stored-token, and
shown back only as fingerprint + expiry, never the value. It is imported
through `BakongTokenService::importToken()` — the same offline path
`bakong:token import` uses — run **after** the settings save and a fresh
`BakongRuntimeConfig::apply()`, because `importToken()` stamps the row with
`config('bakong.integrator.email')`: importing before the save would key the
token to the *old* address and then look it up by the new one, a token that
exists and can never be found. Audited as `bakong.token.imported` with the
fingerprint only.

`tests/Feature/SuperAdmin/BakongSettingsOverrideTest.php` and
`tests/Feature/SuperAdmin/BakongTokenFieldTest.php` pin both halves.

#### `request_token` is dead on the live API — `import` is the real first step

NBC's Open API document (v1.0.2) describes `request_token` alongside
`renew_token`, which is why `bakong:token request` exists — but the endpoint
404s on the live host, and the request is metered by the *attempt*:
`bakong_api_calls` records it before the 404 arrives, so a better error message
would be an explanation delivered one request too late. The document is stale;
the API is the authority. A first token instead comes from NBC's web portal
(`/register`) and arrives by email — `bakong:token import` is that path, and it
is **offline and costs nothing**. `renew` takes over from there for the ~90-day
cycle. `request` stays in the CLI (a documented endpoint dark today may be lit
for a particular account, or restored), but its `confirmSpend()` now defaults
its prompt to **no** — pressing return is not a decision, and a refused Bakong
request is metered exactly like a successful one — and both the command's own
warning and the allowance panel's "no token" remedy point at `import` and the
portal, never at `request`.

**`BAKONG_EMAIL` is two things wearing one name**, and a typo in it is invisible
everywhere this app looks: locally it's a lookup key (`store()` stamps it on the
token row, `current()` reads back by that same string, so the two always agree
with each other); upstream it's the entire payload of `renew_token`. Payments
mint, verify and settle, and `bakong:token status` reports usable, right up
until renewal fails ~90 days later with no visible connection to a letter
mistyped once and copied between hosts. `bakong:token status` now reads the
email back out of the JWT itself (a claim read costs nothing, the same way
expiry already was) and reports a mismatch by naming both spellings in full,
never a diff — the failure being caught is exactly the kind the eye slides
over. A token carrying no email claim reports that, not a mismatch: a check
that cries wolf is how the real one gets ignored. It is a report, not a
correction — which spelling NBC actually holds is their fact, not this app's to
derive.

#### A refusal is not a verdict — `verifyOutcome()`, not `verify()`

`verify()` returns bool, and a `false` read as *"the payer has not paid"* is a
guess of the expensive kind: the money may already have landed, and a row expired
on that guess is a payment written out of the books with no way back. Over-limit
makes the refusal the *normal* answer rather than the rare one, which is how a
quota problem becomes a money problem.

`BakongTransactionService::verifyOutcome()` has three results — `VERIFY_PAID`,
`VERIFY_UNPAID`, `VERIFY_REFUSED`. **Only a 2xx from Bakong can say unpaid.**
Anything acting on a *negative* — expiring a row, giving up on it — must use it:

- `pollAndAdvance()` never expires on a refusal, and sets `lastPollRefused()`,
  which the poll endpoints return as `gateway_error` so the page warns beside the
  spinner instead of spinning in silence. It also reports `gateway_answered`,
  because a poll the **cooldown absorbed is not evidence the gateway is healthy** —
  treating it as such is what kept the stall warning from ever appearing.
- `ReconcileBakongPayments` skips both finalize **and** expire on a refusal.
- A **legacy khqr.cc row** reports `gateway_error: true` / `gateway_answered:
  false` and is never polled at all. Bakong has never heard of that transaction
  and would answer "could not be found" — which reads as UNPAID, which expires
  the QR. Settling one is a human job (SuperAdmin → Accounts → change plan, the
  sanctioned out-of-band path).

#### The superadmin sees the allowance, and looking costs nothing

`App\Services\Bakong\BakongUsageReport` is the one report behind **Superadmin →
Payment Settings** (`<x-bakong-usage>`, refreshed every 30s from
`superadmin.settings.payment.usage`) and `php artisan bakong:usage`, so a browser
and an SSH session cannot read different numbers off the same day.

**It is entirely offline and must stay so** — the moment anyone opens it is the
moment the allowance is under pressure, and a report that spent what it reports
on would be worse than none. That is also why the meter can poll every 30
seconds where the KHQRPay diagnostics popup had to be click-to-run: each refresh
there was two metered requests.

Design notes worth keeping:
- The **bar is our ceiling**; `upstreamExhausted` is called out **above** it, in
  NBC's own words. Folding them together would let the page read "47 / 80, plenty
  left" on a day that is over.
- **`checkouts_left`** translates the remainder into the unit an operator
  actually thinks in — "74 requests left" answers nothing about whether today's
  signups will go through. It is **hidden while exhausted**, or the page
  contradicts itself at the worst moment.
- The **7-day bars** exist because one day's number cannot tell a busy Tuesday
  from a leak; a flat line near the ceiling with nobody signing up is the shape
  the khqr.cc drain made.
- The **token's expiry is a second clock** (read out of the JWT, never asked for)
  and belongs on the same page because both stop payments dead.

`tests/Feature/SuperAdmin/PlatformPaymentSettingsTest.php` pins it, including
`Http::assertNothingSent()` on both the page and the JSON endpoint.

### Signup takes over the row it matches — so only never-activated rows qualify

`provisionOwner()` reuses an existing owner row on the same phone (new password,
`status` reset to `inactive`) rather than stacking a duplicate, which
`users_phone_unique` would reject anyway. That is right for an abandoned signup
and catastrophic for a real account: it resets the customer's login and, once
the payment finalizes, hands the payer that account's data.

**`subscriptions.started_at` is the line.** Both `finalizeSubscription()` and
`startTrial()` stamp it, so it means "this account was ever activated" — paid or
trialed. The phone-uniqueness rule treats such an owner as **taken even once the
subscription lapses**, and `provisionOwner()` re-checks it as defence in depth
against a stale form. Until 2026-08 the rule only looked at *live* subscriptions,
so any expired customer's phone was free to re-register against.

A lapsed owner never needs to re-register: `ExpireSubscriptions` only flips
`subscriptions.status` and never touches `users.status`, so they still sign in
(`LoginRequest` gates on `users.status`) and renew on the billing page — which
`EnsureSubscriptionActive` exempts precisely so there is no lockout loop.

`tests/Feature/Subscription/SignupPhoneTakeoverTest.php` pins it.

---

## Fiscal period pattern

- Admin must have an open `FiscalPeriods` row before accessing any financial routes gated by `fiscal.period`.
- **Supervisor writes land in the admin's books** — a supervisor doesn't own fiscal periods; they use the admin's open period.
- **A period is owned by the account, not the user who opened it.** `fiscal_periods.user_id` is the account owner's id, so the admin-side `fiscalPeriodsQuery()`/`ledgerUserId()` return `current_account_id()`, never `Auth::id()` — see "An account can have more than one admin".
- `HasFiscalPeriodScope` trait (in controller Concerns) provides shared helpers: `getActiveFiscalPeriod()`, `resolveActivePeriod()`, `getAllFiscalPeriods()`, `buildPeriodMonths()`, `getFilteredDateRange()`. Controllers implement two abstract methods: `fiscalPeriodsQuery()` (which periods are visible) and `ledgerUserId()` (which user's ledger rows to read/write).

---

## The month being worked in is remembered — `working_month()`

Every business screen is month-navigated, but the month used to live only in
the URL. Stepping back to July on the rent collection page and then following a
sidebar link — which carries no `?month=` — dropped the user back on the current
month, so a month's collection work had to be re-navigated page by page.

`App\Services\Period\WorkingMonthContext` (session-backed, a request singleton
like `PropertyContext`) holds the month the user last navigated to;
`SetWorkingMonth` middleware records it from any `?month=&year=` on a **GET**,
and `working_month()` reads it back as a Carbon (first of the month).

- **It is only a DEFAULT, never an override.** An explicit `?month=` in the URL
  always wins, and the fiscal period still clamps what may be shown — the
  period checks in `HasDashboardMonthNavigation::resolveSelectedMonth()` and
  `getFilteredDateRange()` are untouched.
- **Nothing remembered returns `null`, not `now()`.** Each caller keeps its own
  default (`working_month() ?: now()` for the month-defaulting pages; the whole
  fiscal period for the income statement), so a session that has navigated
  nowhere behaves exactly as it did before. That null is the
  backward-compatibility seam.
- **A "go to current month" link must state the month.** A bare route link now
  inherits the working month, so those buttons pass `now()->month/year`
  explicitly (`record_income`, `monthly_calendar` — the others already did), and
  the income statement's whole-period view moved to the explicit `?month=all`,
  which the middleware ignores rather than treating as a selection.
- **GET only.** A POST carries `billing_month`/`billing_year` — the month a
  payment settles, a different question (see "Both sides of a checkout settle
  the *billed* month") — and must never move the user's view.
- Call sites: `Shared\RevenueExpenseController` (`index`, `recordIncome`,
  `recordExpense`, `breakEvenPoint`, `monthlyCalendar`, `incomeStatement`,
  `printTenantBill`) and both dashboards via the trait. **`generateMonthlyBills`
  deliberately stays on `now()`** — that page displays no month at all, so
  billing a remembered month from it would be invisible.
- Business-expense entry still defaults its date to **today**, not the viewed
  month: `business_expenses.transaction_date` is a real-world fact, and a past
  month may be closed (`NotInClosedMonth`).

`tests/Feature/RevenueExpense/WorkingMonthNavigationTest.php` pins all of it.

---

## Rent collection day

An account can nominate one day of the month (`settings('billing_cycle_day')`,
1–28) on which every tenant's rent falls due. Two rules, and that is the whole
feature — there is **no invoice table, no scheduler, no per-lease column**. Rent
owed stays derived from the calendar, as it always has been here.

- **First bill** = move-in date → collection day of the **following** month,
  prorated. **Every bill after** = collection day → collection day at the full
  rent. `$300/mo`, moved in Aug 8, day 2 → Aug: `$241.94` (25 days), Sep: `$300`.
- Daily rate = **monthly rent ÷ days in the month the period starts in**, so a
  full cycle comes out at exactly one month's rent in any month length. Full
  cycles take the rent verbatim (no division) so no rounding drift accumulates.
- The 1–28 cap is *why* February, 30/31-day months and leap years need no
  special-casing. Don't widen it.
- Rule 1 anchoring on the **following** month is what guarantees exactly one
  period starts per calendar month — that is what lets the month-navigated rent
  collection page keep working unchanged. Don't "fix" it to the same month.
- Blank setting → `periodFor()` returns `null` and every caller keeps its
  original move-in-day behaviour. That null is the backward-compatibility seam.
- `settings('billing_overdue_days')` (default 3) is grace before rent counts
  late. It drives the overdue badge, the late-fee day count, **and** ប្រការ៥ of
  the contract PDF — previously hard-coded to ០៣ថ្ងៃ there.
- Services: `app/Services/Billing/` — `ProrationCalculator` (pure),
  `BillingCycleService` (reads settings, derives the period), `BillingPeriod` (VO).
- Call sites — **every** place that says what a month owes, or they disagree
  with each other: `Shared\RevenueExpenseController::recordIncome()` (rent due,
  due date, late fee), `Tenants::paymentHistory()` (arrears),
  `MonthClosePreflight` (the pre-close shortfall), `TenantRentProgressCalculator`
  (the tenant-index badge, both panels),
  `DashboardStatsService::countRentPaymentStatus()` (the dashboard's
  paid/pending/overdue tiles), `ContractGenerator` (ប្រការ៤ due day,
  ប្រការ៥ grace), `printReceipt()` (the rent line) and `printTenantBill()` (the
  rent line and the due date). Four of them read `rentals.rent_amount` raw:
  the tenant badge and the contract until 2026-08, which reported a phantom
  shortfall on every fully-paid prorated move-in month, the **printable
  bill** until 2026-09 — the one document that gets handed to the tenant, which
  asked a prorated move-in month for a full month's rent, dated it from the
  move-in day rather than the collection day, and headed it `now()` so stepping
  the page back a month printed July's bill under August's name — and the
  **dashboard tiles** until 2026-09, which also ignored the grace period (see
  the bucket rules below).
- **Which month it is is the same question**, so `Rentals::stayProgress()`
  derives its cycle from `periodFor()` too. It is the **one** implementation of
  the rental-month cycle, feeding the floor-plan gauge (`x-stay-gauge`), the
  "Progress / days left" bar on both tenant index pages, and the rental-month
  column on the floors list and the supervisor apartments list. Those last two
  inlined their own copy in Blade off `tenants.move_in_date` until 2026-08 —
  don't reintroduce one. All of them anchored on each tenant's own move-in
  anniversary until then: under a collection day of the 1st, five tenants read
  five different renewal dates and an arc that restarted mid-month, so a tenant
  who moved in on the 22nd showed 6% and "29 days left" on the 24th while his
  August rent was 74% through and due in 8 days. Two traps if you touch it:
  `periodFor()` returns the period that **starts** in the month asked for, so
  on any day before the collection day you must step back a month or the cycle
  start is in the future; and **every figure it returns is about the current
  rental month only** — `cycle_label` is the day within that month ("24/31",
  collection day = day 1, capped at the cycle's length), so it resets with the
  arc every cycle. It is deliberately neither time lived (`stay_label` /
  `months_stayed`, removed 2026-08 — nobody collecting rent has a use for days
  lived) nor the tenancy's running total (`months_billed`, "3 mo", removed
  right after: a cumulative count in a monthly gauge reads as a tenure counter,
  which is a different question from how far through *this* month the tenant
  is). Keys are `cycle_label`/`cycle_day`/`cycle_days`.
- `tests/Feature/Billing/RentCollectionDayTest.php` pins both rules *and* the
  no-collection-day backward-compatibility contract;
  `tests/Feature/Billing/StayProgressCycleTest.php` pins the gauge cycle.

---

## A month that has ended must be closed — one is a nudge, two is a stop

Closing a month is what turns its figures into books: `MonthlyPeriodManager::closeMonth()`
freezes the totals and carries the closing balance into the next month. Until
that happens the month's net income is a live sum every later entry keeps
moving, nothing is carried forward, and the guard rails that key off a closed
month (`NotInClosedMonth`, `PaymentReversalService`) have nothing to bite on. So
the app asks for the close, and past one month of backlog it insists.

`App\Services\FiscalPeriod\MonthCloseBacklog` is the single answer to "how far
behind is this account?", read by both the banner and the gate so they can never
disagree.

- **A month is due to close once it has ended** — `end_date` before today, still
  `open`, inside a still-open fiscal period. **The month in progress is never
  due**: there is nothing to freeze yet. That is what paces the whole feature —
  exactly one month becomes due on the 1st, so the account has a full month to
  clear it before a second joins it.
- **`ALLOWED_OPEN_MONTHS = 1`.** One due month → an amber, dismissible dashboard
  banner. Two or more → a red, **non-dismissible** banner and
  `EnsureMonthCloseBacklogClear` (alias `month.close`, beside `fiscal.period` on
  both panels' revenue-expense groups) refuses new money. Adjacency is **not**
  required — closing out of order is allowed, so the condition is "two months
  are unclosed", not "two consecutive months".
- **The gate is narrower than `fiscal.period` in three deliberate ways**, and
  each one is load-bearing:
  - **Writes only** — a safe request passes. Reading the books is how the
    operator works out what the month owes before closing it, and the banner has
    already said why. Blocking the reports punishes the wrong half of the
    workflow.
  - **`*.revenue_expense.reverse_payment` is exempt.** Undoing a mistake is how a
    month gets *ready* to close, and a reversal is only reachable while its month
    is open. Without the exemption the rule deadlocks itself: fix July before it
    closes, but no writes until July closes.
  - **Superadmin is exempt**, as everywhere else.
- **Only an admin can close a month**, so `close_url` is null for a supervisor
  and their banner says "ask the owner" instead of offering a button that would
  403 — the same split `EnsureFiscalPeriodExists` and `EnsureSubscriptionActive`
  already make. It reads the **user's role, not the panel**, so an admin
  previewing the supervisor dashboard still gets the link. A supervisor's writes
  land in these books too, so they are blocked as well, bounced `back()` rather
  than to a page they cannot use.
- **An account with no `MonthlyPeriod` rows has no backlog and sees nothing.**
  The rows are minted when a fiscal period is opened, so that null is the
  backward-compatibility seam for books that predate them — the same shape as
  the rent-collection day's null.
- `<x-month-close-alert>` renders both states from one component on both
  dashboards; the red one drops the dismiss button on purpose, since the banner
  is the only place that explains why the next save fails.
- **The banner links to a page that must actually offer the close**, and twice
  it did not. The close control was **icon-only** everywhere (a padlock with a
  `title`, beside the print/back/eye glyphs, and no tooltip at all on a phone) —
  it is labelled now on the month page (`close_month_now`, the page's primary
  action) and on both the card and table rows of the period page. And
  `resolveScope()` read "consolidated" as `showingAll || hasSingleProperty()`,
  so an account with **zero** Property rows fell through to false and never saw
  the button at all; the line is `hasNothingToConsolidate()` (**fewer than
  two**) now, matching where the rest of `PropertyContext` already draws it.
- **One property of several selected still hides the close, on purpose** — the
  close freezes account-wide totals while that view shows a per-property running
  total (`monthBalances`). `<x-month-close-scope-notice>` says so and posts
  `property.switch` (which redirects back), instead of leaving a page that the
  banner sent the user to with nothing on it.

`tests/Feature/FiscalPeriod/MonthCloseBacklogTest.php` pins all of it.

---

## `rentals` is the system of record for money — edits must reach it

Nothing about rent is stored as an invoice. Every money figure is **derived from
the `rentals` row**: prorated rent (`BillingCycleService`), rent due and the
overdue badge (`RevenueExpenseQueryService`, `Shared\RevenueExpenseController`),
arrears (`TenantRentProgressCalculator`), the move-out settlement
(`TenantLeaveCalculator`), and ប្រការ១/៤ of the contract (`ContractGenerator`) —
all read `rentals.start_date`, `rentals.rent_amount`, `rentals.payment_due_day`.

The edit forms write **other** tables: the tenant edit page writes `tenants`
(`move_in_date`, `deposit`), the room edit page writes `apartments`
(`monthly_rent`). Without a sync step the profile shows the corrected figure
while billing keeps charging the old one.

`App\Services\Tenants\LeaseSyncService` is that step. Call it from any flow that
edits lease-relevant details:

- `syncFromTenantEdit($tenant)` — after `$tenant->update()`, **inside the same
  transaction**. Copies `move_in_date` → `start_date` + `payment_due_day` (which
  has no form field of its own and has always been the move-in day), copies
  `deposit`, and corrects the `deposit:rental:{id}` income row.
- `repriceActiveLeases($apartment, $rent)` — after a room reprice, so the sitting
  tenant's next bill uses the new price.

Two rules the service enforces, and they are the point of it:

- **Only the current lease follows an edit.** An ended tenancy is booked history
  — its dates and rent are what was actually charged.
- **Closed fiscal periods are never restated** — the deposit ledger row is
  corrected only while its period is still open, and it is *update-only*: an
  edit corrects income that check-in booked, it never books new income.

Repricing an occupied room moves the **whole** current month (rent is derived,
not invoiced) — there is no month-specific rent without an invoice table.
The stored contract PDF is *not* auto-regenerated; that stays the admin's
explicit "Regenerate" action on the tenant page.

`tests/Feature/Billing/TenantDetailEditSyncTest.php` pins all of this for both
panels.

---

## A bill has two sides — rent and charges settle on separate visits

Tenants pay rent before the month ends; the meters are read and the
utility/other charges are collected at the turn of the month. So a bill row on
the rent collection page carries **two independent statuses**, derived per
request in `Shared\RevenueExpenseController::recordIncome()` — there is still no
invoice table.

- `rent_status` — paid / pending / overdue / upcoming. Driven by a `payment_type
  = 'rent'` Payments row and the collection-day due date.
- `charges_status` — `none` / `pending` / `paid`, from `utilities.paid_status`.
  **`none` ≠ `paid`**: it means the meters haven't been read yet, so nothing is
  owed *and* the month isn't finished.
- `status` (the filter bucket, the row tint and the floor dots) folds them into
  **one of three buckets — `paid` / `pending` / `overdue`, and nothing else.**
  `paid` requires **both** sides settled; rent in with charges outstanding is
  not a fourth state, it is simply pending. A `none` charges side is unsettled
  in a **running** month (meters not read) and settles in any other month:
  nothing was ever billed, so nothing is owed — accounts whose rent is
  utilities-inclusive never write a charge row and their closed months must not
  sit in `pending` forever. `has_outstanding` is unchanged and stays the
  authority for the checkout button: a pending row with unread meters has
  nothing collectable yet.
- **The row prints one badge** — `<x-bill-status>`
  (`components/bill-status.blade.php`, `compact` for the mobile card). It reads
  **Pending · Overdue · Rent Paid · Paid**, where **"Rent Paid" is a label on
  the pending bucket**, not a bucket: rent is in, the charges side is not
  settled yet (`charges_settled = false`), and `status` stays `pending`. Which
  side is open survives only as the `title` tooltip ("Rent paid · charges due" /
  "· meters not read yet"), because a second badge competing with the status is
  exactly what was removed. The controller passes **`charges_settled`** — the
  component cannot re-derive it, since a `none` charges side settles in a closed
  month and not in a running one. `paidCount` means *fully settled* — the "N
  tenants paid" line under the Collected tile excludes a rent-paid tenant whose
  meters are still unread.
- **Every figure on the page is about the month on screen — narrow the eager
  set before summing it.** `recordIncome()` loads a rental's payments across the
  whole fiscal period *or* the selected month on purpose: the period arm is the
  fallback that guarantees the month's own payments load even under a stale or
  short `closing_date`. That makes the loaded set wider than the page, so
  `collected` / `late_fees` / `payment_count` and the **Collected** tile all
  filter it back to `paid_at` in the viewed month, exactly as `paid_this_month`
  and the row's receipt link already did. Summing it raw (until 2026-09) put the
  period-to-date total beside a month-scoped Pending: a $500 room three months
  in read $1,500 collected against $500 expected, growing by a month's rent
  every month.
- **"Delete all unpaid" clears one month.** The charges modal lists a single
  month's rows and is opened from a single month's row, so
  `clearTenantCharges()` takes the month and `forMonth()`s the query. It dropped
  every unpaid charge the rental had ever carried until 2026-09, so tidying a
  mistake in September silently wiped the August arrears
  `Tenants::outstandingCharges()` was still owed.

### Three buckets, everywhere — paid / pending / overdue

`paid`, `pending`, `overdue` is the whole payment-status **bucket** vocabulary
of the app — what every filter chip, floor dot, tile and count uses. Do not
introduce a fourth bucket (`partial`, `paying`, `unpaid`, `not billed`) in any
view: they were consolidated in 2026-08 precisely because the same tenant read
differently on each screen. `upcoming` is *not* a fourth payment state — it
marks a tenancy that has not begun, so nothing is owed yet. **"Rent Paid" is a
badge label over the pending bucket, never a bucket** — added 2026-08 so the
collector can see at a glance which pending rows only owe charges; nothing
counts it separately.

Every screen that states rent status derives it the same way and must keep
agreeing:

- `Shared\RevenueExpenseController::recordIncome()` — the rent collection page
  (filter chips, floor dots, row tint, `<x-bill-status>`).
- `DashboardStatsService::countRentPaymentStatus()` — the admin/supervisor
  dashboard's Paid/Pending/Overdue tiles. Each tile **links to the matching
  filter chip on the collection page**, so it is charges-aware too: counting
  rent alone made the tile disagree with the page it opens. Which is also why
  the due date and the rent owed come from `BillingCycleService` and
  `settings('billing_overdue_days')` and not from a second derivation here. It
  re-derived both until 2026-09 — rent due on each tenant's own move-in day,
  with no grace at all — so an account with a collection day set had a tile and
  a chip that disagreed in **both** directions: a tenant inside the grace
  period read Overdue on the dashboard and Pending on the page, and one past a
  collection day earlier than their move-in day read Pending on the dashboard
  while the page called them overdue. A prorated move-in month also went into
  `total_pending` at the full month's rent.
  **`bills_total` is the page's row count**, so an "upcoming" row counts too:
  an empty room whose next tenancy begins later gets a pending row on the page,
  and the tile counts it while adding nothing to `total_pending` — nothing is
  owed for a month the tenancy never touched.
  `tests/Feature/Dashboard/DashboardTileParityTest.php` pins the tiles against
  the page they open, scenario by scenario, rather than against hard-coded
  numbers.
- `TenantRentProgressCalculator` — the tenant-index badge in both panels, and
  the `?rent_status=paid|pending|overdue` filter in both TenantControllers.
  Its `status` is **rent-only by design** (the badge sits beside a rent progress
  bar) and stays the bucket the filter and the floor dots count. It also carries
  `charges_status`/`charges_settled` for the current month so both panels render
  the *same* `<x-bill-status>` the collection page does — "Rent Paid" until the
  charges settle. Both tenant index pages print that component; don't re-inline
  the badge markup. The page only ever shows the running month, so a `none`
  charges side never settles there.
- `Tenant\DashboardController` — `this_month_status` on the tenant's own
  dashboard.

Anything short of settled is `pending`; "how far along" belongs to the
percentage/progress bar next to the badge, not to a bucket of its own.

### A room counts once per month — never count rentals

A room is single-occupancy, so it yields exactly **one bill, and one unit of
occupancy, per month**. During turnover the outgoing and incoming tenancies
*overlap*: `leave_date` may be any day of the month and the room is freed for
reassignment the moment the leave is processed. Counting `rentals` rows in a
month window therefore double-counts every turnover room. Each of these picks
the **newest tenancy that had begun by month end** (else the earliest future
one, so an empty room awaiting its next tenant still shows) and must keep doing
so:

- `Shared\RevenueExpenseController::recordIncome()` — one bill row per room.
- `DashboardStatsService::countRentPaymentStatus()` — the paid/pending/overdue
  tiles and `bills_total` (the tile read 29 of 28 before).
- `BreakEvenService::monthOccupants()` — `current_occupancy` ("rented X of Y"),
  `avg_rent_per_apartment` and the health trend's `occupancy_pct`. This one
  counted rentals until 2026-08 and reported 6 rooms rented out of 5 in a
  turnover month while the dashboard said 5. `activeRentalsQuery()` is the raw
  overlapping-rentals query it wraps — don't count rooms with it directly.

`tests/Feature/RevenueExpense/BreakEvenOccupancyTest.php` pins break-even
against the dashboard.

Three rules this depends on:

- **Never gate "Add charge" on `status`** — gate it on the two sides. A row with
  `rent_status = paid` and `charges_status = none` is exactly the row that still
  needs its meter reading entered; hiding the button there is what made the
  workflow impossible. The button is
  hidden only when **both** sides are settled (`rent_status = paid` **and**
  `charges_status = paid`) — that month is finished, so there is nothing left to
  bill on it.
- **…and an `Upcoming` row offers it at all.** `billable`
  (`! is_upcoming && ! isFutureMonth`) is the second gate, and it is the pair
  `<x-bill-status>` already folds into that badge: the tenancy has not begun by
  month end, or the whole month is still ahead. Either way no meter has been
  read and nothing has been incurred, so the badge and the affordance have to
  agree — gating on `is_upcoming` alone leaves every row of a future month
  labelled Upcoming with a live **+** button.
  `addTenantCharge()` refuses the same two cases server-side
  (`upcomingChargeFault()`), since the modal posts the month it was opened on
  and a stale tab is the way in. Checkout was already gated —
  `has_outstanding` is false for a not-yet-started tenancy.
  `tests/Feature/RevenueExpense/UpcomingBillNotChargeableTest.php` pins it.
- **Quote checkout the unpaid totals** (`unpaid_utility_only`,
  `unpaid_other_charges`), never the gross ones. `settleUtilitiesForMonth()`
  only settles unpaid rows, so a second visit shown gross figures re-quotes
  money the first visit already took. The modal drops the rent line entirely
  once `rent_status = paid`, for the same reason.
- **The modal shows one card per side, and only the collectable side is a
  card.** `openCheckout()` pre-ticks the outstanding side from
  `rent_status`/`charges_status`, so the mid-month visit offers rent alone and
  the end-of-month visit offers charges alone — the collector never unticks a
  line. A **settled** side collapses to a one-line receipt
  (`rent_paid_already` / `charges_paid_already`), not a disabled checkbox: a
  disabled checkbox posts nothing anyway, so it was only competing for
  attention with the live line. `charges_status = none` prints
  `no_charges_yet`, which is what makes the second visit expected rather than a
  surprise. **The charges side is only a card once the rent is in**
  (`chargesStatus === 'pending' && rentAlreadyPaid`, and `payUtilities` is
  pre-ticked on the same condition): the bill run raises a month's charges
  before anyone comes to collect, so with an unpaid rent both sides were live
  on the first visit — the collector took rent + charges in one go and the
  charges visit the workflow is built around had nothing left to settle.
  Charges raised while the rent is outstanding print as a dashed, read-only
  line (`charges_after_rent`) so the next visit's figure is visible without
  this visit quoting it. Itemisation (rent + room costs, each charge by name) is behind a
  Details disclosure, the period/due pair lives in the header subtitle, and the
  late-fee **input only exists when there is a late fee** — otherwise it is an
  "+ Add late fee" link. Submit is disabled while neither side is ticked. None
  of this changes what is posted: `pay_rent`, `pay_utilities`, `late_fee`,
  `payment_method`, `payment_date`, `billing_month`/`billing_year`.
- **Each visit is its own payment, method included.** One `checkout()` call
  writes one `Payments` row per side it settles, and nothing reads the other
  visit's row — so the rent can come in cash on the 25th and the charges by bank
  or KHQR on the 2nd, and the two rows keep their own method, type, anchor date
  and ledger rows. The `payment_method` radio is per submission, not per bill;
  the bill summary prints `paymentMethod = null` for exactly this reason (a
  month has as many methods as it had visits), and the row's receipt button
  opens the summary rather than a receipt once the month holds more than one.
- **The late fee is a rent-side line, so it only exists on the rent visit.**
  `checkout()` books it on the rent `Payments` row and `khqrGenerate()` only
  adds it to the QR when `pay_rent` is set — it is percent-of-rent per day past
  the grace period (`late_fee_suggested`), which is why it has no meaning once
  the rent is already in. The input, its hint, the "+ Add late fee" link and
  `calculateCheckoutTotal()` are all gated on `payRent`; until 2026-09 the total
  added it unconditionally, so a charges-only visit quoted $52.50 on screen and
  booked $42.50 — money read out to the tenant that the app never collected.
- **Pending is tracked per side** (`totalPendingRent` + `totalPendingCharges`).
  One all-or-nothing test — the old behaviour — dropped a rent-paid tenant's
  unpaid charges out of the tile entirely, which under this workflow is every
  tenant every month. An `ApartmentFixedExpense` is **not** part of either
  figure — see the rule below.
- **A fixed room cost is a template, not a charge.** `apartment_fixed_expenses`
  rows are the instruction that raises a charge; `MonthlyBillingService` (or the
  Add-Charge modal by hand) turns one into a `Utilities` row, and only that row
  can be quoted, settled, receipted or reversed. Every other place that says
  what a tenant owes already reads it this way — `Tenants::outstandingCharges()`,
  `paymentHistory()` and the move-out settlement count utilities rows and ignore
  templates. The rent collection page was the one screen that didn't, and it was
  wrong in both directions: an **un-raised** template went into `total_bill`, the
  Pending tile and the checkout modal's "Total to collect", while `checkout()`
  posts `rent_amount` alone — so the modal asked a $500 room with a $25 template
  for $525 and booked $500, every month, uncollected. And once the bill run
  **had** raised it, the template printed *beside* the charge row it created:
  $550 quoted on a $525 bill, on the row total, in the modal and on the printed
  bill. So: `fixedExpensesFor()` drops any template whose type the month has
  already billed (the same shape as the vehicle-parking supersession it already
  did), and what survives is a **preview** — shown as "Room costs — not billed
  yet" on the collection page and in the charges modal, and absent from
  `total_bill`, both Pending figures, the checkout total, the bill summary and
  the printable bill. Raising the charge is what puts it on the bill.

`checkout()`'s `pay_rent` / `pay_utilities` flags were always independent — it
was the status and totals layer that assumed one payment.
`tests/Feature/RevenueExpense/SplitRentChargesStatusTest.php` pins the two sides;
`tests/Feature/RevenueExpense/RecordIncomeFiguresTest.php` pins the money the
page states against the money checkout books;
`tests/Feature/RevenueExpense/SequentialCheckoutTest.php` pins the two visits end
to end — a cash rent visit then a bank/KHQR charges visit, each its own payment.

### A mistaken payment is reversed, not corrected in place

Because every status is derived, undoing a payment is the whole correction:
drop the `Payments` row, drop the `Accounts` rows it booked, put the charge rows
it settled back to unpaid — and the statuses walk backwards on their own.
Reversing the charges payment takes a **Paid** bill to **Rent Paid** (still the
pending bucket); reversing the rent payment takes it to **pending/overdue**.
`App\Services\RevenueExpense\PaymentReversalService` is the only path;
`Shared\RevenueExpenseController::reversePayment()` (`…revenue_expense.reverse_payment`,
DELETE) is its one caller, driven by the undo button on each recorded payment in
the **payment-history modal** of the tenant detail page (`<x-reverse-payment>`).

- **Closing the month is the deadline, and nothing else is.** A payment stays
  reversible for as long as the month it was booked in is open — the calendar
  rolling over changes nothing. Closing a month is what freezes it
  (`closeMonth()` writes the totals and forwards the closing balance), so that
  is the point past which a reversal would restate reported money. The window
  is the ledger row's `transaction_date` — when the money was *booked*, not the
  month it billed — so July's rent collected on Aug 3 is governed by **August's**
  close. Until 2026-09 there was also a hard current-calendar-month rule on top,
  which contradicted the month status it sat beside: an account that had not
  closed July yet still could not fix July's mis-keyed rent on Aug 1, and was
  told to book an adjustment against a month it had every right to correct.
  Don't reintroduce it — `MonthlyPeriod`, not `now()`, is the authority for
  whether a month has been acted on.
- **Closed money is never restated.** A payment whose ledger rows sit in a
  closed (or locked) fiscal period or `MonthlyPeriod` is refused — reopen the
  month first (`MonthlyPeriodManager::reopenMonth()`). Same rule as
  `LeaseSyncService`'s deposit row, and the same definition `NotInClosedMonth`
  enforces on entry. The closed-*period* reason is checked first so the flash
  doesn't advise reopening a month that reopening wouldn't help. A month with no
  `MonthlyPeriod` row counts as open, and a payment that booked no ledger rows
  at all is placed by its own `paid_at` rather than waved through. (The route
  also sits behind `fiscal.period`, so the closed-*period* case is a
  service-level guard.)
- **The Payments row is soft-deleted, the Accounts rows are deleted outright** —
  income never received must not sit in the books, which is how every other
  ledger-undo path here behaves. `AuditLogger` records `payment.reversed`.
- **A charges payment is matched to its rows by `paid_at`**, the same join
  `printReceipt()` uses (utilities carry no `payment_id`). An empty set is
  legitimate (a hand-recorded utilities payment settled no rows); a non-empty
  set whose total doesn't reconcile means two batches share the timestamp, and
  the reversal is **refused rather than guessed**.
- **A refusal is shown, never hidden** — `<x-reverse-payment-locked>`. Every
  `blockReason()` used to simply remove the undo button, and the two halves of
  one bill settle in *different* months: rent before the month ends, charges
  once the meters are read at the turn of the next one. So closing that month
  blocks the rent (booked inside it) and leaves the charges (booked in the
  still-open month) undoable — the money rule working exactly as written, but
  read at the row it looked like the app disagreeing with itself, with nothing
  naming the reopen that lifts it. The lock states the reason in the same
  `flash_payment_reverse_blocked_*` words the POST would have flashed, and for
  a **closed month** its OK goes to that month's page, where Reopen lives.
  `blockingMonth()` is what lets the UI name the month; only an **admin** gets
  the link (read off the user's role, not the panel — the split
  `MonthCloseBacklog::closeUrlFor()` already makes), and a supervisor is told to
  ask the owner. Don't answer this by loosening the rule: the reopen → reverse →
  re-close path is the sanctioned one, and it was only ever undiscoverable.
- Reversal does **not** refund a KHQR transaction — it corrects the books only.
- **Removing a PAID charge is this same operation, reached from the other end.**
  The charges modal on the rent collection page (the eye icon) offers its remove
  button on paid lines too, and `IncomeRecordingService::removeTenantCharge()`
  answers it by reversing the payment that settled the charge and *then*
  dropping the row — never by deleting the row with the guard taken off, which
  would leave the `Payments` row and its `Accounts` income standing with nothing
  behind them. Consequences to keep in mind before touching it:
  - **The reversal takes the whole payment**, so every other charge in that
    batch goes back to unpaid and must be collected again. Reducing the payment
    to the remaining charges instead was rejected: it restates an amount a
    printed receipt already quotes, and receipts here reprint byte-identical
    forever. The confirm dialog and the success message both say so — the
    operator clicked one line and cannot see the rest of the batch.
  - **The charge finds its payment by `paid_at`**, the same join `printReceipt()`
    and `PaymentReversalService::settledCharges()` use, read backwards
    (`settlingPayment()`). A paid charge with **no** matching payment — the shape
    a move-out settlement leaves, booking income with no `Payments` row — is
    **refused**, not deleted: there is nothing to reverse and no reliable way to
    find its ledger rows, so removing it would strand the income.
  - Every other refusal is `PaymentReversalService`'s verbatim, closed month and
    closed period included. The same refusal must not read differently depending
    on whether it was hit from the tenant page's undo button or from this modal.
  - The route sits behind `fiscal.period` + `month.close` like the rest of the
    group; it is **not** exempt the way `reverse_payment` is.

`tests/Feature/RevenueExpense/PaymentReversalTest.php` pins all of it;
`tests/Feature/RevenueExpense/RemoveTenantChargeTest.php` pins the charge-side
entry point.

### Both sides of a checkout settle the *billed* month

The checkout form's date field defaults to **today**, and rent is collected
late all the time — so the month the money arrives in is routinely not the
month it pays for. `billing_month`/`billing_year` (the month the bill page was
showing) is the authority for both sides:

- **`Payments.paid_at` is anchored in the billed month** (`rentAnchorDate()` —
  the payment date itself when it already falls there, else the end/start of the
  billed month). Every derived rent figure keys off `paid_at`, so it is what
  decides which month goes green. Rent used to key off the payment date alone:
  collecting July's bill on Aug 3 settled July's *charges* but booked the rent
  against August — July stayed overdue forever and August read paid with nothing
  collected. `Accounts.transaction_date` still carries the real payment date;
  income is recognised when received, in the open period. Same split
  `settleOutstandingForTenant()` uses.
- **Rent is idempotent per rental per billed month.** The modal locks the rent
  line once rent is in, but a disabled checkbox isn't posted at all — a
  double-click or stale tab re-posted `pay_rent` and booked it twice. Utilities
  are naturally idempotent (only unpaid rows settle); rent was not. `checkout()`
  returns `rent_already_paid` so the panel says so instead of "no items
  selected". `recordBulkRent()` has carried the same guard since the 2026-07 audit.

`tests/Feature/RevenueExpense/CheckoutBillingMonthTest.php` pins both.

### …so a receipt is per payment, not per month

`Shared\RevenueExpenseController::printReceipt()` renders **two documents off
one route** (`revenue-expense/print-receipt/{rental}?month=&year=`):

- **`?payment={id}` → a RECEIPT for that one payment.** Every figure comes off
  that `Payments` row — amount, late fee, method, reference, note, `paid_at`.
  Two collection visits mean two rows, so each gets its own receipt. The
  receipt number is derived from the payment id, so a reprint is byte-identical
  forever. A receipt states what was received: no balance line, no unpaid items.
- **no `payment` → the month's BILL SUMMARY.** Every line tagged paid/unpaid,
  balance = the unsettled lines. It says "Bill Summary", not "Receipt".

Rules behind it:

- **The rent line comes from `BillingCycleService`, never `rentals.rent_amount`**
  — rent is derived here, so a prorated move-in month must print what was
  actually billed.
- **A receipt lists only what its payment settled.** Utilities carry no
  `payment_id`; `settleUtilityRows()` stamps their `paid_at` from the same date
  as the `Payments` row, and that timestamp is the join. When the rows don't
  reconcile to the payment amount, fall back to one line for the amount taken —
  never print a total that differs from the money received.
- **The late fee is its own line.** It used to count toward "amount paid" while
  the total ignored it, so every late receipt printed short.
- **No fixed-expense lines on either document.** A room's fixed cost is the
  template that raises a charge; by the time it is on a bill it *is* one of the
  `Utilities` rows, and printing the template too billed the tenant twice for
  it. See "A fixed room cost is a template, not a charge".
- The row's receipt button opens the single payment directly when the month has
  one, else the summary — whose picker strip (`.no-print`) links the rest.

`tests/Feature/RevenueExpense/PrintReceiptTest.php` pins all of it;
`SharedPanelViewsTest` renders both modes in both panels.

---

## Tenant vehicles are the parking charge — they are not a third billing lane

A tenant's vehicles live in `tenant_vehicles` (`App\Models\TenantVehicle`,
`BelongsToAccount`): type (car/tuktuk/motorbike), plate, monthly fee. They are
registered, repriced and removed on **Property Management → Vehicles**
(`Shared\VehicleController`), written by `Shared\TenantVehicleController` with
the usual thin Admin/Supervisor subclasses.

The "Vehicles & Parking" card on the tenant detail page
(`partials/tenant-show.blade.php`) is **read-only plus a "Manage vehicles" link**
deep-linked to that tenant's room. It carried a second copy of the add form
until 2026-08 — same controller, duplicated markup, and only the management
page could *edit*, so a typo'd plate had to be deleted and re-created. What is
left on the card is what only it can say: this tenant's vehicles and whether the
parking they imply was actually billed (`parkingState`). Delete stays there —
that is a fact about the tenant, not the building — which is why the write
routes still default to redirecting back to the tenant page when
`redirect_to` is absent. Don't reintroduce a form here.

A vehicle with a fee above zero **is** the tenant's parking charge. It does not
get a billing path of its own: `MonthlyBillingService` sums the tenant's priced
vehicles into the month's single `parking` `Utilities` row, and from there the
money rides the parking lane that already existed — the rent-collection bill
row, checkout, `CAT_OTHER_INCOME`, the receipt, the move-out settlement's
`parking_charge`, `parkingRevenue` on the reports. Adding a lane instead would
have meant restating every one of those.

Rules the design turns on:

- **One room, one parking charge per month** — `(rental, utility_type, month,
  year)` is unique, so two vehicles are one row for their combined fee, never
  two rows. That uniqueness is enforced in code, not by the DB:
  `MonthlyBillingService::bill()` skips an already-billed pair, and
  `IncomeRecordingService::SINGLE_PER_MONTH_TYPES` (`parking`, `internet`,
  `trash`) makes the **hand-entry** path upsert the open row. Operators enter
  the month's charges on the rent collection page *before* taking the payment
  and re-open the Add-Charge modal to correct a figure — that used to
  `create()` a second row and double the charge. Two rules ride along: a
  **paid** row is booked money and is never mutated (a fresh row is raised
  beside it), and **`other` stays additive** — it is the ad-hoc bucket with no
  template, so two unrelated one-offs in a month are legitimate.
  Because a re-save *corrects*, the modal **opens on the month's existing
  charges** — `recordIncome()` ships `$chargeContext`
  (`{rental: {type: {amount, editing, total, count, paid}}}`) and every type
  with a still-open row comes up ticked and prefilled, so the operator edits
  the figure on screen instead of retyping it blind. `editing` is the
  authority there: it is only set for the upserting types, so a **paid** row
  and an **`other`** row report what is recorded without prefilling it (a save
  on either adds a row rather than replacing one) — and a parking row already
  on the bill keeps its chip enabled even with no priced vehicle left to quote
  from, or there would be a charge no one could correct.
  `tests/Feature/RevenueExpense/RecurringChargeUpsertTest.php` pins it.
- **Priced vehicles supersede the room's fixed `parking`
  `ApartmentFixedExpense`** for that rental. Only one of the two could win the
  row anyway; billing both would charge the same spot twice. The tenant card
  says so when both exist. Both bill runs skip the template, and so must every
  page that states what a room costs, or it quotes a parking charge that will
  never be billed on top of the one that will: the two bill views read
  `from_vehicles`, and the rent-collection page, the printable bill and the
  bill summary go through `RevenueExpenseController::fixedExpensesFor()`.
- **A blank fee records the vehicle without billing it** (parking included in
  the rent) — that zero is the backward-compatibility seam, the same shape as
  the rent-collection day's null.
- **The card never writes money.** It says what the *next* bill run will charge
  and whether it already did (`parkingState`: not billed / billed / paid /
  mismatch). Deleting a vehicle leaves billed charges alone — they are owed or
  collected money, not a description of today's vehicle list. A mismatch
  between the vehicle total and the billed figure is **flagged, not
  auto-corrected**.
- The vehicle line on the two bill-generation views is **read-only and carries
  no form inputs** — it has no `apartment_fixed_expenses` id, which
  `ProcessMonthlyBillsRequest` requires. `processSelected()` gates on the
  apartment checkbox alone for the same reason: a rental whose whole bill is
  vehicle parking posts no `expenses` array at all.
- Plates are normalised (upper, trimmed) and unique per account, so the same
  vehicle can't be registered under two tenants and billed twice.
- New account-owned table ⇒ it is deleted in `AccountPurgeService`.

`tests/Feature/Tenants/TenantVehicleTest.php` pins all of it.

### The vehicle management page reads; the tenant card's controller writes

**Property Management → Vehicles** (`Shared\VehicleController`,
`views/shared/vehicles/index.blade.php`, `{panel}.vehicles.index`) lays every
registered vehicle out by floor → room → tenant, with search, a type chip and
per-room add/edit/delete. The floors are collapsible cards, which is the floor
filter — a select beside them was a second way to say the same thing, and a
"with vehicles only" chip hid exactly the rooms where the next vehicle gets
added; both were dropped in 2026-08 along with the `floor`/`only` query params.
It owns the add/edit workflow but is
not a second implementation: its controller only reads, and each of its forms
posts to the **tenant** vehicle routes — `Shared\TenantVehicleController`, the
one write path, which the tenant-detail card still uses for delete — carrying
`redirect_to=vehicles` so the flash lands back on the page that submitted.
Don't grow a write path here.

- **A vehicle belongs to a tenant; the room is derived through them**
  (`TenantVehicle::room()` → `tenant.apartment`). There is deliberately no
  `apartment_id` column: a tenant who changes room takes their vehicles with
  them, so a stored room would be a copy that goes stale — the same reason rent
  is derived rather than invoiced. `FiltersByProperty` on the model follows the
  same path (`tenant.apartment.floor`).
- **That derivation is the verification.** Each form posts the room it was
  *drawn under*; `verifyRoom()` refuses the write when the tenant is no longer
  in it (stale tab after a room move). Vehicles whose derivation comes back
  empty are collected into the page's amber **"Needs attention"** block rather
  than hidden — a departed tenant is soft-deleted, so the FK cascade never
  fires and their vehicles would otherwise be invisible everywhere. The two
  destroy routes are bound `->withTrashed()` precisely so those can be cleared.
  Supervisors don't get that block: a room-less vehicle has no property to match
  against their assignments.
- Deleting a vehicle still leaves billed charges alone, and an **edit only
  restates what the next bill run will charge** — a parking charge already
  raised keeps its figure and the tenant card flags the difference
  (`parkingState` 'mismatch'). Closed money is not restated from here either.
- The plate-uniqueness rule `->ignore($this->route('vehicle'))` so a resubmit
  that leaves the plate alone doesn't collide with itself.
- **The four summary tiles answer two different questions.** Registered, the
  monthly parking fees and the type breakdown come off the *vehicle* rows —
  what the next bill run will charge; **Parking revenue this month** comes off
  the `parking` `Utilities` rows — money actually collected (keyed on `paid_at`,
  the same definition the income statement's parking line uses), with this
  month's unpaid parking as its outstanding sub-line. Don't merge them: a free
  vehicle is registered and bills nothing, and a billed charge outlives the
  vehicle that raised it. All of them describe the property, never the filtered
  view. The type tile is an **inline SVG donut** built in the Blade `@php` block
  (`$donutColors`, arcs as `stroke-dasharray` on one `r=30` ring, total in the
  centre) — three fixed slices don't justify pulling Chart.js onto the page, and
  an inline ring prints with the rest of the card.

- **A new page needs a nav entry in three places, not one.** Phones suppress the
  hamburger (`$useBottomNav` in the admin/supervisor layouts) and replace the
  sidebar with `layouts/bottom-nav` / `layouts/supervisor-bottom-nav`, so a page
  missing from those sheets is unreachable on mobile however well it renders —
  which is what happened to Vehicles until 2026-09. The supervisor Property tab
  is a sheet for that reason; it was a direct link to Apartments with nowhere to
  put a second entry. Add the route to the tab's `$isProperty`-style `routeIs`
  list too, or the tab never lights up on the new page.

`tests/Feature/Tenants/VehicleManagementTest.php` pins the page, both write
verbs and the verification; `SharedPanelViewsTest` renders it as both roles.

---

## Expense categories are account-owned, not a hard-coded list

The categories the record-expense form offers live in `expense_categories`
(`App\Models\ExpenseCategory`, `BelongsToAccount`) and are managed by the owner
at **Settings → Expense Categories** (`Admin\ExpenseCategoryController`,
`views/admin/settings/expense_categories.blade.php`). Admin-only, like every
settings page that writes account-wide config; supervisors record expenses
against the admin's vocabulary but don't manage it.

Two columns carry the design:

- **`key` is immutable** — it is what `business_expenses.category` stores, and
  `incomeStatement()` maps six of them (`electricity`, `water`, `internet`,
  `security`, `tax`, `property_tax`) onto their own statement lines while every
  other key falls into "Other Expenses". It is derived once from the name
  (`makeKey()`), so **renaming a category never restates booked history**.
- **`is_active`** is how a category is retired: hidden from the dropdown, still
  labelling the expenses that reference it. That is why **deleting a category
  that booked money references is refused outright** (`isInUse()`/`usageCount()`
  check both `business_expenses` and the `Accounts` expense rows — account-scoped
  Eloquent builders, so a sibling account's bookings never hold a category open)
  — the expense stores the key as a string, so a delete would strand it.
  The settings page **says so instead of hiding the button**: an in-use row's
  delete turns into a lock that opens the shared dialog
  (`partials/confirm-modal`, `confirmAction`/`amsAlert`) naming how many records
  hold it, whose OK submits the hidden deactivate form — the one action that is
  allowed. `destroy()` re-checks server-side; the dialog is only the
  explanation, and `usageByKey()` is the two-aggregate version for the list.

Rules:

- **The form and its validation read the same list.** `recordExpense()` renders
  `ExpenseCategory::options()` and `StoreBusinessExpenseRequest` validates
  `Rule::in(array_keys(...))` of it. They were two hard-coded lists that had
  drifted — the dropdown offered `legal_fee` and `salary`, the request allowed
  `legal` and `salaries`, so those two options were unsubmittable. Don't
  reintroduce a second list.
- **Defaults are seeded lazily** (`ensureDefaults()`), not by a data migration,
  so accounts created later get them too. An account with **no** categories
  refills — an empty dropdown makes the expense form unusable — which is also
  why `update()` refuses to deactivate the last active one.
- **Booked rows print `labelFor()`**, never the raw key: it falls back to the
  humanized key so a deleted category, or the separate hard-coded "other
  expense" vocabulary in `StoreOtherExpenseRequest`, still reads correctly.
  The label memo is keyed by account — flush it after any write.
- `ApartmentFixedExpense.expense_type` (parking/internet/trash/other) is a
  **different** vocabulary — those are recurring charges billed to a room's
  tenant, not owner-side expense classification. Don't merge them.
- New account-owned table ⇒ it is deleted in `AccountPurgeService`.

`tests/Feature/RevenueExpense/ExpenseCategoryTest.php` pins all of it.

---

## Global helpers (`app/helpers.php`)

| Helper | Purpose |
|--------|---------|
| `settings($key, $default)` | Read/write `Settings` model (per account via BelongsToAccount). Pass array to bulk-set. |
| `currency_symbol()` | Returns `$` (USD) or `៛` (KHR) based on `system_currency` setting. |
| `status_label(?string $status)` | Localised human-readable label; looks up `messages.status_labels.*`; falls back to humanized raw value. |
| `current_account_id()` | Returns account id for the current request (see Multi-tenancy section). |

---

## Deployment & proxy prefix

- Deploy with `./deploy.sh` (git pull → `composer install --no-dev` → `migrate --force` → cache config/route/view).
- The app runs **behind a Cloudflare Tunnel + nginx at sub-path `/ams_app`**. `bootstrap/app.php` trusts all proxies and `X-Forwarded-Prefix` so generated URLs/redirects/assets keep the prefix. **Never hardcode root-relative paths** (`/foo`) — always use named routes or `route()`. Regression test: `tests/Feature/ProxyPrefixUrlTest`.

---

## Commands

```bash
composer dev          # server (port 8001) + queue + pail logs + vite, all at once
composer test         # config:clear then artisan test
./vendor/bin/pest     # run tests directly
./vendor/bin/pint     # format PHP — run before committing
php artisan migrate   # run migrations
npm run dev           # vite dev mode
npm run build         # build production assets

# Bakong — all offline unless stated
php artisan bakong:usage              # allowance spent per day, and on what
php artisan bakong:diagnose           # can this install take a payment? (--live = 1 request)
php artisan bakong:token status       # token expiry, read from the JWT
php artisan khqr:expire-abandoned     # close open api-channel rows; calls nobody
```

Tests: `tests/Feature/{Auth,Payment,Subscription,SuperAdmin,FiscalPeriod,Middleware,RevenueExpense}`. Add a test when changing payment, subscription, scoping, or fiscal-period behavior.

---

## Conventions & do-nots

- Format with **Pint** before committing.
- Use Eloquent relationships over raw SQL.
- User-facing strings go through `__()` / `lang/`; both `en` and `km` need entries.
- Shared Blade layouts: `resources/views/layouts/`. Components: `resources/views/components/`. Partials: `resources/views/partials/`.
- **Printing**: every panel layout loads `resources/css/print.css` — the global A4 `@media print` system (hides app chrome via element selectors + `.no-print`, un-clips scroll containers, repeats `thead` per page, keeps rows/totals unsplit, undoes the `rtable` mobile card view). Report letterheads/footers come from `<x-print.report-header>` / `<x-print.report-footer>` (`resources/views/components/print/`, inline-styled so they also render in Dompdf): pass `landscape` on wide reports and `screen` in standalone printable documents. Never say `@vite` inside a Blade comment or inline CSS — Blade compiles the directive anywhere, including comments.
- `AuditLogger::record()` never throws — an audit-write failure must not roll back the money action it records.
- **Do not add a "Fixed Monthly Costs" summary card** to the break-even page (`shared/revenue_expense/break_even.blade.php`) — it has been removed intentionally more than once.
- `Subscription` is intentionally NOT `BelongsToAccount`-scoped — do not add it.
- **Never add an `Http::` call to a Bakong endpoint outside `BakongProviderClient`**, and never reintroduce one into `KhqrPaymentService` (the rent channel contacts nobody, by design). The guards are not at the call site precisely so the next person cannot forget them.
- **Nothing is CSRF-exempt.** The only exemption this app ever had was the khqr.cc webhook; add one back only for an endpoint that authenticates every request by its own signature.
- Payment `status` columns are VARCHAR, not DB enum — do not convert them.
- **Room maintenance mode** is the boolean `apartments.under_maintenance`, deliberately NOT a third `status` enum value (the enum was narrowed back to available/occupied in `2026_06_08_141001_remove_maintenance_from_apartments_status` — don't re-add it). `status` answers "is someone living here?"; `under_maintenance` answers "is this unit part of the rentable stock?". Rules:
  - Use `Apartments::rentable()` for occupancy / expected-revenue / break-even **denominators** so a maintenance unit never reads as a room the owner failed to rent.
  - The flag has **no history** — the rentable count is today's state while occupancy is the viewed month's, so any month/rentable ratio must floor its denominator at that month's occupancy (`max($rentableCount, $currentOccupancy)` in `BreakEvenService`), or a room let earlier and mothballed later reports 2-of-1 rented.
  - **Never** scope historical money queries with it — a unit put under maintenance after a tenant left still earned real income earlier, and dropping it erases booked transactions. `BreakEvenService` keeps the full `$apartmentIds` for rental/utility lookups and a separate rentable count for `total_apartments`; follow that split.
  - Assignment is blocked in `TenantAssignmentService` (inside the row lock) plus the room pickers/validation in both TenantControllers. Switching maintenance ON is refused while the unit is occupied.
  - The switch on the room edit page is **its own form posting to `admin.apartments.maintenance`** (`ApartmentController@toggleMaintenance`) — it saves on click and flashes a confirmation, and its hidden value is the *inverse* of the stored state so it needs no JS. It is deliberately not part of the "Update Room" submit: as an in-form Alpine field, users flipped it and never pressed Save, so nothing ever persisted. `update()` still honours the flag (and the occupied guard) for stale tabs.
  - Use `$apartment->displayStatus()` for badges/dots (returns `'maintenance'`, gray) — don't read `status` directly in views. The dashboard's Floor Quick View popup (`partials/floor-quick-view.blade.php`) is fed by `HasDashboardMonthNavigation::buildFloorPlan()`, whose per-room array carries `maintenance` and whose `total`/`occupied` are rentable-only — a projected array like this is easy to miss when adding a room-status concept.
  - In `Rule::exists()` wheres pass `0`, not `false` — the rule serialises its wheres to a string where `false` becomes `''` and matches nothing.
  - The plan room cap (`SubscriptionService::roomCount()`) intentionally still counts maintenance rooms, so accounts can't park rooms to exceed their cap.
- `users.phone` is **globally unique** (one login namespace — `Auth::attempt()` looks phones up globally). Never scope a users-table phone-uniqueness rule per account. The signup flow's takeover of failed/lapsed owner rows is the only sanctioned reuse.
- Financial-history FKs (`payments`, `utilities`, `tenant_leaves`, `khqr_payments` → rentals/tenants/apartments) are `ON DELETE RESTRICT`. Deleting a customer account goes through `AccountPurgeService` (children first, files included) — never manual deletes, and never rely on DB cascades from soft-delete models (soft deletes don't fire them).
- The soft-delete uniques on `apartments`/`tenants` are MySQL functional indexes over `IFNULL(deleted_at, epoch)`; keep request validation in place — SQLite tests rely on it.
- Money casts are split on purpose: customer-facing tables (`payments`, `accounts`, `khqr_payments`, …) cast `amount` to `float`; the platform-finance tables use `decimal:2`. The accounting math was audited as-is (epsilon comparisons where it matters) — don't churn the casts app-wide.
- Validation style: FormRequests exist for the Auth, FiscalPeriod, RevenueExpense, and Tenants domains; everywhere else uses inline `$request->validate()`. Match whichever the domain already uses.
- `env()` may only be called in `config/` files — both the live host and `deploy.sh` run `config:cache`, under which `env()` returns null everywhere else (that is how `FORCE_HTTPS` silently broke once).
