<?php

namespace App\Http\Controllers\Supervisor;

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
            ->whereIn('apartment_id', $apartmentIds)
            ->with(['apartment.floor']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('apartment')) {
            $query->where('apartment_id', $request->apartment);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tenants = $query->orderBy('id', 'desc')->paginate(15);
        $apartments = Apartments::whereIn('id', $apartmentIds)->with('floor')->get();

        // Build rent progress for each tenant (within fiscal period or current month fallback)
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $rentProgressMap = [];

        foreach ($tenants as $tenant) {
            $rental = Rentals::where('tenant_id', $tenant->id)
                ->where(function ($q) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->with(['payments' => function ($q) use ($activePeriod, $currentMonth, $currentYear) {
                    $q->where('payment_type', 'rent')
                      ->where('payment_status', 'paid');
                    if ($activePeriod) {
                        $q->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
                    } else {
                        $q->whereMonth('paid_at', $currentMonth)
                          ->whereYear('paid_at', $currentYear);
                    }
                }])
                ->latest('start_date')
                ->first();

            if ($rental) {
                $paidAmount = $rental->payments->sum('amount');
                $monthlyRent = $rental->rent_amount;
                $paidDate = $rental->payments->first()?->paid_at;

                $monthStart = Carbon::create($currentYear, $currentMonth, 1)->startOfDay();
                $totalDaysInMonth = $monthStart->daysInMonth;

                $rentalStart = Carbon::parse($rental->start_date)->startOfDay();
                $stayStart = $rentalStart->gt($monthStart) ? $rentalStart : $monthStart;
                $stayEnd = now()->gt($monthStart->copy()->endOfMonth()) ? $monthStart->copy()->endOfMonth() : now();
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

        // Income summary for the fiscal period (scoped to supervisor's apartments)
        $paymentScope = fn($q) => $q->whereIn('apartment_id', $apartmentIds);

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

        return view('supervisor.tenants.index', compact(
            'tenants', 'apartments', 'rentProgressMap', 'activePeriod', 'incomeStats', 'floors'
        ));
    }

    /**
     * Display archived tenants for supervisor's apartments.
     */
    public function archived(Request $request): View
    {
        $apartmentIds = $this->allApartmentIds();

        $query = Tenants::onlyTrashed()
            ->whereIn('apartment_id', $apartmentIds)
            ->with(['apartment.floor', 'leaves']);

        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($floorId = $request->input('floor')) {
            $query->whereHas('apartment.floor', function (Builder $q) use ($floorId) {
                $q->where('id', $floorId);
            });
        }

        $tenants = $query->orderBy('deleted_at', 'desc')->paginate(7)->withQueryString();
        $floors = Floors::orderBy('floor_name')->get();

        $archivedScope = Tenants::onlyTrashed()->whereIn('apartment_id', $apartmentIds);
        $archivedTenantCount = (clone $archivedScope)->count();
        $recentlyArchivedCount = (clone $archivedScope)->where('deleted_at', '>=', now()->subDays(30))->count();
        $totalDeposits = (clone $archivedScope)->sum('deposit');

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
        $tenant->load(['apartment', 'rentals', 'utilities']);

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
            'email' => 'required|email|unique:tenants|unique:users|max:255',
            'phone' => 'required|string|max:20',
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
                Log::error('Photo upload failed: ' . $e->getMessage());
            }
        }

        // Create a user account for the tenant with default password
        // Do NOT call Hash::make() here — the User model's 'hashed' cast handles it
        $tenantUser = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => '12345678',
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
            ->with('success', 'Tenant registered successfully!');
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

        return view('supervisor.tenants.leave', compact('tenant', 'rental', 'pendingCharges'));
    }

    /**
     * Process tenant leave and create settlement.
     */
    public function processLeave(Request $request, Tenants $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        try {
            $tenant->load(['apartment', 'rentals']);

            $rental = $tenant->rentals()
                ->where('apartment_id', $tenant->apartment_id)
                ->where(function ($query) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>', now());
                })
                ->latest()
                ->first();

            if (!$rental) {
                $rental = Rentals::create([
                    'apartment_id' => $tenant->apartment_id,
                    'tenant_id' => $tenant->id,
                    'rent_amount' => $tenant->apartment?->monthly_rent ?? 0,
                    'start_date' => $tenant->move_in_date,
                    'end_date' => null,
                ]);
            }

            $validated = $request->validate([
                'leave_date'        => 'required|date|after_or_equal:' . $tenant->move_in_date->format('Y-m-d'),
                'charge_full_month' => 'nullable|boolean',
                'charge_ids'        => 'nullable|array',
                'charge_ids.*'      => 'string',
                'notes'             => 'nullable|string',
            ]);

            $leaveDate = Carbon::parse($validated['leave_date']);

            $chargeFullMonth = $request->boolean('charge_full_month');
            $proRataRent = $chargeFullMonth
                ? (float) $rental->rent_amount
                : $this->leaveCalculator->calculateProRataRent($rental, $leaveDate);

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
                $rental, $tenant, $leaveDate, $charges, $tenant->deposit ?? 0
            );

            TenantLeave::create([
                'tenant_id' => $tenant->id,
                'rental_id' => $rental->id,
                'apartment_id' => $tenant->apartment_id,
                'leave_date' => $leaveDate,
                'original_move_out_date' => $rental->end_date,
                'stay_days' => $settlement['stay_days'],
                'pro_rata_rent' => $settlement['pro_rata_rent'],
                'electricity_charge' => $settlement['electricity_charge'],
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

            // Record in fiscal period if admin has one open
            $activePeriod = FiscalPeriods::where('status', 'open')
                ->whereHas('user', function ($q) {
                    $q->role('admin');
                })
                ->orderBy('opening_date', 'desc')
                ->first();

            if ($activePeriod && $settlement['total_amount_due'] > 0) {
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
                        'note' => 'Tenant leave settlement - pro-rata rent (' . $settlement['stay_days'] . ' days)',
                    ]);

                    Accounts::create([
                        'fiscal_period_id' => $activePeriod->id,
                        'payment_id' => $rentPayment->id,
                        'user_id' => $activePeriod->user_id,
                        'account_type' => 'income',
                        'category' => 'rent_income',
                        'description' => '[Apt ' . $apartmentNumber . '] Leave settlement - pro-rata rent (by supervisor)',
                        'amount' => $settlement['pro_rata_rent'],
                        'transaction_date' => $leaveDate,
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
                        'account_type' => 'income',
                        'category' => 'utility_income',
                        'description' => '[Apt ' . $apartmentNumber . '] Leave settlement - utilities (by supervisor)',
                        'amount' => $utilityTotal,
                        'transaction_date' => $leaveDate,
                    ]);
                }
            }

            $rental->update(['end_date' => $leaveDate]);

            $apartment = $tenant->apartment;
            $this->leaveCalculator->archiveTenant($tenant, now());
            $this->leaveCalculator->clearTenantFromApartment($tenant);

            if ($apartment) {
                $this->leaveCalculator->markApartmentAvailable($apartment);
            }

            $tenant->delete();

            return redirect()
                ->route('supervisor.tenants.archived')
                ->with('success', 'Tenant leave processed successfully.');

        } catch (\Exception $e) {
            Log::error('Supervisor - Error processing tenant leave: ' . $e->getMessage(), [
                'tenant_id' => $tenant->id,
                'exception' => $e,
            ]);
            return back()->with('error', 'Error processing leave: ' . $e->getMessage());
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
            'email' => 'required|email|max:255|unique:tenants,email,' . $tenant->id,
            'phone' => 'required|string|max:20',
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
                Log::error('Photo update failed: ' . $e->getMessage());
            }
        }

        $tenant->update($validated);

        return redirect()->route('supervisor.tenants.show', $tenant->id)
            ->with('success', 'Tenant updated successfully!');
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
            ->with('success', 'Tenant removed successfully.');
    }
}
