# Fiscal Period Management - Integration Guide

## Adding Navigation Links

To make the Fiscal Period Management system easily accessible from your admin dashboard, add these navigation links.

### Option 1: Add to Admin Sidebar/Navigation

#### If using a navigation menu component:
```blade
<li>
    <a href="{{ route('admin.fiscalperiod.index') }}" class="nav-link">
        <i class="icon-calculator"></i> Fiscal Periods
    </a>
</li>
```

#### Dashboard Widget:
```blade
<div class="col-md-3 mb-4">
    <div class="card">
        <div class="card-body text-center">
            <h5 class="card-title">Fiscal Periods</h5>
            <p class="card-text">Manage accounting cycles</p>
            <a href="{{ route('admin.fiscalperiod.index') }}" class="btn btn-primary">Open</a>
        </div>
    </div>
</div>
```

### Option 2: Quick Links in Admin Dashboard

Add to your admin dashboard blade file:
```blade
@if(auth()->check() && auth()->user()->hasRole('admin'))
    <div class="dashboard-section">
        <h3>Financial Management</h3>
        <ul class="quick-links">
            <li><a href="{{ route('admin.fiscalperiod.index') }}">Fiscal Periods</a></li>
            <li><a href="{{ route('admin.fiscalperiod.create') }}">Create New Period</a></li>
        </ul>
    </div>
@endif
```

## URL Reference

| Action | Route | URL |
|--------|-------|-----|
| List Periods | `admin.fiscalperiod.index` | `/admin/fiscalperiod` |
| Create Period | `admin.fiscalperiod.create` | `/admin/fiscalperiod/create` |
| View Period | `admin.fiscalperiod.show` | `/admin/fiscalperiod/{id}` |
| Edit Period | `admin.fiscalperiod.edit` | `/admin/fiscalperiod/{id}/edit` |
| Balance Sheet | `admin.fiscalperiod.balance-sheet` | `/admin/fiscalperiod/{id}/balance-sheet` |
| Closing Balances | `admin.fiscalperiod.open-close-balances` | `/admin/fiscalperiod/{id}/open-close-balances` |
| Reports | `admin.fiscalperiod.reports` | `/admin/fiscalperiod/{id}/reports` |
| Export CSV | `admin.fiscalperiod.exportCSV` | `/admin/fiscalperiod/{id}/export-csv` |

## Access Control

All routes are protected by:
```blade
->middleware(['auth', 'role:admin'])
```

Only admin users can access fiscal period management.

## Direct Access Links

Users can access directly with these URLs:
- Main page: `http://yourapp.com/admin/fiscalperiod`
- Create: `http://yourapp.com/admin/fiscalperiod/create`

## Integration with Other Modules

### Linking from Dashboard
```blade
@php
    $openPeriods = auth()->user()->fiscalPeriods()->where('status', 'open')->count();
    $closedPeriods = auth()->user()->fiscalPeriods()->where('status', 'closed')->count();
@endphp

<div class="stats-card">
    <p>Open Periods: {{ $openPeriods }}</p>
    <p>Closed Periods: {{ $closedPeriods }}</p>
    <a href="{{ route('admin.fiscalperiod.index') }}" class="btn">Manage</a>
</div>
```

### Recently Created Periods
```blade
@php
    $recentPeriods = auth()->user()->fiscalPeriods()
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
@endphp

<ul>
    @foreach($recentPeriods as $period)
        <li>
            <a href="{{ route('admin.fiscalperiod.show', $period->id) }}">
                {{ $period->name }} ({{ $period->status }})
            </a>
        </li>
    @endforeach
</ul>
```

## Menu Structure Example

```
Admin Dashboard
├── Management
│   ├── Floors
│   ├── Apartments
│   ├── Users
│   └── Tenants
├── Financial (NEW)
│   ├── Fiscal Periods ← Add here
│   ├── Reports
│   └── Accounting
└── Settings
```

## Authorization Helpers

In your views, you can use these authorization checks:

```blade
@can('view fiscal periods')
    <a href="{{ route('admin.fiscalperiod.index') }}">Fiscal Periods</a>
@endcan

@if(auth()->user()->hasRole('admin'))
    <a href="{{ route('admin.fiscalperiod.create') }}">Create Period</a>
@endif
```

## Helper Functions

You can create these helper functions in AppServiceProvider or a helpers file:

```php
// Check if user has active fiscal period
function hasActiveFiscalPeriod($user = null)
{
    $user = $user ?? auth()->user();
    return $user->fiscalPeriods()
        ->where('status', 'open')
        ->exists();
}

// Get current fiscal period
function getCurrentFiscalPeriod($user = null)
{
    $user = $user ?? auth()->user();
    return $user->fiscalPeriods()
        ->where('status', 'open')
        ->latest('opening_date')
        ->first();
}

// Check if period is balanced
function isPeriodBalanced($periodId)
{
    $period = FiscalPeriods::find($periodId);
    $balanceSheet = $period->balanceSheets();
    
    $assets = $balanceSheet->where('item_type', 'asset')->sum('amount');
    $liabilities = $balanceSheet->where('item_type', 'liability')->sum('amount');
    $equity = $balanceSheet->where('item_type', 'equity')->sum('amount');
    
    return $assets == ($liabilities + $equity);
}
```

## Breadcrumb Navigation

Add to your breadcrumb component:

```blade
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
        @if(request()->is('admin/fiscalperiod*'))
            <li class="breadcrumb-item">
                <a href="{{ route('admin.fiscalperiod.index') }}">Fiscal Periods</a>
            </li>
            @if(request()->is('admin/fiscalperiod/create'))
                <li class="breadcrumb-item active">Create</li>
            @elseif(request()->is('admin/fiscalperiod/*/edit'))
                <li class="breadcrumb-item active">Edit</li>
            @elseif(request()->is('admin/fiscalperiod/*'))
                <li class="breadcrumb-item active">Details</li>
            @endif
        @endif
    </ol>
</nav>
```

## Email Notifications Example

You can add notifications when:

```php
// When period is created
auth()->user()->notify(new FiscalPeriodCreated($period));

// When period is closed
auth()->user()->notify(new FiscalPeriodClosed($period));

// When balance is unbalanced
if (!$this->validateBalance($period)) {
    auth()->user()->notify(new UnbalancedBalanceSheet($period));
}
```

## Troubleshooting Navigation

If the links don't work:
1. Verify routes are registered in `routes/web.php`
2. Check that FiscalPeriodController is imported
3. Ensure user has 'admin' role
4. Check that routes are within the admin middleware group

## API Endpoints (Optional)

To enable JSON API responses for fiscal periods:

```php
Route::middleware(['auth:sanctum', 'admin'])->prefix('api/fiscal-periods')->group(function () {
    Route::get('/', [FiscalPeriodController::class, 'index']);
    Route::post('/', [FiscalPeriodController::class, 'store']);
    Route::get('/{fiscalperiod}', [FiscalPeriodController::class, 'show']);
    Route::put('/{fiscalperiod}', [FiscalPeriodController::class, 'update']);
    Route::delete('/{fiscalperiod}', [FiscalPeriodController::class, 'destroy']);
});
```

## Support

For integration help, refer to:
- `FISCAL_PERIOD_GUIDE.md` - User guide
- `FISCAL_PERIOD_IMPLEMENTATION.md` - Technical details
- Controller methods in `FiscalPeriodController.php`

---

**Last Updated**: February 2026
