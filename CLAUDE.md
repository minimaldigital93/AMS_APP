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
| Payments | KHQRPay (Bakong KHQR) — subscriptions and tenant payments |
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
    Payment/
      PaymentManager               ← resolves PaymentGateway drivers
      Gateways/KhqrPayGateway      ← KHQRPay driver (implements PaymentGateway)
      RefundService                ← handles refunds
      WebhookIngestService         ← processes raw webhook payloads
    Platform/PlatformFinanceService← cross-account platform finance (superadmin)
    Platform/AccountPurgeService   ← full account deletion (rows + files);
                                      soft-delete models never fire DB cascades and
                                      history FKs are RESTRICT — delete children first
    RevenueExpense/                ← BreakEvenService, ExpenseRecordingService,
                                      IncomeRecordingService, KhqrCredentials,
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
on Basic who clicked upgrade to Pro, hit the khqr.cc page and closed the tab
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

Implement `App\Contracts\PaymentGateway` (three methods: `provider()`, `verify()`, `validateWebhook()`) and register the driver in `App\Services\Payment\PaymentManager`.

### KHQR secrets

- **Platform/subscription payments**: signed with `platform_payment_settings.khqrpay_secret` (DB row), **not** `.env KHQRPAY_SECRET`. A 502 after auth passes = the khqr.cc account isn't provisioned for live QR.
- **Per-merchant tenant payments**: `MerchantPaymentSetting` (per account).
- KHQRPay webhook: `POST /khqr/callback` — signature-authenticated, **CSRF-exempt** (see `bootstrap/app.php`), throttled 60/min.

### SaaS signup funnel

`/subscribe` → checkout → KHQR → activate — all in the `guest` middleware group in `web.php`.

### `redirect()->away()` is a one-way door — preflight the gateway first

Both subscription entry points (`SubscriptionController::store()`,
`Admin\BillingController::renew()`) hand the browser to khqr.cc's hosted
checkout. Once it is there, a profile that cannot transact answers with a raw
JSON body — `{"responseCode":1,"responseMessage":"Bakong Token Required…"}` —
and the customer is left reading a JSON file on someone else's domain, with no
way for this app to say what happened or offer a retry. That was the behaviour
until 2026-08.

`KhqrPaymentService::platformCheckoutFault()` is the gate. Call it **before
minting anything** — a refusal then leaves no half-finished signup and no orphan
QR, matching the missing-credentials guard — and flash its return value as
`error` on the form the customer is already on.

- **It runs TWO probes, and only the second one catches the reported failure.**
  `probeCheckTransaction()` asks the read-only `check-transv2-khqrcc` endpoint
  whether the profile answers at all (wrong secret, wrong profile id, gateway
  down). `probeHandoff()` asks the hosted-checkout endpoint the customer is
  about to be sent to whether it will render a payment form or a JSON refusal.
  **Checking a transaction and taking money are different permissions at
  khqr.cc**: this account passed probe 1 with a healthy `404 Transaction Not
  Found` and answered probe 2 with `422 Bakong Token Required: No active
  official Bakong OpenAPI token configured` — so until 2026-08 the guard
  reported healthy and customers still landed on the JSON page. A payment form
  answers as HTML; a refusal answers as JSON with a non-zero `responseCode`, and
  telling those apart *is* the check.
- **Both probe a throwaway transaction id, never the row's own.** khqr.cc
  checkout sessions are single-use (see `createSubscriptionQr`), so GETting the
  *customer's* checkout URL would burn the session they are about to open —
  that, and not the request itself, is what must never be done. The handoff
  probe opens a throwaway session nobody will ever be sent to.
  `services.khqrpay.handoff_preflight` (`KHQRPAY_HANDOFF_PREFLIGHT`, default on)
  turns it off if khqr.cc ever objects to those unused sessions.
- **It fails open, deliberately.** Only a positive showing that the profile
  can't transact is a fault: 5xx (the unprovisioned-profile signature here),
  401/403/404, or a non-zero `responseCode` whose message names a credential
  problem (`isConfigurationRefusal()`). A timeout, a network blip, or a plain
  "transaction not found" all pass — blocking a working checkout on a flaky
  probe costs real money. A healthy verdict is cached 60s; a fault never is.
- **The status poll never 500s.** Both `status()` endpoints catch `Throwable`
  and return `gateway_error: true` with the row's unchanged status. The checkout
  pages warn after two consecutive bad polls (`stalled`) but keep polling — the
  payment can still land, so it is a warning beside the spinner, not a terminal
  state. A non-OK response used to be silently swallowed and the customer
  watched the spinner forever.
