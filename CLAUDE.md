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
- `tests/Feature/Billing/RentCollectionDayTest.php` pins both rules *and* the
  no-collection-day backward-compatibility contract.

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
