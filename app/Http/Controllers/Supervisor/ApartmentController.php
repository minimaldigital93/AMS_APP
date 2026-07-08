<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Concerns\ScopesToSupervisorProperties;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApartmentController extends Controller
{
    use ScopesToSupervisorProperties;

    /**
     * Display all apartments grouped by floor.
     */
    public function index(Request $request): View
    {
        $query = $this->supervisorVisibleApartments()
            ->forActiveProperty()
            ->with(['floor', 'tenants', 'supervisor']);

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

        $floors = $this->supervisorVisibleFloors()
            ->forActiveProperty()
            ->whereHas('apartments')
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
        $floors = $this->supervisorVisibleFloors()->forActiveProperty()->with(['apartments' => function ($query) {
            $query->orderBy('apartment_number')
                ->with([
                    'tenants' => fn ($q) => $q->whereNull('archived_at'),
                    'rentals' => fn ($q) => $q->active()->latest('start_date'),
                ]);
        }])->orderBy('id')->get();

        $floorsData = $floors->map(function ($floor) {
            return [
                'id' => $floor->id,
                'name' => $floor->floor_name,
                'apartments' => $floor->apartments->map(function ($apt) {
                    $tenant = $apt->tenants->first();
                    $stay = $apt->rentals->first()?->stayProgress() ?? [];

                    return [
                        'id' => $apt->id,
                        'number' => $apt->apartment_number,
                        'status' => $apt->status,
                        'rent' => (float) $apt->monthly_rent,
                        'tenant' => $tenant?->name,
                        'tenant_id' => $tenant?->id,
                        'stay_label' => $stay['stay_label'] ?? null,
                        'cycle_percent' => $stay['cycle_percent'] ?? null,
                        'days_left' => $stay['days_left'] ?? null,
                        'next_renewal_label' => $stay['next_renewal_label'] ?? null,
                    ];
                })->values(),
            ];
        })->values();

        $summary = [
            'floors' => $floors->count(),
            'total' => $floors->sum(fn ($f) => $f->apartments->count()),
            'available' => $floors->sum(fn ($f) => $f->apartments->where('status', 'available')->count()),
            'occupied' => $floors->sum(fn ($f) => $f->apartments->where('status', 'occupied')->count()),
        ];

        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('shared.apartments.plan3d', compact('floorsData', 'summary', 'availableTenants') + ['panel' => 'supervisor']);
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

        return view('shared.apartments.show', compact('apartment', 'activeRental', 'activePeriod') + ['panel' => 'supervisor']);
    }

    /**
     * Assign tenant to an available apartment.
     */
    public function assignTenant(Request $request, Apartments $apartment, AttachmentService $attachments)
    {
        $this->authorizeApartment($apartment);

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
                // Per-account uniqueness, only when creating a new tenant.
                Rule::unique('users', 'phone')
                    ->where('account_id', current_account_id())
                    ->where(fn () => $request->input('tenant_option') === 'new'),
                Rule::unique('tenants', 'phone')
                    ->where('account_id', current_account_id())
                    ->whereNull('deleted_at')
                    ->where(fn () => $request->input('tenant_option') === 'new'),
            ],
            'gender' => 'nullable|in:male,female',
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'attached_photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,heic,heif,pdf|max:10240',
            'id_pdf' => 'nullable|file|mimes:pdf,jpeg,jpg,png,gif,webp,heic,heif|max:10240',
            'move_in_date' => 'required|date',
            'deposit' => 'required|numeric|min:0',
        ], [
            'phone.unique' => __('messages.validation_phone_taken'),
            'phone.regex' => __('messages.phone_must_be_english'),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        return DB::transaction(function () use ($request, $apartment, $validated, $attachments) {
            // Lock the apartment row so two concurrent assignments can't both succeed.
            $apartment = Apartments::whereKey($apartment->id)->lockForUpdate()->firstOrFail();

            if ($apartment->status !== 'available') {
                return back()->with('error', __('messages.flash_apartment_not_available'));
            }

            $photoPath = null;

            // Only accept uploads when creating a new tenant — prevents accidental
            // overwrite of an existing tenant's photo/document via crafted requests.
            if ($validated['tenant_option'] === 'new') {
                if ($request->hasFile('attached_photo') && $request->file('attached_photo')->isValid()) {
                    try {
                        $photoPath = $request->file('attached_photo')->store('tenants', 'public');
                    } catch (\Throwable $e) {
                        Log::error('Tenant photo upload failed during assignment: '.$e->getMessage());
                    }
                }
            }

            if ($validated['tenant_option'] === 'existing') {
                $tenant = Tenants::findOrFail($validated['tenant_id']);

                if (! $tenant->user_id && $tenant->phone) {
                    // Scope to this account — the same phone may now exist under other accounts.
                    $existingUser = User::firstOrCreate(
                        ['phone' => $tenant->phone, 'account_id' => current_account_id()],
                        [
                            'name' => $tenant->name,
                            'password' => Str::random(16),
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
                    'gender' => $validated['gender'] ?? null,
                    'id_card_number' => $validated['id_card_number'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'date_of_birth' => $validated['date_of_birth'] ?? null,
                    'photo_path' => $photoPath,
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

            $tenant->update($updateData);
            $apartment->update(['status' => 'occupied']);

            // ID document upload — only for a newly-created tenant (matches the
            // photo/PDF-acceptance guard above), resilient so a storage hiccup
            // never aborts the whole assignment.
            if ($validated['tenant_option'] === 'new' && $request->hasFile('id_pdf') && $request->file('id_pdf')->isValid()) {
                try {
                    $attachments->storeMany($tenant, [$request->file('id_pdf')], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');
                } catch (\Throwable $e) {
                    Log::error('Tenant document upload failed during assignment: '.$e->getMessage());
                }
            }

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
                        'property_id' => $apartment->property_id ?? $apartment->floor?->property_id,
                        'payment_id' => null,
                        // Ledger rows carry the admin's user_id (one-ledger invariant).
                        'user_id' => $activePeriod?->user_id ?? Auth::id(),
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
                ->with('success', __('messages.flash_tenant_assigned'));
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
     * Supervisors may only manage rooms in their assigned properties (403 otherwise).
     * Admins/superadmins reaching this route are not property-scoped.
     */
    private function authorizeApartment(Apartments $apartment): void
    {
        $this->authorizeSupervisorApartment($apartment);
    }
}