- **A healthy verdict is only cached when both probes said so.** An `unknown`
  (timeout, blip) is let through but never silences the next check, or one
  timeout buys a broken gateway a free minute of handoffs.
- **A refusal is cached too, for the same 60 seconds** (`faultVerdictKey()`,
  added 2026-08). Only the healthy verdict used to be, on the reasoning that a
  fault must re-probe so a fixed profile works immediately — but the profile
  this app points at has been faulting since June, so in practice every visit to
  the billing page bought the same discovery for two metered calls and the guard
  outspent the payments it guards. The reasoning is preserved where it actually
  matters: `platformDiagnostics()` never reads the cache and **clears both
  verdicts** (`forgetCachedVerdicts()`), because that is the page an operator is
  standing on while doing the fixing. Keep it distinct from `lastFaultKey()` —
  that is the six-hour record of *what* refused, and it is only ever displayed;
  this one suppresses calls.
- **The probes are skipped entirely once `dailyBudgetExhausted()`.** They cost
  what a verify costs, and a health check must never spend the reserve kept for
  a payment. Checkout then **fails open** (an unrunnable check is not a fault);
  diagnostics reports the two probes as skipped, since `usageCheck()` directly
  above them has already stated the finding.
- Checkout views read `$payment->subscription?->plan?->…`: a superadmin can
  delete a Plan mid-checkout, and a 500 there replaces "confirming your payment"
  with an error page mid-payment.

#### …and when it does refuse, a popup says which part

One flash sentence is all the customer needs; whoever has to *fix* the profile
needs to know which check failed and in whose words.
`<x-khqr-diagnostics>` (`components/khqr-diagnostics.blade.php`) is that popup,
and `KhqrPaymentService::platformDiagnostics()` is the one report behind it —
also printed by `php artisan khqr:diagnose`, which exists because the failure
can lock the operator out of the very page the popup lives on (no active
subscription + a gateway that won't take payment leaves nowhere in the UI to
stand).

- **Two audiences, one component, and the difference is `endpoint`.** With it
  (billing page, admin checkout) the popup runs the live checks and quotes the
  gateway verbatim. Without it (public signup form, signup checkout) it says
  what happened, that no money moved, and what to do — **never** the probe
  results: `detail` names the profile id and the gateway's internals, and an
  unauthenticated probe route would be a free way to spend a metered Bakong
  token. `admin.billing.diagnostics` is auth'd and throttled 10/min.
- **`khqr_fault` is the auto-open trigger**, flashed beside `error` only by the
  gateway refusal paths — the billing page flashes `error` for ordinary
  failures too, and a diagnostics dialog is the wrong answer to those.
- **The last recorded refusal is shown beside the fresh run.** The gateway is
  allowed to answer differently a minute later (and a healthy verdict is cached
  for one), so an all-green report in front of someone staring at the failure is
  worse than no report.
- **Both checkout pages carry a "payment page didn't open?" button.** The
  spinner cannot tell "not paid yet" from "khqr.cc showed you JSON and you came
  back" — the row sits in `qr_generated` either way — so without it the
  customer's only option is to watch it until the QR expires.
- The webhook URL is reported as `info`, not a pass/fail: nothing here can read
  back what is pasted into the khqr.cc profile, and a missing one is exactly the
  case where the payment succeeds and the checkout page spins forever.
- **Every check carries its own `remedy`, and the generic paragraph is only a
  fallback.** "The gateway refused" hides four different jobs — add a credential
  (our settings page), wait out an allowance (nobody, it clears at midnight),
  re-copy a secret (our settings page), get a token activated (khqr.cc, and only
  khqr.cc) — done in different places by different people. `probeRemedy()` picks
  the one that applies from the status and the gateway's words; a healthy check
  gets none. Rendering the catch-all beside a specific remedy is what left the
  reader unsure which applied, so the blade shows it only when a failing check
  has no remedy of its own (`failedWithoutRemedy`).
- **`copy` is for values that must leave the screen intact**: the webhook URL,
  and — only when the refusal needs someone at khqr.cc (`needsGatewayOperator()`)
  — a support sentence quoting their own words back with the profile id and the
  fact that check-transaction answers fine. A quota or bad-secret refusal gets
  no support sentence; there is nobody to send it to.
- **The allowance is a check of its own** (`usageCheck()`), printed above the
  probes: a spent token and an unconfigured one produce similar-looking
  refusals, and only one of them resolves itself.
