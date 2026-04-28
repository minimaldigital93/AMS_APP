<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApartmentController extends Controller
{
    /**
     * Display all apartments grouped by floor.
     */
    public function index(Request $request): View
    {
        $query = Apartments::with(['floor', 'tenants', 'supervisor']);

        if ($request->filled('search')) {
            $query->where('apartment_number', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $apartments = $query->get();

        $apartmentsByFloor = $apartments->filter(fn($apt) => $apt->floor !== null)
            ->groupBy(fn($apt) => $apt->floor->id)
            ->sortBy(fn($group) => $group->first()->floor->id);

        $floors = Floors::whereHas('apartments')
            ->orderBy('id', 'asc')->get();

        $statuses = Apartments::getStatuses();

        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('supervisor.apartments.index', compact('apartmentsByFloor', 'floors', 'statuses', 'apartments', 'availableTenants'));
    }

    /**
     * Show apartment details with rent payment progress.
     */
    public function show(Apartments $apartment): View
    {
        $this->authorizeApartment($apartment);

        $apartment->load('floor', 'supervisor');

        $activePeriod = $this->activeAdminFiscalPeriod();

        $activeRental = Rentals::where('apartment_id', $apartment->id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->with(['tenant', 'payments' => function ($q) use ($activePeriod) {
                $q->where('payment_type', 'rent')
                    ->where('payment_status', 'paid');
                if ($activePeriod) {
                    $q->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
                }
                $q->orderBy('paid_at', 'asc');
            }])
            ->latest('start_date')
            ->first();

        $rentProgress = [];

        if ($activeRental) {
            // Clamp dates to fiscal period if available
            if ($activePeriod) {
                $startDate = Carbon::parse($activePeriod->opening_date)->startOfMonth();
                $endDate = Carbon::parse($activePeriod->closing_date)->endOfMonth();
            } else {
                $startDate = Carbon::parse($activeRental->start_date)->startOfMonth();
                $endDate = $activeRental->end_date
                    ? Carbon::parse($activeRental->end_date)->endOfMonth()
                    : now()->endOfMonth();
            }
            $monthlyRent = $activeRental->rent_amount;

            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $month = $current->month;
                $year = $current->year;

                $monthPayments = $activeRental->payments->filter(function ($p) use ($month, $year) {
                    return Carbon::parse($p->paid_at)->month === $month
                        && Carbon::parse($p->paid_at)->year === $year;
                });

                $paidAmount = $monthPayments->sum('amount');
                $lateFees = $monthPayments->sum('late_fee');
                $paidDate = $monthPayments->first()?->paid_at;
                $percent = $monthlyRent > 0 ? min(round(($paidAmount / $monthlyRent) * 100, 1), 100) : 0;

                $isPast = $current->copy()->endOfMonth()->lt(now());
                $isCurrent = $current->month === now()->month && $current->year === now()->year;

                $status = 'upcoming';
                if ($percent >= 100) {
                    $status = 'paid';
                } elseif ($percent > 0) {
                    $status = 'partial';
                } elseif ($isPast) {
                    $status = 'overdue';
                } elseif ($isCurrent) {
                    $status = 'due';
                }

                $rentProgress[] = [
                    'month' => $current->format('M'),
                    'year' => $current->format('Y'),
                    'label' => $current->format('M Y'),
                    'rent' => $monthlyRent,
                    'paid' => $paidAmount,
                    'late_fee' => $lateFees,
                    'percent' => $percent,
                    'paid_date' => $paidDate ? Carbon::parse($paidDate)->format('M d') : null,
                    'status' => $status,
                    'is_current' => $isCurrent,
                ];

                $current->addMonth();
            }
        }

        $totalPaid = collect($rentProgress)->sum('paid');
        $totalExpected = collect($rentProgress)->sum('rent');
        $overallPercent = $totalExpected > 0 ? round(($totalPaid / $totalExpected) * 100, 1) : 0;

        return view('supervisor.apartments.show', compact(
            'apartment', 'activeRental', 'rentProgress',
            'totalPaid', 'totalExpected', 'overallPercent', 'activePeriod'
        ));
    }

    /**
     * Assign tenant to an available apartment.
     */
    public function assignTenant(Request $request, Apartments $apartment)
    {
        $this->authorizeApartment($apartment);

        $validated = $request->validate([
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => 'nullable|required_if:tenant_option,existing|exists:tenants,id',
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'email' => [
                'nullable',
                'required_if:tenant_option,new',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where(fn ($q) => $request->input('tenant_option') === 'new'),
                Rule::unique('tenants', 'email')->where(fn ($q) => $request->input('tenant_option') === 'new'),
            ],
            'phone' => 'required_if:tenant_option,new|nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before:today',
            'attached_photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,pdf|max:5120',
            'id_pdf' => 'nullable|file|mimes:pdf,jpeg,jpg,png,gif|max:5120',
            'move_in_date' => 'required|date|before_or_equal:today',
            'deposit' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $apartment, $validated) {
            // Lock the apartment row so two concurrent assignments can't both succeed.
            $apartment = Apartments::whereKey($apartment->id)->lockForUpdate()->firstOrFail();

            if ($apartment->status !== 'available') {
                return back()->with('error', 'This apartment is not available for assignment.');
            }

            $photoPath = null;
            $documentPath = null;

            // Only accept uploads when creating a new tenant — prevents accidental
            // overwrite of an existing tenant's photo/document via crafted requests.
            if ($validated['tenant_option'] === 'new') {
                if ($request->hasFile('attached_photo')) {
                    $photoPath = $request->file('attached_photo')->store('tenants', 'public');
                }
                if ($request->hasFile('id_pdf')) {
                    $documentPath = $request->file('id_pdf')->store('tenants/id_documents', 'public');
                }
            }

            if ($validated['tenant_option'] === 'existing') {
                $tenant = Tenants::findOrFail($validated['tenant_id']);

                if (!$tenant->user_id && $tenant->email) {
                    $existingUser = User::firstOrCreate(
                        ['email' => $tenant->email],
                        [
                            'name'     => $tenant->name,
                            'password' => Str::random(16),
                        ]
                    );
                    if (!$existingUser->hasRole('tenant')) {
                        $existingUser->assignRole('tenant');
                    }
                    $tenant->update(['user_id' => $existingUser->id]);
                }
            } else {
                $tenantUser = User::create([
                    'name'     => $validated['name'],
                    'email'    => $validated['email'],
                    'password' => Str::random(16),
                ]);
                $tenantUser->assignRole('tenant');

                $tenant = Tenants::create([
                    'name'          => $validated['name'],
                    'email'         => $validated['email'],
                    'phone'         => $validated['phone'] ?? null,
                    'address'       => $validated['address'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'photo_path'    => $photoPath,
                    'document_path' => $documentPath,
                    'apartment_id'  => $apartment->id,
                    'status'        => 'active',
                    'managed_by'    => Auth::id(),
                    'user_id'       => $tenantUser->id,
                ]);
            }

            $updateData = [
                'apartment_id' => $apartment->id,
                'move_in_date' => $validated['move_in_date'],
                'deposit' => $validated['deposit'],
                'status' => 'active',
                'managed_by' => Auth::id(),
            ];

            if ($photoPath) $updateData['photo_path'] = $photoPath;
            if ($documentPath) $updateData['document_path'] = $documentPath;

            $tenant->update($updateData);
            $apartment->update(['status' => 'occupied']);

            $moveInDate = Carbon::parse($validated['move_in_date']);
            $rental = Rentals::create([
                'apartment_id' => $apartment->id,
                'tenant_id' => $tenant->id,
                'start_date' => $moveInDate,
                'end_date' => null,
                'rent_amount' => $apartment->monthly_rent,
                'deposit' => $validated['deposit'],
            ]);

            if (!empty($validated['deposit']) && $validated['deposit'] > 0) {
                $activePeriod = $this->activeAdminFiscalPeriod();

                $reference = 'deposit:rental:' . $rental->id;

                Accounts::firstOrCreate(
                    ['reference_number' => $reference],
                    [
                        'fiscal_period_id' => $activePeriod?->id,
                        'payment_id' => null,
                        'user_id' => Auth::id(),
                        'account_type' => Accounts::TYPE_INCOME,
                        'category' => Accounts::CAT_DEPOSIT_INCOME,
                        'description' => 'Security deposit — Apt ' . ($apartment->apartment_number ?? 'N/A'),
                        'amount' => $validated['deposit'],
                        'transaction_date' => now()->toDateString(),
                        'note' => 'Initial deposit collected on tenant assignment',
                        'reference_number' => $reference,
                    ]
                );
            }

            return redirect()->route('supervisor.apartments.index')
                ->with('success', 'Tenant assigned successfully with rental created.');
        });
    }

    private function activeAdminFiscalPeriod(): ?FiscalPeriods
    {
        return FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
            ->orderBy('opening_date', 'desc')
            ->first();
    }

    /**
     * Supervisors can manage all apartments.
     */
    private function authorizeApartment(Apartments $apartment): void
    {
        // Supervisors have access to all apartments
    }
}
