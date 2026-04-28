<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\TenantLeave;
use App\Models\Rentals;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Utilities;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Models\User;
use App\Services\TenantLeaveCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TenantController extends Controller
{
    protected TenantLeaveCalculator $leaveCalculator;

    public function __construct(TenantLeaveCalculator $leaveCalculator)
    {
        $this->leaveCalculator = $leaveCalculator;
    }

    public function index(Request $request): View
    {
        $query = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function(Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apartment filter
        if ($request->has('apartment') && !empty($request->apartment)) {
            $query->where('apartment_id', $request->apartment);
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Floor filter (filter tenants by apartment's floor)
        if ($request->has('floor') && !empty($request->floor)) {
            $floorId = $request->floor;
            $query->whereHas('apartment', function (Builder $q) use ($floorId) {
                $q->where('floor_id', $floorId);
            });
        }

        $tenants = $query->orderBy('id', 'desc')->paginate(15);
        $apartments = Apartments::all();
        $floors = Floors::whereHas('apartments')->orderBy('floor_name')->get();

        // Build rent progress for each tenant (current month)
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $rentProgressMap = [];

        foreach ($tenants as $tenant) {
            $rental = Rentals::where('tenant_id', $tenant->id)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->with(['payments' => function ($q) use ($currentMonth, $currentYear) {
                    $q->where('payment_type', 'rent')
                      ->where('payment_status', 'paid')
                      ->whereMonth('paid_at', $currentMonth)
                      ->whereYear('paid_at', $currentYear);
                }])
                ->latest('start_date')
                ->first();

            if ($rental) {
                $paidAmount = $rental->payments->sum('amount');
                $monthlyRent = $rental->rent_amount;
                $paidDate = $rental->payments->first()?->paid_at;

                // Calculate days stayed in current month
                $monthStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
                $monthEnd = $monthStart->copy()->endOfMonth();
                $totalDaysInMonth = $monthStart->daysInMonth;

                $rentalStart = Carbon::parse($rental->start_date)->startOfDay();
                $stayStart = $rentalStart->gt($monthStart) ? $rentalStart : $monthStart;
                $stayEnd = now()->gt($monthEnd) ? $monthEnd : now();
                $daysStayed = max($stayStart->diffInDays($stayEnd) + 1, 0);
                $daysStayed = min($daysStayed, $totalDaysInMonth);

                $dayPercent = $totalDaysInMonth > 0 ? round(($daysStayed / $totalDaysInMonth) * 100) : 0;
                $payPercent = $monthlyRent > 0 ? min(round(($paidAmount / $monthlyRent) * 100, 1), 100) : 0;

                $rentProgressMap[$tenant->id] = [
                    'rent' => $monthlyRent,
                    'paid' => $paidAmount,
                    'percent' => $payPercent,
                    'status' => $payPercent >= 100 ? 'paid' : ($payPercent > 0 ? 'partial' : 'unpaid'),
                    'paid_date' => $paidDate ? Carbon::parse($paidDate)->format('M d') : null,
                    'days_stayed' => $daysStayed,
                    'total_days' => $totalDaysInMonth,
                    'day_percent' => $dayPercent,
                ];
            }
        }

        // Statistics counts (across all records, not just current page)
        $activeTenantCount = Tenants::where('status', 'active')->count();
        $archivedTenantCount = Tenants::onlyTrashed()->count();
        $totalDeposits = Tenants::where('status', 'active')->sum('deposit');

        return view('admin.tenantManagement.activeTenants', compact('tenants', 'apartments', 'rentProgressMap', 'floors', 'activeTenantCount', 'archivedTenantCount', 'totalDeposits'));
    }

    /**
     * Display archived tenants (soft deleted)
     */
    public function archived(Request $request): View
    {
        $query = Tenants::onlyTrashed()
            ->with(['apartment.floor', 'leaves']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($floorId = $request->input('floor')) {
            $query->whereHas('apartment.floor', function ($q) use ($floorId) {
                $q->where('id', $floorId);
            });
        }

        $tenants = $query->orderBy('deleted_at', 'desc')->paginate(7)->withQueryString();
        $floors = Floors::orderBy('floor_name')->get();

        $archivedTenantCount = Tenants::onlyTrashed()->count();
        $recentlyArchivedCount = Tenants::onlyTrashed()->where('deleted_at', '>=', now()->subDays(30))->count();
        $totalDeposits = Tenants::onlyTrashed()->sum('deposit');

        return view('admin.tenantManagement.archivedTenants', compact('tenants', 'floors', 'archivedTenantCount', 'recentlyArchivedCount', 'totalDeposits'));
    }

    /**
     * Show leave form for a tenant
     */
    public function leave(Tenants $tenant): View|RedirectResponse
    {
        // Check if tenant exists
        if (!$tenant) {
            return redirect()->route('admin.tenants.index')
                ->with('error', 'Tenant not found');
        }

        $tenant->load(['apartment', 'rentals']);

        // Get the current active rental (allow any rental, not just active ones)
        $rental = $tenant->rentals()
            ->where('apartment_id', $tenant->apartment_id)
            ->latest()
            ->first();

        // If no rental exists, create a rental object with data from apartment
        if (!$rental) {
            $rental = new Rentals();
            $rental->id = null;
            $rental->apartment_id = $tenant->apartment_id;
            $rental->tenant_id = $tenant->id;
            $rental->rent_amount = $tenant->apartment?->monthly_rent ?? 0;
            $rental->start_date = $tenant->move_in_date;
            $rental->end_date = null;
        }

        $pendingCharges = collect();
        if ($rental && $rental->id) {
            // Pending/overdue utility payments recorded manually
            $pendingPayments = Payments::where('rental_id', $rental->id)
                ->whereIn('payment_type', ['utilities', 'other'])
                ->whereIn('payment_status', ['pending', 'overdue'])
                ->orderBy('due_date')
                ->get()
                ->map(fn($p) => (object)[
                    'id'          => 'payment_' . $p->id,
                    'source'      => 'payment',
                    'description' => $p->note ?: ucfirst($p->payment_type) . ' charge',
                    'type'        => $p->payment_type,
                    'amount'      => $p->amount,
                    'due_date'    => $p->due_date,
                ]);

            // Unpaid utility charges from the billing/utilities system
            $unpaidUtils = Utilities::where('rental_id', $rental->id)
                ->where('paid_status', false)
                ->orderBy('billing_year')
                ->orderBy('billing_month')
                ->get()
                ->map(fn($u) => (object)[
                    'id'          => 'utility_' . $u->id,
                    'source'      => 'utility',
                    'description' => ucfirst($u->utility_type) . ' — ' . Carbon::create($u->billing_year, $u->billing_month)->format('M Y'),
                    'type'        => 'utilities',
                    'amount'      => $u->charge_amount,
                    'due_date'    => Carbon::create($u->billing_year, $u->billing_month)->endOfMonth(),
                ]);

            $pendingCharges = $pendingPayments->concat($unpaidUtils)
                ->sortBy('due_date')
                ->values();
        }

        return view('admin.tenantManagement.leave', compact('tenant', 'rental', 'pendingCharges'));
    }

    /**
     * Process tenant leave and create settlement
     */
    public function processLeave(Request $request, Tenants $tenant)
    {
        try {
            $tenant->load(['apartment', 'rentals']);

            // Get the current active rental
            $rental = $tenant->rentals()
                ->where('apartment_id', $tenant->apartment_id)
                ->where(function($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>', now());
                })
                ->latest()
                ->first();

            // If no rental exists, create one with tenant and apartment data
            if (!$rental) {
                $rental = Rentals::create([
                    'apartment_id' => $tenant->apartment_id,
                    'tenant_id' => $tenant->id,
                    'rent_amount' => $tenant->apartment?->monthly_rent ?? 0,
                    'start_date' => $tenant->move_in_date,
                    'end_date' => null,
                ]);
                
                Log::info('Created rental record for tenant', [
                    'tenant_id' => $tenant->id,
                    'rental_id' => $rental->id,
                    'apartment_id' => $tenant->apartment_id,
                    'rent_amount' => $rental->rent_amount,
                    'start_date' => $rental->start_date,
                ]);
            }

            // Validate input
            $validated = $request->validate([
                'leave_date'        => 'required|date|after_or_equal:' . $tenant->move_in_date->format('Y-m-d'),
                'charge_full_month' => 'nullable|boolean',
                'charge_ids'        => 'nullable|array',
                'charge_ids.*'      => 'string',
                'notes'             => 'nullable|string',
            ]);

            $leaveDate = Carbon::parse($validated['leave_date']);

            // Rent: full month or pro-rata based on checkbox
            $chargeFullMonth = $request->boolean('charge_full_month');
            $proRataRent = $chargeFullMonth
                ? (float) $rental->rent_amount
                : $this->leaveCalculator->calculateProRataRent($rental, $leaveDate);

            // Parse prefixed charge IDs: "payment_N" and "utility_N"
            $paymentIds = [];
            $utilityIds = [];
            foreach ($validated['charge_ids'] ?? [] as $chargeId) {
                if (str_starts_with($chargeId, 'payment_')) {
                    $paymentIds[] = (int) substr($chargeId, 8);
                } elseif (str_starts_with($chargeId, 'utility_')) {
                    $utilityIds[] = (int) substr($chargeId, 8);
                }
            }

            $selectedPayments = collect();
            if (!empty($paymentIds)) {
                $selectedPayments = Payments::whereIn('id', $paymentIds)
                    ->whereIn('payment_type', ['utilities', 'other'])
                    ->get();
            }

            $selectedUtilities = collect();
            if (!empty($utilityIds)) {
                $selectedUtilities = Utilities::whereIn('id', $utilityIds)
                    ->where('paid_status', false)
                    ->get();
            }

            $utilitiesTotal = $selectedPayments->where('payment_type', 'utilities')->sum('amount')
                            + $selectedUtilities->sum('charge_amount');
            $otherTotal     = $selectedPayments->where('payment_type', 'other')->sum('amount');

            $charges = [
                'pro_rata_rent' => $proRataRent,
                'electricity'   => $utilitiesTotal,
                'water'         => 0,
                'internet'      => 0,
                'parking'       => $otherTotal,
            ];

            $settlement = $this->leaveCalculator->calculateSettlement(
                $rental,
                $tenant,
                $leaveDate,
                $charges,
                $tenant->deposit ?? 0
            );

            // Create tenant leave record
            $tenantLeave = TenantLeave::create([
                'tenant_id' => $tenant->id,
                'rental_id' => $rental->id,
                'apartment_id' => $tenant->apartment_id,
                'leave_date' => $leaveDate,
                'original_move_out_date' => $rental->end_date,
                'stay_days' => $settlement['stay_days'],
                'pro_rata_rent' => $settlement['pro_rata_rent'],
                'electricity_reading' => null,
                'electricity_charge' => $settlement['electricity_charge'],
                'water_reading' => null,
                'water_charge' => $settlement['water_charge'],
                'internet_charge' => $settlement['internet_charge'],
                'parking_charge' => $settlement['parking_charge'],
                'total_amount_due' => $settlement['total_amount_due'],
                'deposit_applied' => $settlement['deposit_applied'],
                'balance_due' => $settlement['balance_due'],
                'refund_amount' => $settlement['refund_amount'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            Log::info('Created tenant leave record', [
                'tenant_leave_id' => $tenantLeave->id,
                'tenant_id' => $tenant->id,
            ]);

            // Record revenue in Payments and Accounts tables
            $activePeriod = FiscalPeriods::where('user_id', Auth::id())
                ->where('status', 'open')
                ->orderBy('opening_date', 'desc')
                ->first();

            if ($activePeriod) {
                $apartmentNumber = $tenant->apartment->apartment_number ?? 'N/A';

                // 1) Record pro-rata rent payment
                if ($settlement['total_amount_due'] > 0 && $settlement['pro_rata_rent'] > 0) {
                    $rentPayment = Payments::create([
                        'rental_id' => $rental->id,
                        'amount' => $settlement['pro_rata_rent'],
                        'due_date' => $leaveDate,
                        'paid_at' => $leaveDate,
                        'payment_method' => 'cash',
                        'payment_status' => 'paid',
                        'payment_type' => 'rent',
                        'transaction_reference' => null,
                        'late_fee' => 0,
                        'note' => 'Tenant leave settlement - pro-rata rent (' . $settlement['stay_days'] . ' days)',
                    ]);

                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id' => $rentPayment->id,
                        'user_id' => Auth::id(),
                        'account_type' => 'income',
                        'category' => 'rent_income',
                        'description' => '[Apt ' . $apartmentNumber . '] Leave settlement - pro-rata rent',
                        'amount' => $settlement['pro_rata_rent'],
                        'transaction_date' => $leaveDate,
                        'reference_number' => null,
                        'note' => 'Tenant: ' . $tenant->name . ' - ' . $settlement['stay_days'] . ' days stay',
                    ]);
                }

                // 2a) Mark selected Payments (pending utility/other) as paid and record income
                foreach ($selectedPayments as $charge) {
                    $charge->update([
                        'payment_status' => 'paid',
                        'paid_at'        => $leaveDate,
                        'note'           => ($charge->note ? $charge->note . ' | ' : '') . 'Settled on tenant leave',
                    ]);

                    $category = $charge->payment_type === 'utilities' ? 'utility_income' : 'other_income';

                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id'       => $charge->id,
                        'user_id'          => Auth::id(),
                        'account_type'     => Accounts::TYPE_INCOME,
                        'category'         => $category,
                        'description'      => '[Apt ' . $apartmentNumber . '] Leave settlement - ' . ucfirst($charge->payment_type) . ': ' . ($charge->note ?: '-'),
                        'amount'           => $charge->amount,
                        'transaction_date' => $leaveDate,
                        'reference_number' => null,
                        'note'             => 'Tenant: ' . $tenant->name,
                    ]);

                    // Mirror expense for utility payments (matches checkoutTenant behavior)
                    if ($charge->payment_type === 'utilities') {
                        Accounts::create([
                            'fiscal_period_id' => $activePeriod->id,
                            'payment_id'       => $charge->id,
                            'user_id'          => Auth::id(),
                            'account_type'     => Accounts::TYPE_EXPENSE,
                            'category'         => Accounts::CAT_UTILITIES_EXPENSE,
                            'description'      => '[Apt ' . $apartmentNumber . '] Leave settlement - Utilities expense: ' . ($charge->note ?: '-'),
                            'amount'           => $charge->amount,
                            'transaction_date' => $leaveDate,
                            'reference_number' => null,
                            'note'             => 'Utility expense offset — Tenant: ' . $tenant->name,
                        ]);
                    } else {
                        Accounts::create([
                            'fiscal_period_id' => $activePeriod->id,
                            'payment_id'       => $charge->id,
                            'user_id'          => Auth::id(),
                            'account_type'     => Accounts::TYPE_EXPENSE,
                            'category'         => Accounts::CAT_OTHER_EXPENSE,
                            'description'      => '[Apt ' . $apartmentNumber . '] Leave settlement - Other charge expense: ' . ($charge->note ?: '-'),
                            'amount'           => $charge->amount,
                            'transaction_date' => $leaveDate,
                            'reference_number' => null,
                            'note'             => 'Other charge expense offset — Tenant: ' . $tenant->name,
                        ]);
                    }
                }

                // 2b) Mark selected Utilities as paid and record income per utility type
                $utilityIncomeTypes = ['electricity', 'water'];
                foreach ($selectedUtilities as $util) {
                    $util->update([
                        'paid_status' => true,
                        'paid_at'     => $leaveDate,
                    ]);

                    $category = in_array($util->utility_type, $utilityIncomeTypes)
                        ? 'utility_income'
                        : 'other_income';

                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id'       => null,
                        'user_id'          => Auth::id(),
                        'account_type'     => Accounts::TYPE_INCOME,
                        'category'         => $category,
                        'description'      => '[Apt ' . $apartmentNumber . '] Leave settlement - ' . ucfirst($util->utility_type) . ' ' . Carbon::create($util->billing_year, $util->billing_month)->format('M Y'),
                        'amount'           => $util->charge_amount,
                        'transaction_date' => $leaveDate,
                        'reference_number' => null,
                        'note'             => 'Tenant: ' . $tenant->name,
                    ]);

                    // Mirror expense for each utility charge (matches checkoutTenant behavior)
                    if (in_array($util->utility_type, $utilityIncomeTypes)) {
                        Accounts::create([
                            'fiscal_period_id' => $activePeriod->id,
                            'payment_id'       => null,
                            'user_id'          => Auth::id(),
                            'account_type'     => Accounts::TYPE_EXPENSE,
                            'category'         => Accounts::CAT_UTILITIES_EXPENSE,
                            'description'      => '[Apt ' . $apartmentNumber . '] Leave settlement - ' . ucfirst($util->utility_type) . ' expense ' . Carbon::create($util->billing_year, $util->billing_month)->format('M Y'),
                            'amount'           => $util->charge_amount,
                            'transaction_date' => $leaveDate,
                            'reference_number' => null,
                            'note'             => 'Utility expense offset — Tenant: ' . $tenant->name,
                        ]);
                    } else {
                        Accounts::create([
                            'fiscal_period_id' => $activePeriod->id,
                            'payment_id'       => null,
                            'user_id'          => Auth::id(),
                            'account_type'     => Accounts::TYPE_EXPENSE,
                            'category'         => Accounts::CAT_OTHER_EXPENSE,
                            'description'      => '[Apt ' . $apartmentNumber . '] Leave settlement - ' . ucfirst($util->utility_type) . ' expense ' . Carbon::create($util->billing_year, $util->billing_month)->format('M Y'),
                            'amount'           => $util->charge_amount,
                            'transaction_date' => $leaveDate,
                            'reference_number' => null,
                            'note'             => 'Other charge expense offset — Tenant: ' . $tenant->name,
                        ]);
                    }
                }

                // 3) Record full deposit consumed as expense on tenant leave.
                // deposit_applied = portion used to cover charges; refund_amount = cash returned.
                // Both represent the deposit being returned/consumed, so the full amount is an expense.
                $totalDepositConsumed = ($settlement['deposit_applied'] ?? 0) + ($settlement['refund_amount'] ?? 0);
                if ($totalDepositConsumed > 0) {
                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id' => null,
                        'user_id' => Auth::id(),
                        'account_type' => Accounts::TYPE_EXPENSE,
                        'category' => Accounts::CAT_DEPOSIT_EXPENSE,
                        'description' => '[Apt ' . $apartmentNumber . '] Deposit returned — ' . $tenant->name,
                        'amount' => $totalDepositConsumed,
                        'transaction_date' => $leaveDate,
                        'note' => 'Deposit on leave. Refunded: $' . ($settlement['refund_amount'] ?? 0) . ', Applied to charges: $' . ($settlement['deposit_applied'] ?? 0),
                    ]);
                }

                Log::info('Recorded leave settlement revenue', [
                    'tenant_id'                => $tenant->id,
                    'fiscal_period_id'         => $activePeriod->id,
                    'pro_rata_rent'            => $settlement['pro_rata_rent'],
                    'outstanding_charges_total' => $utilitiesTotal + $otherTotal,
                    'settled_payment_ids'      => $selectedPayments->pluck('id'),
                    'settled_utility_ids'      => $selectedUtilities->pluck('id'),
                    'total_amount_due'         => $settlement['total_amount_due'],
                    'deposit_applied'          => $settlement['deposit_applied'] ?? 0,
                    'refund_amount'            => $settlement['refund_amount'] ?? 0,
                    'deposit_expense_recorded' => $totalDepositConsumed,
                ]);
            } else {
                Log::warning('No active fiscal period found - leave settlement not recorded', [
                    'tenant_id' => $tenant->id,
                    'total_amount_due' => $settlement['total_amount_due'],
                ]);
            }

            // Update rental end date
            $rental->update(['end_date' => $leaveDate]);

            Log::info('Updated rental end date', [
                'rental_id' => $rental->id,
                'end_date' => $leaveDate,
            ]);

            // Save apartment reference before clearing tenant
            $apartment = $tenant->apartment;

            Log::info('Starting tenant archival process', [
                'tenant_id' => $tenant->id,
                'apartment_id' => $apartment?->id,
            ]);

            // Archive tenant (set status to moved_out and archived_at)
            $archiveResult = $this->leaveCalculator->archiveTenant($tenant, now());
            Log::info('Archived tenant', [
                'tenant_id' => $tenant->id,
                'result' => $archiveResult,
            ]);

            // Clear tenant from apartment (remove apartment assignment)
            $clearResult = $this->leaveCalculator->clearTenantFromApartment($tenant);
            Log::info('Cleared tenant from apartment', [
                'tenant_id' => $tenant->id,
                'result' => $clearResult,
            ]);

            // Mark apartment as available (using saved reference)
            if ($apartment) {
                $apartmentResult = $this->leaveCalculator->markApartmentAvailable($apartment);
                Log::info('Marked apartment as available', [
                    'apartment_id' => $apartment->id,
                    'result' => $apartmentResult,
                ]);
            }

            // Soft delete the tenant record (will be preserved in tenant_leaves history)
            $deleteResult = $tenant->delete();
            Log::info('Soft deleted tenant', [
                'tenant_id' => $tenant->id,
                'result' => $deleteResult,
            ]);

            return redirect()
                ->route('admin.tenants.archived')
                ->with('success', 'Tenant leave processed successfully. Settlement created.');

        } catch (\Exception $e) {
            Log::error('Error processing tenant leave: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Error processing leave: ' . $e->getMessage());
        }
    }

    /**
     * Show create tenant form
     */
    public function create(): View
    {
        $apartments = Apartments::all();
        return view('admin.tenantManagement.createTenant', compact('apartments'));
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants|unique:users|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'notes' => 'nullable|string',
        ]);

        // Handle photo upload - SEPARATE from validation
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            try {
                $photoPath = $request->file('photo')->store('tenants', 'public');
                $validated['photo_path'] = $photoPath;
            } catch (\Exception $e) {
                // If photo upload fails, continue without it
                Log::error('Photo upload failed: ' . $e->getMessage());
            }
        }

        // Create a user account for the tenant with default password
        $tenantUser = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make('12345678'),
        ]);
        $tenantUser->assignRole('tenant');

        $validated['user_id'] = $tenantUser->id;
        $tenant = Tenants::create($validated);

        // Auto-record deposit income when a deposit amount is set
        $depositAmount = $validated['deposit'] ?? 0;
        if ($depositAmount > 0) {
            $activePeriod = FiscalPeriods::where('user_id', Auth::id())
                ->where('status', 'open')
                ->orderBy('opening_date', 'desc')
                ->first();

            if ($activePeriod) {
                $apartment = Apartments::find($validated['apartment_id']);
                $aptNumber = $apartment?->apartment_number ?? 'N/A';

                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => null,
                    'user_id' => Auth::id(),
                    'account_type' => Accounts::TYPE_INCOME,
                    'category' => Accounts::CAT_DEPOSIT_INCOME,
                    'description' => '[Apt ' . $aptNumber . '] Security deposit received — ' . $tenant->name,
                    'amount' => $depositAmount,
                    'transaction_date' => $validated['move_in_date'],
                    'note' => 'Deposit collected on move-in',
                ]);
            }
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant created successfully!');
    }

    /**
     * Show tenant details
     */
    public function show(Tenants $tenant): View
    {
        $tenant->load(['apartment', 'rentals', 'utilities']);
        Log::info('Show tenant details', [
            'tenant_id' => $tenant->id,
            'name' => $tenant->name,
            'photo_path' => $tenant->photo_path,
        ]);
        return view('admin.tenantManagement.showTenant', compact('tenant'));
    }

    /**
     * Show edit tenant form
     */
    public function edit(Tenants $tenant): View
    {
        $apartments = Apartments::all();
        return view('admin.tenantManagement.editTenant', compact('tenant', 'apartments'));
    }

    /**
     * Update a tenant
     */
    public function update(Request $request, Tenants $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:tenants,email,' . $tenant->id,
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'notes' => 'nullable|string',
        ]);

        // Handle photo upload - SEPARATE from validation
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            try {
                // Delete old photo if exists
                if ($tenant->photo_path && Storage::disk('public')->exists($tenant->photo_path)) {
                    Storage::disk('public')->delete($tenant->photo_path);
                }
                
                $photoPath = $request->file('photo')->store('tenants', 'public');
                $validated['photo_path'] = $photoPath;
            } catch (\Exception $e) {
                // If photo upload fails, continue without updating photo
                Log::error('Photo update failed: ' . $e->getMessage());
            }
        }

        $tenant->update($validated);

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', 'Tenant updated successfully!');
    }
}