- `khqr:diagnose` prints remedy and copy lines too — an SSH session and a
  support call must not read different advice off the same report.

`tests/Feature/Subscription/CheckoutPreflightTest.php` pins it.

#### The Bakong token is metered per day, and a refusal costs the same as a sale

Bakong rates the upstream OpenAPI token per calendar day (this account's
allowance is ~100 requests). A request that is *refused* is charged exactly like
one that answers, so an app that keeps polling a spent token spends the rest of
the day discovering it is spent. Five guards bound it, and they are the only
things that do — `env` defaults are in `config/services.php`:

- **`KHQRPAY_DAILY_BUDGET`** — a hard ceiling on live calls **per settlement
  target** per day (`dailyBudgetExhausted()`). Per-target because platform rows
  spend the SaaS operator's token and merchant rows spend the individual
  landlord's; a shared cap would let one busy landlord lock out everyone. Past
  the ceiling the gateway is not called at all. **0 disables it** — that is the
  backward-compatibility seam, and it is what an untouched deployment gets.
- **`KHQRPAY_VERIFY_COOLDOWN`** — minimum seconds between live calls for the
  same transaction. Every checkout poller *and* `khqr:reconcile` funnel through
  `verify()`, so this is the single most effective throttle. It must stay **≥
  the browser poll interval** (`POLL_MS`, 10s in all three checkout views) or it
  absorbs nothing: at the 4s default every 10s poll was a live call.
- **`KHQRPAY_QR_TTL`** — also caps how long one abandoned tab can poll, since a
  row past `expires_at` is terminal and `verify()` short-circuits on it.
- **`KHQRPAY_RECONCILE_GRACE`** — minutes past a QR's `expires_at` that
  `khqr:reconcile` keeps re-verifying it. **This is the quota bound on the
  safety net**, and until 2026-08 there was nothing playing that role: the run
  swept every open API row created in the last *day*. With a 10-minute QR that
  is 288 live calls per abandoned checkout, and because a profile with no Bakong
  token refuses every one of them — and a refusal (correctly) never closes the
  row — nothing took the row back out of scope. The allowance was gone by
  ~02:30 with nobody having touched the app. See `reconcileWindow()`.
- **`KHQRPAY_RECONCILE_ENABLED`** — master switch for that safety net, applied
  as `->skip()` in `routes/console.php` rather than a commented-out schedule
  line. Set it false while the khqr.cc profile has no usable Bakong token: the
  net cannot confirm anything then, so every run is pure spend. **Turn it back
  on once the token is active** or paid-but-unnotified rows stop being rescued.

`php artisan khqr:usage` reports spend against the budget. It counts
`queryProviderOutcome()` **and both preflight probes** (since 2026-08 — they
were uncounted, so the ceiling protecting the allowance could be sailed past by
the probes meant to protect it, and the table under-reported every checkout
attempt by two).

`php artisan khqr:expire-abandoned` clears open rows the window has left behind,
**without calling the gateway**. `khqr:reconcile` deliberately cannot do this —
it only expires on a conclusive unpaid, and a permanently-refusing gateway never
gives one, which is how two rows sat in `qr_generated` for seventy-three days.
Closing them is a human judgement ("a QR from days ago will not be paid"), so it
is an operator command with a confirmation, not automation. Safe to run when the
allowance is already spent, which is when the backlog exists.

#### A refusal is not a verdict — `verifyOutcome()`, not `verify()`

`verify()` returns bool, and every caller read its `false` as *"the payer has
not paid"*. For a 200 saying "transaction not found" that is right. For a
refusal — spent allowance, 429, 5xx, timeout — it is a guess, and it is the
expensive kind: the money may already have landed, and a row expired on that
guess is a payment written out of the books with no way back. Over-limit makes
the refusal the *normal* answer rather than the rare one, which is how a quota
problem becomes a money problem.

`KhqrPaymentService::verifyOutcome()` has three results — `VERIFY_PAID`,
`VERIFY_UNPAID`, `VERIFY_REFUSED`. **Only a 2xx from the gateway can say
unpaid.** `verify()` stays as the thin bool wrapper so the `PaymentGateway`
contract is unchanged; anything that acts on a **negative** — expiring a row,
giving up on it — must use `verifyOutcome()` and treat a refusal as "ask again
later":

- `pollAndAdvance()` never expires on a refusal, and sets `lastPollRefused()`,
  which all three poll endpoints return as `gateway_error` so the checkout page
  warns beside the spinner instead of spinning in silence.
