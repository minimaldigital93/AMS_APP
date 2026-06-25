# AMS_APP — Project Guidebook

A brief overview of the **Apartment Management System (AMS_APP)** — prepared for academic / school purposes.

> **For contributors / Claude Code:** `CLAUDE.md` at the root is the authoritative technical reference. Where this guidebook and `CLAUDE.md` disagree, trust `CLAUDE.md`.

---

## 1. What the Project Does

AMS_APP is a web-based **Apartment Management System** that helps property owners manage:

- Apartments, floors, and rooms
- Tenants and their leases
- Monthly rentals and payments
- Business expenses, fixed expenses, and utilities
- Accounts and balance sheets
- Fiscal periods and monthly reports
- PDF receipts and financial documents

It is built as a multi-tenant SaaS platform with four distinct user roles: **SuperAdmin**, **Admin**, **Supervisor**, and **Tenant**.

---

## 2. Technology Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2+ |
| Framework | Laravel 12 |
| Authentication | Laravel Breeze + Sanctum |
| Authorization | spatie/laravel-permission (role-based) |
| Frontend | Blade templates + Tailwind CSS + Vite |
| PDF generation | barryvdh/laravel-dompdf |
| Testing | Pest 4 |
| Database | MySQL / SQLite (Eloquent ORM) |

---

## 3. User Roles

The system has four role areas, each with its own controllers and views.

### 3.1 SuperAdmin
Platform-level access across all customer accounts — manages subscriptions, plans, platform finances, and cross-account data. Uses `App\Http\Controllers\SuperAdmin` and is not subscription-gated.

### 3.2 Admin
Full access within their own account — manages apartments, users, fiscal periods, settings, and revenue/expenses. Requires an active subscription (`subscription.active` middleware).

### 3.3 Supervisor
A manager-level user **scoped to the properties assigned to them** (`properties.supervisor_id`). They can only see floors, rooms, and tenants that belong to their assigned properties. Admins and superadmins can also access supervisor routes (for preview) and are not property-scoped. Routes use `role:supervisor|admin|superadmin`.

### 3.4 Tenant
A resident user who can view their own dashboard, payments, and leave requests.

---

## 4. Project Structure

```
AMS_APP/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/          ← Admin-only logic
│   │   ├── SuperAdmin/     ← SuperAdmin (platform) logic
│   │   ├── Supervisor/     ← Supervisor logic
│   │   ├── Tenant/         ← Tenant logic
│   │   └── Concerns/       ← Shared controller traits
│   ├── Models/             ← Eloquent models
│   │   └── Concerns/       ← BelongsToAccount (multi-tenant scope)
│   ├── Services/           ← Domain logic (Payment, Subscription, etc.)
│   ├── Enums/              ← PaymentStatus, SubscriptionStatus
│   ├── Contracts/          ← PaymentGateway interface
│   └── helpers.php         ← Global helper functions
├── resources/views/
│   ├── admin/
│   ├── superadmin/
│   ├── supervisor/
│   ├── tenant/
│   └── layouts/            ← Shared Blade layouts
├── routes/
│   ├── web.php             ← Main app routes
│   └── auth.php            ← Auth (Breeze) routes
├── database/
│   ├── migrations/
│   └── seeders/
└── docs/                   ← Feature documentation
```

---

## 5. Domain Models

Located in `app/Models/`:

| Model | Purpose |
|-------|---------|
| `Apartments` | An apartment building |
| `Floors` | Floors inside an apartment |
| `Property` | A property grouping apartments, carries `supervisor_id` |
| `Tenants` | People renting a unit |
| `Rentals` | Lease/rental contracts |
| `Payments` | Rent and other payments |
| `Accounts` | Cash / bank accounts |
| `BalanceSheet` | Financial reporting |
| `BusinessExpense` | General business costs |
| `ApartmentFixedExpense` | Recurring building costs |
| `Utilities` | Water, electricity, etc. |
| `FiscalPeriods` | Yearly fiscal periods |
| `MonthlyPeriod` | Monthly accounting periods |
| `TenantLeave` | Tenant move-out requests |
| `Settings` | System configuration (per account) |
| `User` | Authenticated user |
| `Subscription` | SaaS subscription (plan, status, expiry) |
| `Plan` | SaaS plan definition |
| `MerchantPaymentSetting` | Per-merchant KHQR/payment config |
| `PlatformPaymentSetting` | Platform-level KHQR signing credentials |
| `KhqrPayment` | KHQR payment record |
| `PaymentWebhook` | Raw KHQR webhook log |
| `Refund` | Payment refund record |
| `PlatformExpense` | Platform-level expense (superadmin) |
| `PlatformFiscalPeriod` | Platform-level fiscal period |
| `PlatformMonthlyClose` | Platform monthly close |
| `PlatformWithdrawal` | Platform withdrawal record |
| `AuditLog` | System audit trail |

Most customer-owned models use the `BelongsToAccount` trait for automatic per-account scoping.

---

## 6. Main Features by Role

### Admin
- Manage apartments, floors, tenants, users
- Configure fiscal periods and system settings
- View and record revenues / expenses
- Generate PDF reports

### Supervisor
- Dashboard scoped to assigned properties only
- Manage apartments and tenants under their assigned properties (`properties.supervisor_id`)
- Record revenue and expenses for their properties
- Adjust supervisor-level settings

### Tenant
- Personal dashboard
- View payment history and rental info

---

## 7. How to Run the Project

Make sure PHP 8.2+, Composer, and Node.js are installed.

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# Run database migrations
php artisan migrate

# Start the dev server
php artisan serve

# In a second terminal, start Vite
npm run dev
```

Visit: **http://127.0.0.1:8000**

---

## 8. Useful Commands

```bash
php artisan serve          # Run development server
php artisan migrate        # Run database migrations
php artisan migrate:fresh  # Reset database
php artisan tinker         # Interactive REPL
npm run dev                # Vite dev mode
npm run build              # Build production assets
./vendor/bin/pest          # Run tests
./vendor/bin/pint          # Format PHP code
```

---

## 9. Coding Conventions

- **Role isolation:** Controllers stay within their own role namespace; do not cross between Admin / Supervisor / Tenant.
- **Views mirror controllers:** `Supervisor\TenantController` → `resources/views/supervisor/tenant/`.
- **Use Eloquent:** Prefer relationships over raw SQL.
- **Shared layout:** Use the layouts in `resources/views/layouts/`.
- **Helpers:** Global helper functions live in `app/helpers.php`.

---

## 10. Additional Documentation

More detailed guides are stored in the `docs/` folder:

- `API_MOBILE_INTEGRATION_GUIDE.md` — Mobile API integration
- `FISCAL_PERIOD_GUIDE.md` — Fiscal period feature
- `SYSTEM_SETTINGS_GUIDE.md` — System settings reference

---

## 11. Summary

AMS_APP demonstrates a real-world **multi-role Laravel application** combining:

- Role-based access control
- Financial accounting (rent, expenses, balance sheets)
- Multi-tenant property management
- PDF document generation
- Modern Blade + Tailwind frontend

It is a practical example of how the **MVC pattern**, **Eloquent ORM**, and **Laravel's ecosystem** work together to build a complete management system.
