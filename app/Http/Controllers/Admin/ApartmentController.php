<?php

namespace App\Http\Controllers\Admin;

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
use App\Services\Subscription\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApartmentController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function create(): View
    {
        $floors = Floors::all();
        $supervisors = User::role('supervisor')->get();
        $statuses = Apartments::getStatuses();

        return view('admin.apartments.create', compact('floors', 'supervisors', 'statuses'));
    }

    public function show(Apartments $apartment): View
    {
        $apartment = $apartment->load('floor', 'supervisor');

        // Get the active rental for this apartment
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

        return view('shared.apartments.show', compact('apartment', 'activeRental') + ['panel' => 'admin']);
    }

    public function edit(Apartments $apartment): View
    {
        $apartment = $apartment->load('floor', 'supervisor');
        $floors = Floors::all();
        $supervisors = User::role('supervisor')->get();
        $statuses = Apartments::getStatuses();

        return view('admin.apartments.edit', compact('apartment', 'floors', 'supervisors', 'statuses'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'apartment_number' => [
                'required', 'string', 'max:255',
                // Per-floor uniqueness: a unit "101" may exist on more than one
                // floor of the same building (and across properties).
                Rule::unique('apartments', 'apartment_number')
                    ->where('floor_id', $request->input('floor_id'))
                    ->whereNull('deleted_at'),
            ],
            'floor_id' => 'required|exists:floors,id',
            'monthly_rent' => 'required|numeric|min:0',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ], [
            'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        // Enforce the account's subscription plan room cap.
        $accountId = current_account_id();
        if (! $this->subscriptions->canAddRooms($accountId)) {
            $plan = $this->subscriptions->activePlan($accountId);

            return back()->withInput()->with('error', __('messages.flash_plan_limit_rooms', ['plan' => $plan?->name, 'max' => $plan?->max_rooms]));
        }

        Apartments::create($validated);

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_apartment_created'));
    }

    public function update(Request $request, Apartments $apartment)
    {
        $validated = $request->validate([
            'apartment_number' => [
                'required', 'string', 'max:255',
                // Per-floor uniqueness, ignoring this apartment's own row. The edit
                // form can't move an apartment between floors, so the floor is fixed
                // to this apartment's existing floor_id.
                Rule::unique('apartments', 'apartment_number')
                    ->ignore($apartment->id)
                    ->where('floor_id', $apartment->floor_id)
                    ->whereNull('deleted_at'),
            ],
            'monthly_rent' => 'required|numeric|min:0',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ], [
            'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        $apartment->update($validated);

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_apartment_updated'));
    }

    public function assignTenant(Request $request, Apartments $apartment, AttachmentService $attachments)
    {
        $validated = $request->validate([
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => 'nullable|required_if:tenant_option,existing|exists:tenants,id',
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'phone' => [
                'nullable', 'required_if:tenant_option,new', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/',
                // Per-account uniqueness — only validated when creating a brand-new tenant.
                Rule::unique('tenants', 'phone')
                    ->where('account_id', current_account_id())
                    ->whereNull('deleted_at')
                    ->where(fn () => $request->input('tenant_option') === 'new'),
                Rule::unique('users', 'phone')
                    ->where('account_id', current_account_id())
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

        $tenant = null;
        $photoPath = null;

        // Handle attached photo upload — resilient so a storage hiccup never
        // aborts the whole assignment (the tenant record still gets created).
        if ($request->hasFile('attached_photo') && $request->file('attached_photo')->isValid()) {
            try {
                $photoPath = $request->file('attached_photo')->store('tenants', 'public');
            } catch (\Throwable $e) {
                Log::error('Tenant photo upload failed during assignment: '.$e->getMessage());
            }
        }

        if ($validated['tenant_option'] === 'existing') {
            $tenant = Tenants::findOrFail($validated['tenant_id']);

            // If this existing tenant has no user account yet, create one now
            if (! $tenant->user_id && $tenant->phone) {
                // Scope to this account — the same phone may now exist under other accounts.
                $existingUser = User::where('phone', $tenant->phone)
                    ->where('account_id', current_account_id())
                    ->first();
                if (! $existingUser) {
                    $existingUser = User::create([
                        'name' => $tenant->name,
                        'phone' => $tenant->phone,
                        'password' => '12345678',
                        'account_id' => current_account_id(),
                    ]);
                    $existingUser->assignRole('tenant');
                }
                $tenant->update(['user_id' => $existingUser->id]);
            }
        } else {
            // Create a user account first, then create the tenant linked to it
            $tenantUser = User::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'password' => '12345678',
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
                'user_id' => $tenantUser->id,
            ]);
        }

        // Update tenant information
        $updateData = [
            'apartment_id' => $apartment->id,
            'move_in_date' => $validated['move_in_date'],
            'deposit' => $validated['deposit'],
            'status' => 'active',
        ];

        // Record who assigned this tenant (admin or supervisor)
        $updateData['managed_by'] = Auth::id();

        if ($photoPath) {
            $updateData['photo_path'] = $photoPath;
        }

        $tenant->update($updateData);

        // ID document upload — resilient so a storage hiccup never aborts the
        // whole assignment (the tenant record still gets created/updated).
        if ($request->hasFile('id_pdf') && $request->file('id_pdf')->isValid()) {
            try {
                $attachments->storeMany($tenant, [$request->file('id_pdf')], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');
            } catch (\Throwable $e) {
                Log::error('Tenant document upload failed during assignment: '.$e->getMessage());
            }
        }

        // Update apartment status to occupied
        $apartment->update(['status' => 'occupied']);

        // Auto-create Rental record (ongoing lease — end_date set when tenant leaves)
        $moveInDate = Carbon::parse($validated['move_in_date']);
        $rental = Rentals::create([
            'apartment_id' => $apartment->id,
            'tenant_id' => $tenant->id,
            'start_date' => $moveInDate,
            'end_date' => null,
            'rent_amount' => $apartment->monthly_rent,
            'deposit' => $validated['deposit'],
        ]);

        // Record deposit as revenue (deposit income) in Accounts ledger
        if (! empty($validated['deposit']) && $validated['deposit'] > 0) {
            // Determine active fiscal period for this user
            $activePeriod = FiscalPeriods::where('user_id', Auth::id())
                ->where('status', 'open')
                ->orderBy('opening_date', 'desc')
                ->first();

            $reference = 'deposit:rental:'.$rental->id;

            Accounts::firstOrCreate(
                ['reference_number' => $reference],
                [
                    'fiscal_period_id' => $activePeriod?->id,
                    'property_id' => $apartment->property_id ?? $apartment->floor?->property_id,
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

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_tenant_assigned'));
    }

    public function destroy(Apartments $apartment)
    {
        // Block deletion while a tenant still lives here. Soft-delete does not
        // cascade to rentals/tenants, so removing an occupied unit would orphan
        // the live rental ($rental->apartment === null) and break ledger writes.
        if ($apartment->isCurrentlyOccupied()) {
            return back()->with('error', __('messages.flash_apartment_has_active_tenant'));
        }

        $apartment->delete();

        // Check if request came from floor edit page
        $referrer = request()->headers->get('referer');
        if ($referrer && str_contains($referrer, '/admin/floors/') && str_contains($referrer, '/edit')) {
            return back()->with('success', __('messages.flash_apartment_deleted'));
        }

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_apartment_deleted'));
    }
}