- `ReconcileKhqrPayments` skips both finalize **and** expire on a refusal.
  Expiry is terminal, so expiring here means the safety net never looks at that
  QR again even after the gateway recovers. That is also why the run needs a
  **window** (`reconcileWindow()`): "leave it open and ask again" has no exit
  when the gateway never answers, so the bound has to come from how long the
  asking lasts, not from the answer.
- The cooldown caches the **outcome string** under `khqr:verify:outcome:…`,
  keyed apart from the old boolean `khqr:verify:…` so a value written by the
  previous release is never read back as an outcome.

#### The preflight has to know a spent allowance from a broken one

`isConfigurationRefusal()` matches credential words (`token`, `profile`,
`hash`, …); a rate-limited gateway says none of them, so the preflight returned
`unknown`, **failed open**, and handed the customer to khqr.cc to read the same
refusal as raw JSON — the exact scenario the preflight exists to prevent, for
the one cause it could not name. `isQuotaRefusal()` covers it and
`isBlockingRefusal()` is the union both probes now decide on; **429 joins
401/403/5xx** as a positive fault in both probes.

Its needles are deliberately **phrases** (`rate limit`, `daily limit`, `quota`,
…), never the bare word `limit`: the handoff probe asks for a 0.01 amount, and a
gateway answering *"amount below minimum limit"* is describing the probe, not
the profile. Matching that would block checkout on a healthy account — the false
alarm this guard is written to fail open on.

`tests/Feature/RevenueExpense/KhqrQuotaGuardTest.php` pins all three (how a
refusal is *read*); `tests/Feature/RevenueExpense/KhqrQuotaBoundTest.php` pins
what *bounds* the spend — the reconcile window, the counted probes, the cached
refusal and `khqr:expire-abandoned`.

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
  (the tenant-index badge, both panels), `ContractGenerator` (ប្រការ៤ due day,
  ប្រការ៥ grace). The last two read `rentals.rent_amount` raw until 2026-08 and
  reported a phantom shortfall on every fully-paid prorated move-in month.
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
  rent alone made the tile disagree with the page it opens.
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
- **Quote checkout the unpaid totals** (`unpaid_utility_only`,
  `unpaid_other_charges`), never the gross ones. `settleUtilitiesForMonth()`
  only settles unpaid rows, so a second visit shown gross figures re-quotes
  money the first visit already took. The modal locks the rent line via
  `rent_status` for the same reason.
- **Pending is tracked per side** (`totalPendingRent` + `totalPendingCharges`).
  One all-or-nothing test — the old behaviour — dropped a rent-paid tenant's
  unpaid charges out of the tile entirely, which under this workflow is every
  tenant every month. Fixed apartment costs ride with rent; they have no
  settlement row of their own.

`checkout()`'s `pay_rent` / `pay_utilities` flags were always independent — it
was the status and totals layer that assumed one payment.
`tests/Feature/RevenueExpense/SplitRentChargesStatusTest.php` pins all of it.

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

- **Only the current month can be undone.** The window is the ledger row's
  `transaction_date` — when the money was *booked*, not the month it billed —
  so July's rent collected on Aug 3 is reversible through August (that late
  collection is the everyday mistake) while July's own collection is not. A
  reversal deletes ledger rows; an earlier month's revenue has already been
  read and reported, and is corrected by an adjustment in the open month
  instead. Checked before the closed-period/month reasons so the flash doesn't
  advise reopening a month that reopening wouldn't help.
- **Closed money is never restated.** A payment whose ledger rows sit in a
  closed fiscal period or a closed `MonthlyPeriod` is refused — reopen the month
  first. Same rule as `LeaseSyncService`'s deposit row. (The route also sits
  behind `fiscal.period`, so the closed-*period* case is a service-level guard.)
- **The Payments row is soft-deleted, the Accounts rows are deleted outright** —
  income never received must not sit in the books, which is how every other
  ledger-undo path here behaves. `AuditLogger` records `payment.reversed`.
- **A charges payment is matched to its rows by `paid_at`**, the same join
  `printReceipt()` uses (utilities carry no `payment_id`). An empty set is
  legitimate (a hand-recorded utilities payment settled no rows); a non-empty
  set whose total doesn't reconcile means two batches share the timestamp, and
  the reversal is **refused rather than guessed**.
- Reversal does **not** refund a KHQR transaction — it corrects the books only.

`tests/Feature/RevenueExpense/PaymentReversalTest.php` pins all of it.

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
- Fixed room costs settle with rent (they have no settlement row), which is what
  marks them paid on the summary.
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
