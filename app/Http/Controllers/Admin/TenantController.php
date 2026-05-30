<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\ProcessTenantLeaveRequest;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Tenants\TenantLeaveProcessor;
use App\Services\Tenants\TenantPendingChargesQuery;
use App\Services\Tenants\TenantRentProgressCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(
        protected TenantLeaveProcessor $leaveProcessor,
        protected TenantPendingChargesQuery $pendingChargesQuery,
        protected TenantRentProgressCalculator $rentProgressCalculator,
    ) {}

    public function index(Request $request): View
    {
        $query = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment']);

        // Search filter
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apartment filter
        if ($request->has('apartment') && ! empty($request->apartment)) {
            $query->where('apartment_id', $request->apartment);
        }

        // Status filter
        if ($request->has('status') && ! empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Floor filter (filter tenants by apartment's floor)
        if ($request->has('floor') && ! empty($request->floor)) {
            $floorId = $request->floor;
            $query->whereHas('apartment', function (Builder $q) use ($floorId) {
                $q->where('floor_id', $floorId);
            });
        }

        $tenants = $query->orderBy('id', 'desc')->paginate(15);
        $apartments = Apartments::all();
        $floors = Floors::whereHas('apartments')->orderBy('floor_name')->get();

        $rentProgressMap = $this->rentProgressCalculator->map($tenants);

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
                    ->orWhere('phone', 'like', "%{$search}%");
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
        if (! $tenant) {
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
        if (! $rental) {
            $rental = new Rentals;
            $rental->id = null;
            $rental->apartment_id = $tenant->apartment_id;
            $rental->tenant_id = $tenant->id;
            $rental->rent_amount = $tenant->apartment?->monthly_rent ?? 0;
            $rental->start_date = $tenant->move_in_date;
            $rental->end_date = null;
        }

        $pendingCharges = $this->pendingChargesQuery->forRental($rental);

        return view('admin.tenantManagement.leave', compact('tenant', 'rental', 'pendingCharges'));
    }

    /**
     * Process tenant leave and create settlement.
     *
     * Pipeline:
     *   1. Validate input
     *   2. processor->prepare() — resolve rental, parse charges, compute settlement
     *   3. processor->persist() — write TenantLeave row + stamp rental.end_date
     *   4. recordAdminLeaveAccounting() — admin-specific ledger writes
     *      (per-payment, per-utility income; deposit refund expense)
     *   5. processor->finalize() — archive tenant + free apartment + suspend user
     */
    public function processLeave(ProcessTenantLeaveRequest $request, Tenants $tenant)
    {
        try {
            $validated = $request->validated();

            DB::transaction(function () use ($tenant, $validated) {
                $context = $this->leaveProcessor->prepare($tenant, $validated);
                $context['deposit_action'] = $validated['deposit_action'] ?? 'return_deposit';
                $this->leaveProcessor->persist($tenant, $context, $validated['notes'] ?? null);

                $this->recordAdminLeaveAccounting($tenant, $context);

                $this->leaveProcessor->finalize($tenant);
            });

            return redirect()
                ->route('admin.tenants.archived')
                ->with('success', 'Tenant leave processed successfully. Settlement created.');

        } catch (\Exception $e) {
            Log::error('Error processing tenant leave: '.$e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e,
            ]);

            return back()->with('error', 'Error processing leave: '.$e->getMessage());
        }
    }

    /**
     * Admin-specific ledger writes for tenant leave.
     *
     * Differs from supervisor (which records summary aggregates only):
     *   - Per-payment income row for each selected pending charge
     *   - Per-utility income row split by type (electricity/water → utility_income;
     *     internet/parking/trash/other → other_income)
     *   - A deposit-refund expense entry (cash returned to tenant) so the
     *     original deposit_income is offset on the books
     *
     * Skipped silently when no fiscal period is open (the leave still proceeds
     * but the settlement is not recorded — see Log::warning below).
     */
    private function recordAdminLeaveAccounting(Tenants $tenant, array $context): void
    {
        $settlement = $context['settlement'];
        $leaveDate = $context['leave_date'];
        $rental = $context['rental'];
        $selectedPayments = $context['selected_payments'];
        $selectedUtilities = $context['selected_utilities'];
        $extraCharges = $context['extra_charges'] ?? [];
        $depositAction = $context['deposit_action'] ?? 'return_deposit';

        $activePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();

        if (! $activePeriod) {
            Log::warning('No active fiscal period found - leave settlement not recorded', [
                'tenant_id' => $tenant->id,
                'total_amount_due' => $settlement['total_amount_due'],
            ]);

            return;
        }

        $apartmentNumber = $tenant->apartment->apartment_number ?? 'N/A';

        // 1) Pro-rata rent payment + income entry
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
                'note' => 'Tenant leave settlement - pro-rata rent ('.$settlement['stay_days'].' days)',
            ]);

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $rentPayment->id,
                'user_id' => Auth::id(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - pro-rata rent',
                'amount' => $settlement['pro_rata_rent'],
                'transaction_date' => $leaveDate,
                'note' => 'Tenant: '.$tenant->name.' - '.$settlement['stay_days'].' days stay',
            ]);
        }

        // 2a) Mark selected Payments paid + record per-row income
        foreach ($selectedPayments as $charge) {
            $charge->update([
                'payment_status' => 'paid',
                'paid_at' => $leaveDate,
                'note' => ($charge->note ? $charge->note.' | ' : '').'Settled on tenant leave',
            ]);

            $category = $charge->payment_type === 'utilities'
                ? Accounts::CAT_UTILITY_INCOME
                : Accounts::CAT_OTHER_INCOME;

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $charge->id,
                'user_id' => Auth::id(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => $category,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - '.ucfirst($charge->payment_type).': '.($charge->note ?: '-'),
                'amount' => $charge->amount,
                'transaction_date' => $leaveDate,
                'note' => 'Tenant: '.$tenant->name,
            ]);
        }

        // 2b) Mark selected Utilities paid + record per-utility income (split by type)
        $utilityIncomeTypes = ['electricity', 'water'];
        foreach ($selectedUtilities as $util) {
            $util->update([
                'paid_status' => true,
                'paid_at' => $leaveDate,
            ]);

            $category = in_array($util->utility_type, $utilityIncomeTypes, true)
                ? Accounts::CAT_UTILITY_INCOME
                : Accounts::CAT_OTHER_INCOME;

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => null,
                'user_id' => Auth::id(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => $category,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - '.ucfirst($util->utility_type).' '.Carbon::create($util->billing_year, $util->billing_month)->format('M Y'),
                'amount' => $util->charge_amount,
                'transaction_date' => $leaveDate,
                'note' => 'Tenant: '.$tenant->name,
            ]);
        }

        // 2c) Extra/damage charges entered on the leave form — booked as other income
        foreach ($extraCharges as $extra) {
            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => null,
                'user_id' => Auth::id(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_OTHER_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - Damage/Extra: '.$extra['description'],
                'amount' => $extra['amount'],
                'transaction_date' => $leaveDate,
                'note' => 'Tenant: '.$tenant->name.' - deducted from deposit on leave',
            ]);
        }

        // 3) Deposit disposition — return or apply as last rent payment
        $depositAmount = (float) ($tenant->deposit ?? 0);
        if ($depositAction === 'last_payment' && $depositAmount > 0) {
            // Deposit is kept as the last month's rent payment — record as rent income
            $depositPayment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $depositAmount,
                'due_date' => $leaveDate,
                'paid_at' => $leaveDate,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_type' => 'rent',
                'transaction_reference' => null,
                'late_fee' => 0,
                'note' => 'Deposit applied as last month rent payment on leave',
            ]);

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $depositPayment->id,
                'user_id' => Auth::id(),
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Deposit as last rent — '.$tenant->name,
                'amount' => $depositAmount,
                'transaction_date' => $leaveDate,
                'note' => 'Deposit kept as last month rent payment (no refund issued)',
            ]);
        } else {
            // return_deposit: refund surplus deposit to tenant, recorded as expense
            $refundAmount = $settlement['refund_amount'] ?? 0;
            if ($refundAmount > 0) {
                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => null,
                    'user_id' => Auth::id(),
                    'account_type' => Accounts::TYPE_EXPENSE,
                    'category' => Accounts::CAT_DEPOSIT_EXPENSE,
                    'description' => '[Apt '.$apartmentNumber.'] Deposit refunded — '.$tenant->name,
                    'amount' => $refundAmount,
                    'transaction_date' => $leaveDate,
                    'note' => 'Deposit refund on leave. Applied to charges: $'.($settlement['deposit_applied'] ?? 0),
                ]);
            }
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
            'phone' => 'required|string|max:20|unique:tenants,phone|unique:users,phone',
            'email' => 'nullable|email|max:255',
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
                Log::error('Photo upload failed: '.$e->getMessage());
            }
        }

        // Create a user account for the tenant with default password
        // Do NOT call Hash::make() here — the User model's 'hashed' cast handles it
        $tenantUser = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => '12345678',
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
                    'description' => '[Apt '.$aptNumber.'] Security deposit received — '.$tenant->name,
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
        $tenant->load(['apartment.floor', 'rentals.apartment', 'rentals.payments', 'utilities']);

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
            'phone' => 'required|string|max:20|unique:tenants,phone,'.$tenant->id,
            'email' => 'nullable|email|max:255',
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
                Log::error('Photo update failed: '.$e->getMessage());
            }
        }

        $tenant->update($validated);

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', 'Tenant updated successfully!');
    }
}
