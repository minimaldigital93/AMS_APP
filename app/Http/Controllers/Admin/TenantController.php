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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(
        protected TenantLeaveProcessor $leaveProcessor,
        protected TenantPendingChargesQuery $pendingChargesQuery,
        protected TenantRentProgressCalculator $rentProgressCalculator,
    ) {}

    /**
     * Constrain archived (apartment_id-cleared) tenants to the active property.
     * They keep their property linkage only through leave history, so match on
     * either a still-set apartment or the apartment recorded on a leave row.
     */
    private function scopeArchivedToActiveProperty(Builder $query, int|null|false $propertyId = false): Builder
    {
        // `false` = caller didn't specify → fall back to the globally active
        // property. An explicit null means "no narrowing" (the consolidated view).
        if ($propertyId === false) {
            $propertyId = current_property_id();
        }

        if ($propertyId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($propertyId) {
            $q->whereHas('apartment.floor', fn (Builder $s) => $s->where('property_id', $propertyId))
                ->orWhereHas('leaves.apartment.floor', fn (Builder $s) => $s->where('property_id', $propertyId));
        });
    }

    public function index(Request $request, \App\Services\Property\PropertyContext $propertyContext): View
    {
        // When the top-bar is on "All properties", offer a per-page property
        // filter so the user can narrow to one building without changing the
        // global selection. Otherwise everything stays scoped to the active one.
        $showingAll = $propertyContext->showingAllProperties();
        $properties = collect();
        $selectedPropertyId = null;

        if ($showingAll) {
            $properties = $propertyContext->accessibleProperties();
            $requested = $request->integer('property') ?: null;

            if ($requested !== null && $properties->contains('id', $requested)) {
                $selectedPropertyId = $requested;
            }
        }

        // Effective property scope: the per-page filter in "all" mode (null = no
        // narrowing), otherwise the globally active property.
        $scopeId = $showingAll ? $selectedPropertyId : current_property_id();

        $query = Tenants::whereIn('status', ['active', 'pending'])
            ->with(['apartment.floor.property'])
            ->forProperty($scopeId);

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

        $tenants = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $apartments = Apartments::forProperty($scopeId)->get();
        $floors = Floors::forProperty($scopeId)->whereHas('apartments')->orderBy('id', 'asc')->get();

        $rentProgressMap = $this->rentProgressCalculator->map($tenants);

        // Statistics counts (across all records, not just current page), scoped to
        // the effective property. Archived tenants have apartment_id cleared, so they
        // are matched through their leave history.
        $activeTenantCount = Tenants::where('status', 'active')->forProperty($scopeId)->count();
        $archivedTenantCount = $this->scopeArchivedToActiveProperty(Tenants::onlyTrashed(), $scopeId)->count();
        $totalDeposits = Tenants::where('status', 'active')->forProperty($scopeId)->sum('deposit');

        return view('admin.TenantManagement.activeTenants', compact('tenants', 'apartments', 'rentProgressMap', 'floors', 'activeTenantCount', 'archivedTenantCount', 'totalDeposits', 'showingAll', 'properties', 'selectedPropertyId'));
    }

    /**
     * Display archived tenants (soft deleted)
     */
    public function archived(Request $request): View
    {
        $query = $this->scopeArchivedToActiveProperty(
            Tenants::onlyTrashed()->with(['apartment.floor', 'leaves.apartment.floor'])
        );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Archived tenants have apartment_id cleared, so match on the apartment
        // recorded in their leave history as well as any current apartment.
        if ($floorId = $request->input('floor')) {
            $query->where(function ($q) use ($floorId) {
                $q->whereHas('apartment.floor', function ($sub) use ($floorId) {
                    $sub->where('id', $floorId);
                })->orWhereHas('leaves.apartment.floor', function ($sub) use ($floorId) {
                    $sub->where('id', $floorId);
                });
            });
        }

        $tenants = $query->orderBy('deleted_at', 'desc')->paginate(7)->withQueryString();
        $floors = Floors::forActiveProperty()->orderBy('floor_name')->get();

        $archivedTenantCount = $this->scopeArchivedToActiveProperty(Tenants::onlyTrashed())->count();
        $recentlyArchivedCount = $this->scopeArchivedToActiveProperty(Tenants::onlyTrashed()->where('deleted_at', '>=', now()->subDays(30)))->count();
        $totalDeposits = $this->scopeArchivedToActiveProperty(Tenants::onlyTrashed())->sum('deposit');

        return view('admin.TenantManagement.archivedTenants', compact('tenants', 'floors', 'archivedTenantCount', 'recentlyArchivedCount', 'totalDeposits'));
    }

    /**
     * Show leave form for a tenant
     */
    public function leave(Tenants $tenant): View|RedirectResponse
    {
        // Check if tenant exists
        if (! $tenant) {
            return redirect()->route('admin.tenants.index')
                ->with('error', __('messages.flash_tenant_not_found'));
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

        return view('admin.TenantManagement.leave', compact('tenant', 'rental', 'pendingCharges'));
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
                ->with('success', __('messages.flash_leave_processed_settlement'));

        } catch (\Exception $e) {
            Log::error('Error processing tenant leave: '.$e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e,
            ]);

            return back()->with('error', __('messages.flash_leave_error', ['error' => $e->getMessage()]));
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
        // Attribute every settlement entry to the apartment's property so it
        // lands in the right building's books (payment-linked rows self-derive
        // via Accounts' creating hook; the payment-less rows below need it set).
        $propertyId = $tenant->apartment?->floor?->property_id ?? $tenant->apartment?->property_id;

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
                'property_id' => $propertyId,
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
                'property_id' => $propertyId,
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
                    'property_id' => $propertyId,
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
        $apartments = Apartments::where('status', 'available')
            ->with('floor')
            ->get();

        return view('admin.TenantManagement.createTenant', compact('apartments'));
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request): RedirectResponse
    {
        $minBirthDate = now()->subYears(18)->toDateString();
        $minMoveInDate = now()->subDays(3)->toDateString();

        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'phone' => [
                'required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/',
                // Per-account uniqueness so each admin's tenants are independent.
                Rule::unique('tenants', 'phone')->where('account_id', current_account_id())->whereNull('deleted_at'),
                Rule::unique('users', 'phone')->where('account_id', current_account_id()),
            ],
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before_or_equal:'.$minBirthDate,
            'move_in_date' => 'required|date|after_or_equal:'.$minMoveInDate,
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0',
            'photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,heic,heif|max:10240',
            'notes' => 'nullable|string',
        ], [
            'phone.unique' => __('messages.validation_phone_taken'),
            'phone.regex' => __('messages.phone_must_be_english'),
            'date_of_birth.before_or_equal' => __('messages.tenant_must_be_18'),
            'move_in_date.after_or_equal' => __('messages.move_in_date_min'),
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
            'account_id' => current_account_id(),
        ]);
        $tenantUser->assignRole('tenant');

        $validated['user_id'] = $tenantUser->id;
        $tenant = Tenants::create($validated);

        // Update apartment status to occupied
        $apartment = Apartments::findOrFail($validated['apartment_id']);
        $apartment->update(['status' => 'occupied']);

        // Auto-create Rental record
        $rental = Rentals::create([
            'apartment_id' => $apartment->id,
            'tenant_id' => $tenant->id,
            'start_date' => Carbon::parse($validated['move_in_date']),
            'end_date' => $validated['move_out_date'] ? Carbon::parse($validated['move_out_date']) : null,
            'rent_amount' => $apartment->monthly_rent,
            'deposit' => $validated['deposit'] ?? 0,
        ]);

        // Auto-record deposit income when a deposit amount is set
        $depositAmount = $validated['deposit'] ?? 0;
        if ($depositAmount > 0) {
            $activePeriod = FiscalPeriods::where('user_id', Auth::id())
                ->where('status', 'open')
                ->orderBy('opening_date', 'desc')
                ->first();

            if ($activePeriod) {
                $reference = 'deposit:rental:'.$rental->id;

                Accounts::firstOrCreate(
                    ['reference_number' => $reference],
                    [
                        'fiscal_period_id' => $activePeriod->id,
                        'property_id' => $apartment->property_id ?? $apartment->floor?->property_id,
                        'payment_id' => null,
                        'user_id' => Auth::id(),
                        'account_type' => Accounts::TYPE_INCOME,
                        'category' => Accounts::CAT_DEPOSIT_INCOME,
                        'description' => '[Apt '.$apartment->apartment_number.'] Security deposit received — '.$tenant->name,
                        'amount' => $depositAmount,
                        'transaction_date' => $validated['move_in_date'],
                        'note' => 'Deposit collected on move-in',
                        'reference_number' => $reference,
                    ]
                );
            }
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', __('messages.flash_tenant_created'));
    }

    /**
     * Show tenant details
     */
    public function show(Tenants $tenant): View
    {
        $tenant->load(['apartment.floor', 'rentals.apartment', 'rentals.payments', 'utilities']);

        return view('admin.TenantManagement.showTenant', compact('tenant'));
    }

    /**
     * Show edit tenant form
     */
    public function edit(Tenants $tenant): View
    {
        $apartments = Apartments::where(function ($q) use ($tenant) {
            $q->where('status', 'available')
                ->orWhere('id', $tenant->apartment_id);
        })
            ->get();

        return view('admin.TenantManagement.editTenant', compact('tenant', 'apartments'));
    }

    /**
     * Update a tenant
     */
    public function update(Request $request, Tenants $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'apartment_id' => 'required|exists:apartments,id',
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('tenants', 'phone')->ignore($tenant->id)->where('account_id', current_account_id())->whereNull('deleted_at'),
            ],
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0',
            'photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,heic,heif|max:10240',
            'notes' => 'nullable|string',
        ], [
            'phone.unique' => __('messages.validation_phone_taken'),
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
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
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
                'end_date' => null,
                'rent_amount' => $newApartment->monthly_rent,
                'deposit' => $validated['deposit'] ?? 0,
            ]);
        }

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
            ->with('success', __('messages.flash_tenant_updated'));
    }
}
