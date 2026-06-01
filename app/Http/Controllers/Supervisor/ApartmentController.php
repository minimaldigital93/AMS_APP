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
            $query->where('apartment_number', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $apartments = $query->get();

        $apartmentsByFloor = $apartments->filter(fn ($apt) => $apt->floor !== null)
            ->groupBy(fn ($apt) => $apt->floor->id)
            ->sortBy(fn ($group) => $group->first()->floor->id);

        $floors = Floors::whereHas('apartments')
            ->orderBy('id', 'asc')->get();

        $statuses = Apartments::getStatuses();

        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('supervisor.apartments.index', compact('apartmentsByFloor', 'floors', 'statuses', 'apartments', 'availableTenants'));
    }

    /**
     * 3D visualization of all floors and their apartments,
     * highlighting available vs occupied units. Mirrors the admin view.
     */
    public function plan3d(): View
    {
        $floors = Floors::with(['apartments' => function ($query) {
            $query->orderBy('apartment_number');
        }])->orderBy('id')->get();

        $floorsData = $floors->map(function ($floor) {
            return [
                'id' => $floor->id,
                'name' => $floor->floor_name,
                'apartments' => $floor->apartments->map(function ($apt) {
                    return [
                        'id' => $apt->id,
                        'number' => $apt->apartment_number,
                        'status' => $apt->status,
                        'rent' => (float) $apt->monthly_rent,
                    ];
                })->values(),
            ];
        })->values();

        $summary = [
            'floors' => $floors->count(),
            'total' => $floors->sum(fn ($f) => $f->apartments->count()),
            'available' => $floors->sum(fn ($f) => $f->apartments->where('status', 'available')->count()),
            'occupied' => $floors->sum(fn ($f) => $f->apartments->where('status', 'occupied')->count()),
            'maintenance' => $floors->sum(fn ($f) => $f->apartments->where('status', 'maintenance')->count()),
        ];

        return view('supervisor.apartments.plan3d', compact('floorsData', 'summary'));
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
            ->with('tenant')
            ->latest('start_date')
            ->first();

        // Load the tenant's own relations for the embedded universal tenant view.
        if ($activeRental && $activeRental->tenant) {
            $activeRental->tenant->load(['apartment.floor', 'rentals.apartment', 'rentals.payments']);
        }

        return view('supervisor.apartments.show', compact('apartment', 'activeRental', 'activePeriod'));
    }

    /**
     * Assign tenant to an available apartment.
     */
    public function assignTenant(Request $request, Apartments $apartment)
    {
        $this->authorizeApartment($apartment);

        $minBirthDate = now()->subYears(18)->toDateString();
        $minMoveInDate = now()->subDays(3)->toDateString();

        $validated = $request->validate([
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => 'nullable|required_if:tenant_option,existing|exists:tenants,id',
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'phone' => [
                'nullable',
                'required_if:tenant_option,new',
                'string',
                'max:20',
                'regex:/^[0-9+\-\s()]+$/',
                Rule::unique('users', 'phone')->where(fn ($q) => $request->input('tenant_option') === 'new'),
                Rule::unique('tenants', 'phone')->where(fn ($q) => $request->input('tenant_option') === 'new'),
            ],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before_or_equal:'.$minBirthDate,
            'attached_photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,pdf|max:5120',
            'id_pdf' => 'nullable|file|mimes:pdf,jpeg,jpg,png,gif|max:5120',
            'move_in_date' => 'required|date|after_or_equal:'.$minMoveInDate,
            'deposit' => 'required|numeric|min:0',
        ], [
            'phone.regex' => __('messages.phone_must_be_english'),
            'date_of_birth.before_or_equal' => __('messages.tenant_must_be_18'),
            'move_in_date.after_or_equal' => __('messages.move_in_date_min'),
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

                if (! $tenant->user_id && $tenant->phone) {
                    $existingUser = User::firstOrCreate(
                        ['phone' => $tenant->phone],
                        [
                            'name' => $tenant->name,
                            'password' => Str::random(16),
                            'account_id' => current_account_id(),
                        ]
                    );
                    if (! $existingUser->hasRole('tenant')) {
                        $existingUser->assignRole('tenant');
                    }
                    $tenant->update(['user_id' => $existingUser->id]);
                }
            } else {
                $tenantUser = User::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'password' => Str::random(16),
                    'account_id' => current_account_id(),
                ]);
                $tenantUser->assignRole('tenant');

                $tenant = Tenants::create([
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'photo_path' => $photoPath,
                    'document_path' => $documentPath,
                    'apartment_id' => $apartment->id,
                    'status' => 'active',
                    'managed_by' => Auth::id(),
                    'user_id' => $tenantUser->id,
                ]);
            }

            $updateData = [
                'apartment_id' => $apartment->id,
                'move_in_date' => $validated['move_in_date'],
                'deposit' => $validated['deposit'],
                'status' => 'active',
                'managed_by' => Auth::id(),
            ];

            if ($photoPath) {
                $updateData['photo_path'] = $photoPath;
            }
            if ($documentPath) {
                $updateData['document_path'] = $documentPath;
            }

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

            if (! empty($validated['deposit']) && $validated['deposit'] > 0) {
                $activePeriod = $this->activeAdminFiscalPeriod();

                $reference = 'deposit:rental:'.$rental->id;

                Accounts::firstOrCreate(
                    ['reference_number' => $reference],
                    [
                        'fiscal_period_id' => $activePeriod?->id,
                        'payment_id' => null,
                        'user_id' => Auth::id(),
                        'account_type' => Accounts::TYPE_INCOME,
                        'category' => Accounts::CAT_DEPOSIT_INCOME,
                        'description' => 'Security deposit — Apt '.($apartment->apartment_number ?? 'N/A'),
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
