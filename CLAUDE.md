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
- **Admin** → their own `user.id` (admin's `account_id` points to themselves).
- **Supervisor / Tenant** → their `users.account_id` (which points to their admin).
- **Unauthenticated (login, signup, seeders, console)** → `null` → the scope is a no-op so global lookups still work.

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
| `fiscal.period` | `EnsureFiscalPeriodExists` | Admin: requires their own open `FiscalPeriods` row; else → `admin.fiscalperiod.create`. Supervisor: requires any admin's open period; else → `supervisor.dashboard` with a warning. |
| `SetLocale` | `SetLocale` | Runs on every web request. Priority: `session('locale')` → DB `Settings.app_locale` → `config('app.locale')`. Supported: `en`, `km`. |

---

## Key directories

```
app/
  Http/Controllers/{Admin,Supervisor,Tenant,SuperAdmin,Auth}/
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
    RevenueExpense/                ← BreakEvenService, ExpenseRecordingService,
                                      IncomeRecordingService, KhqrCredentials,
                                      KhqrPaymentService, MonthlyBillingService,
                                      RevenueExpenseQueryService
    Subscription/SubscriptionService
    Tenants/                       ← TenantLeaveProcessor, TenantPendingChargesQuery,
                                      TenantRentProgressCalculator
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

### Adding a payment provider

Implement `App\Contracts\PaymentGateway` (three methods: `provider()`, `verify()`, `validateWebhook()`) and register the driver in `App\Services\Payment\PaymentManager`.

### KHQR secrets

- **Platform/subscription payments**: signed with `platform_payment_settings.khqrpay_secret` (DB row), **not** `.env KHQRPAY_SECRET`. A 502 after auth passes = the khqr.cc account isn't provisioned for live QR.
- **Per-merchant tenant payments**: `MerchantPaymentSetting` (per account).
- KHQRPay webhook: `POST /khqr/callback` — signature-authenticated, **CSRF-exempt** (see `bootstrap/app.php`), throttled 60/min.

### SaaS signup funnel

`/subscribe` → checkout → KHQR → activate — all in the `guest` middleware group in `web.php`.

---

## Fiscal period pattern

- Admin must have an open `FiscalPeriods` row before accessing any financial routes gated by `fiscal.period`.
- **Supervisor writes land in the admin's books** — a supervisor doesn't own fiscal periods; they use the admin's open period.
- `HasFiscalPeriodScope` trait (in controller Concerns) provides shared helpers: `getActiveFiscalPeriod()`, `resolveActivePeriod()`, `getAllFiscalPeriods()`, `buildPeriodMonths()`, `getFilteredDateRange()`. Controllers implement two abstract methods: `fiscalPeriodsQuery()` (which periods are visible) and `ledgerUserId()` (which user's ledger rows to read/write).

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
- `AuditLogger::record()` never throws — an audit-write failure must not roll back the money action it records.
- **Do not add a "Fixed Monthly Costs" summary card** to `break_event.blade.php` — it has been removed intentionally more than once.
- `Subscription` is intentionally NOT `BelongsToAccount`-scoped — do not add it.
- Payment `status` columns are VARCHAR, not DB enum — do not convert them.
