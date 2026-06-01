<?php

namespace App\Http\Controllers\Supervisor;

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

    /**
     * Get all apartment IDs (supervisor sees same data as admin).
     */
    private function allApartmentIds(): array
    {
        return Apartments::pluck('id')->toArray();
    }

    /**
     * Display active tenants for supervisor's assigned apartments.
     */
    public function index(Request $request): View
    {
        $apartmentIds = $this->allApartmentIds();

        // Get admin's active fiscal period
        $activePeriod = FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
            ->orderBy('opening_date', 'desc')
            ->first();

        $query = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment.floor']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('apartment')) {
            $query->where('apartment_id', $request->apartment);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('floor')) {
            $floorId = $request->floor;
            $query->whereHas('apartment', function (Builder $q) use ($floorId) {
                $q->where('floor_id', $floorId);
            });
        }

        $tenants = $query->orderBy('id', 'desc')->paginate(15);
        $apartments = Apartments::with('floor')->get();

        $rentProgressMap = $this->rentProgressCalculator->map($tenants, $activePeriod);

        // Income summary for the fiscal period (scoped to supervisor's apartments)
        $paymentScope = fn ($q) => $q->whereIn('apartment_id', $apartmentIds);

        $incomeStats = [
            'total_rent_collected' => 0,
            'total_utility_collected' => 0,
            'total_income' => 0,
        ];

        if ($activePeriod) {
            $incomeStats['total_rent_collected'] = Payments::whereHas('rental', $paymentScope)
                ->where('payment_status', 'paid')
                ->where('payment_type', 'rent')
                ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
                ->sum('amount');

            $incomeStats['total_utility_collected'] = Payments::whereHas('rental', $paymentScope)
                ->where('payment_status', 'paid')
                ->where('payment_type', 'utilities')
                ->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date])
                ->sum('amount');

            $incomeStats['total_income'] = $incomeStats['total_rent_collected'] + $incomeStats['total_utility_collected'];
        }

        // Floor data
        $floors = Floors::whereHas('apartments')->orderBy('floor_name')->get();

        $activeTenantCount = Tenants::whereIn('status', ['active', 'pending'])->count();
        $archivedTenantCount = Tenants::onlyTrashed()->count();
        $totalDeposits = Tenants::whereIn('status', ['active', 'pending'])->sum('deposit');

        return view('supervisor.tenants.index', compact(
            'tenants', 'apartments', 'rentProgressMap', 'activePeriod', 'incomeStats', 'floors',
            'activeTenantCount', 'archivedTenantCount', 'totalDeposits'
        ));
    }

    /**
     * Display archived tenants for supervisor's apartments.
     */
    public function archived(Request $request): View
    {
        $query = Tenants::onlyTrashed()
            ->with(['apartment.floor', 'leaves']);

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($floorId = $request->input('floor')) {
            $query->whereHas('apartment.floor', function (Builder $q) use ($floorId) {
                $q->where('id', $floorId);
            });
        }

        $tenants = $query->orderBy('deleted_at', 'desc')->paginate(7)->withQueryString();
        $floors = Floors::orderBy('floor_name')->get();

        $archivedTenantCount = Tenants::onlyTrashed()->count();
        $recentlyArchivedCount = Tenants::onlyTrashed()->where('deleted_at', '>=', now()->subDays(30))->count();
        $totalDeposits = Tenants::onlyTrashed()->sum('deposit');

        return view('supervisor.tenants.archived', compact(
            'tenants',
            'floors',
            'archivedTenantCount',
            'recentlyArchivedCount',
            'totalDeposits'
        ));
    }

    /**
     * Show tenant details.
     */
    public function show(Tenants $tenant): View
    {
        $this->authorizeTenant($tenant);
        $tenant->load(['apartment.floor', 'rentals.apartment', 'rentals.payments', 'utilities']);

        return view('supervisor.tenants.show', compact('tenant'));
    }

    /**
     * Show create tenant form.
     */
    public function create(): View
    {
        $apartments = Apartments::where('status', 'available')
            ->with('floor')
            ->get();

        return view('supervisor.tenants.create', compact('apartments'));
    }

    /**
     * Store a new tenant.
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
            'status' => 'required|in:pending,active',
            'deposit' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            try {
                $photoPath = $request->file('photo')->store('tenants', 'public');
                $validated['photo_path'] = $photoPath;
            } catch (\Exception $e) {
                Log::error('Photo upload failed: '.$e->getMessage());
            }
        }

        // Create a user account for the tenant with default password
        // Do NOT call Hash::make() here — the User model's 'hashed' cast handles it
        $tenantUser = User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => '12345678',
            'account_id' => current_account_id(),
        ]);
        $tenantUser->assignRole('tenant');

        $validated['managed_by'] = Auth::id();
        $validated['user_id'] = $tenantUser->id;
        $tenant = Tenants::create($validated);

        // Update apartment status to occupied
        $apartment = Apartments::findOrFail($validated['apartment_id']);
        $apartment->update(['status' => 'occupied']);

        // Auto-create Rental record
        Rentals::create([
            'apartment_id' => $apartment->id,
            'tenant_id' => $tenant->id,
            'start_date' => Carbon::parse($validated['move_in_date']),
            'end_date' => $validated['move_out_date'] ? Carbon::parse($validated['move_out_date']) : null,
            'rent_amount' => $apartment->monthly_rent,
            'deposit' => $validated['deposit'] ?? 0,
        ]);

        return redirect()->route('supervisor.tenants.index')
            ->with('success', __('messages.flash_tenant_registered'));
    }

    /**
     * Show leave form for a tenant.
     */
    public function leave(Tenants $tenant): View|RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $tenant->load(['apartment', 'rentals']);

        $rental = $tenant->rentals()
            ->where('apartment_id', $tenant->apartment_id)
            ->latest()
            ->first();

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

        return view('supervisor.tenants.leave', compact('tenant', 'rental', 'pendingCharges'));
    }

    /**
     * Process tenant leave and create settlement.
     *
     * Pipeline mirrors the admin flow but uses summary-style accounting
     * (one aggregate rent entry + one aggregate utilities entry) instead of
     * per-payment/per-utility rows. See recordSupervisorLeaveAccounting().
     */
    public function processLeave(ProcessTenantLeaveRequest $request, Tenants $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        try {
            $validated = $request->validated();

            DB::transaction(function () use ($tenant, $validated) {
                $context = $this->leaveProcessor->prepare($tenant, $validated);
                $context['deposit_action'] = $validated['deposit_action'] ?? 'return_deposit';
                $this->leaveProcessor->persist($tenant, $context, $validated['notes'] ?? null);

                $this->recordSupervisorLeaveAccounting($tenant, $context);

                $this->leaveProcessor->finalize($tenant);
            });

            return redirect()
                ->route('supervisor.tenants.archived')
                ->with('success', __('messages.flash_leave_processed'));

        } catch (\Exception $e) {
            Log::error('Supervisor - Error processing tenant leave: '.$e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e,
            ]);

            return back()->with('error', __('messages.flash_leave_error', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Supervisor-side ledger writes for tenant leave.
     *
     * Differs from admin (which writes per-payment/per-utility rows):
     *   - One aggregate rent_income entry for the pro-rata rent
     *   - One aggregate utility_income entry summing all utility charges
     *   - One income row per damage/extra charge (CAT_OTHER_INCOME)
     *   - Deposit refund expense when leftover deposit > 0
     *
     * Writes are owned by the admin's user_id (resolved from the period), since
     * supervisors don't own ledger rows. Skipped silently when no admin period
     * is open.
     */
    private function recordSupervisorLeaveAccounting(Tenants $tenant, array $context): void
    {
        $settlement = $context['settlement'];
        $leaveDate = $context['leave_date'];
        $rental = $context['rental'];
        $selectedPayments = $context['selected_payments'];
        $selectedUtilities = $context['selected_utilities'];
        $extraCharges = $context['extra_charges'] ?? [];
        $depositAction = $context['deposit_action'] ?? 'return_deposit';

        $activePeriod = FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
            ->orderBy('opening_date', 'desc')
            ->first();

        if (! $activePeriod) {
            return;
        }

        $apartmentNumber = $tenant->apartment->apartment_number ?? 'N/A';

        if ($settlement['pro_rata_rent'] > 0) {
            $rentPayment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $settlement['pro_rata_rent'],
                'due_date' => $leaveDate,
                'paid_at' => $leaveDate,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_type' => 'rent',
                'late_fee' => 0,
                'note' => 'Tenant leave settlement - pro-rata rent ('.$settlement['stay_days'].' days)',
            ]);

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $rentPayment->id,
                'user_id' => $activePeriod->user_id,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - pro-rata rent (by supervisor)',
                'amount' => $settlement['pro_rata_rent'],
                'transaction_date' => $leaveDate,
            ]);
        }

        foreach ($selectedPayments as $charge) {
            $charge->update([
                'payment_status' => 'paid',
                'paid_at' => $leaveDate,
                'note' => ($charge->note ? $charge->note.' | ' : '').'Settled on tenant leave',
            ]);
        }

        foreach ($selectedUtilities as $util) {
            $util->update([
                'paid_status' => true,
                'paid_at' => $leaveDate,
            ]);
        }

        $utilityTotal = ($settlement['electricity_charge'] ?? 0)
            + ($settlement['water_charge'] ?? 0)
            + ($settlement['internet_charge'] ?? 0)
            + ($settlement['parking_charge'] ?? 0);

        if ($utilityTotal > 0) {
            $utilityPayment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $utilityTotal,
                'due_date' => $leaveDate,
                'paid_at' => $leaveDate,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_type' => 'utilities',
                'late_fee' => 0,
                'note' => 'Tenant leave settlement - utility charges (by supervisor)',
            ]);

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $utilityPayment->id,
                'user_id' => $activePeriod->user_id,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_UTILITY_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - utilities (by supervisor)',
                'amount' => $utilityTotal,
                'transaction_date' => $leaveDate,
            ]);
        }

        // Damage / extra charges entered on the leave form — booked as other income
        foreach ($extraCharges as $extra) {
            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => null,
                'user_id' => $activePeriod->user_id,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_OTHER_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Leave settlement - Damage/Extra: '.$extra['description'].' (by supervisor)',
                'amount' => $extra['amount'],
                'transaction_date' => $leaveDate,
            ]);
        }

        // Deposit disposition — return or apply as last rent payment
        $depositAmount = (float) ($tenant->deposit ?? 0);
        if ($depositAction === 'last_payment' && $depositAmount > 0) {
            $depositPayment = Payments::create([
                'rental_id' => $rental->id,
                'amount' => $depositAmount,
                'due_date' => $leaveDate,
                'paid_at' => $leaveDate,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_type' => 'rent',
                'late_fee' => 0,
                'note' => 'Deposit applied as last month rent payment on leave (by supervisor)',
            ]);

            Accounts::create([
                'fiscal_period_id' => $activePeriod->id,
                'payment_id' => $depositPayment->id,
                'user_id' => $activePeriod->user_id,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => Accounts::CAT_RENT_INCOME,
                'description' => '[Apt '.$apartmentNumber.'] Deposit as last rent — '.$tenant->name.' (by supervisor)',
                'amount' => $depositAmount,
                'transaction_date' => $leaveDate,
                'note' => 'Deposit kept as last month rent payment (no refund issued)',
            ]);
        } else {
            $refundAmount = $settlement['refund_amount'] ?? 0;
            if ($refundAmount > 0) {
                Accounts::create([
                    'fiscal_period_id' => $activePeriod->id,
                    'payment_id' => null,
                    'user_id' => $activePeriod->user_id,
                    'account_type' => Accounts::TYPE_EXPENSE,
                    'category' => Accounts::CAT_DEPOSIT_EXPENSE,
                    'description' => '[Apt '.$apartmentNumber.'] Deposit refunded — '.$tenant->name.' (by supervisor)',
                    'amount' => $refundAmount,
                    'transaction_date' => $leaveDate,
                ]);
            }
        }
    }

    /**
     * Ensure the tenant belongs to a valid apartment.
     */
    private function authorizeTenant(Tenants $tenant): void
    {
        // Supervisors can manage tenants across all apartments
    }

    /**
     * Show edit form for a tenant.
     */
    public function edit(Tenants $tenant): View
    {
        $this->authorizeTenant($tenant);

        $apartments = Apartments::where(function ($q) use ($tenant) {
            $q->where('status', 'available')
                ->orWhere('id', $tenant->apartment_id);
        })
            ->get();

        return view('supervisor.tenants.edit', compact('tenant', 'apartments'));
    }

    /**
     * Update a tenant.
     */
    public function update(Request $request, Tenants $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:tenants,phone,'.$tenant->id,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active',
            'deposit' => 'nullable|numeric|min:0',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'notes' => 'nullable|string',
        ]);

        // Handle apartment change
        $oldApartmentId = $tenant->apartment_id;
        $newApartmentId = $validated['apartment_id'];

        if ($oldApartmentId != $newApartmentId) {
            // Free old apartment
            Apartments::where('id', $oldApartmentId)->update(['status' => 'available']);
            // Occupy new apartment
            Apartments::where('id', $newApartmentId)->update(['status' => 'occupied']);

            // Update active rental
            $activeRental = Rentals::where('tenant_id', $tenant->id)
                ->where('apartment_id', $oldApartmentId)
                ->whereNull('end_date')
                ->orWhere('end_date', '>=', now())
                ->latest()
                ->first();

            if ($activeRental) {
                $activeRental->update(['end_date' => now()]);
            }

            $newApartment = Apartments::find($newApartmentId);
            Rentals::create([
                'apartment_id' => $newApartmentId,
                'tenant_id' => $tenant->id,
                'start_date' => Carbon::parse($validated['move_in_date']),
                'end_date' => $validated['move_out_date'] ? Carbon::parse($validated['move_out_date']) : null,
                'rent_amount' => $newApartment->monthly_rent,
                'deposit' => $validated['deposit'] ?? 0,
            ]);
        }

        // Handle photo upload
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            try {
                if ($tenant->photo_path && Storage::disk('public')->exists($tenant->photo_path)) {
                    Storage::disk('public')->delete($tenant->photo_path);
                }
                $photoPath = $request->file('photo')->store('tenants', 'public');
                $validated['photo_path'] = $photoPath;
            } catch (\Exception $e) {
                Log::error('Photo update failed: '.$e->getMessage());
            }
        }

        $tenant->update($validated);

        return redirect()->route('supervisor.tenants.show', $tenant->id)
            ->with('success', __('messages.flash_tenant_updated'));
    }

    /**
     * Delete (soft-delete) a tenant.
     */
    public function destroy(Tenants $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        // Free the apartment
        if ($tenant->apartment_id) {
            Apartments::where('id', $tenant->apartment_id)->update(['status' => 'available']);
        }

        // End active rental
        Rentals::where('tenant_id', $tenant->id)
            ->where('apartment_id', $tenant->apartment_id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->update(['end_date' => now()]);

        $tenant->update([
            'status' => 'inactive',
            'archived_at' => now(),
        ]);

        $tenant->delete();

        return redirect()->route('supervisor.tenants.index')
            ->with('success', __('messages.flash_tenant_removed'));
    }
}
