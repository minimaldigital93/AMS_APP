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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            'monthly_rent' => 'required|numeric|min:0|max:99999999.99',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => [
                'nullable',
                // Same-account supervisors only — the bare exists:users,id let a
                // crafted request stamp another account's user (even an admin)
                // onto the apartment (2026-07 validation audit).
                Rule::exists('users', 'id')->where('account_id', current_account_id()),
            ],
            'description' => 'nullable|string|max:65535',
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
            'monthly_rent' => 'required|numeric|min:0|max:99999999.99',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => [
                'nullable',
                // Same-account supervisors only — the bare exists:users,id let a
                // crafted request stamp another account's user (even an admin)
                // onto the apartment (2026-07 validation audit).
                Rule::exists('users', 'id')->where('account_id', current_account_id()),
            ],
            'description' => 'nullable|string|max:65535',
        ], [
            'apartment_number.unique' => __('messages.validation_apartment_number_taken', ['number' => $request->input('apartment_number')]),
        ]);
        $validated = convert_money_input($validated, ['monthly_rent', 'deposit', 'apartments.*.monthly_rent']);

        $apartment->update($validated);

        return redirect()->route('admin.floors.index')->with('success', __('messages.flash_apartment_updated'));
    }

    public function assignTenant(Request $request, Apartments $apartment, AttachmentService $attachments)
    {
        // Same bounds as the tenant store flow: tenants are adults (18+)
        // and move-in can't be backdated more than a few days.
        $minBirthDate = now()->subYears(18)->toDateString();
        $minMoveInDate = now()->subDays(3)->toDateString();

        $validated = $request->validate([
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => 'nullable|required_if:tenant_option,existing|exists:tenants,id',
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'phone' => [
                'nullable', 'required_if:tenant_option,new', 'string', 'max:20', 'regex:/^[0-9+\\-\\s()]+$/',
                // Tenants stay per-account; the users table is one GLOBAL login
                // namespace (users_phone_unique) — a per-account rule here would
                // pass validation and then 500 on the insert.
                Rule::unique('tenants', 'phone')
                    ->where('account_id', current_account_id())
                    ->whereNull('deleted_at'),
                Rule::unique('users', 'phone'),
            ],
            'gender' => 'nullable|in:male,female,other',
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before_or_equal:'.$minBirthDate,
            'attached_photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,heic,heif,pdf|max:10240',
            'id_pdf' => 'nullable|file|mimes:pdf,jpeg,jpg,png,gif,webp,heic,heif|max:10240',
            'move_in_date' => 'required|date|after_or_equal:'.$minMoveInDate,
            'deposit' => 'required|numeric|min:0|max:99999999.99',
        ], [
            'phone.unique' => __('messages.validation_phone_taken'),
            'phone.regex' => __('messages.phone_must_be_english'),
        ]);
        $validated = convert_money_input($validated, ['deposit']);

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

                // An existing tenant must be unhoused — assigning one who still
                // occupies another room would leave that room flagged occupied
                // with an open rental while the tenant lives elsewhere. Moving
                // rooms goes through the tenant edit flow, which ends the old
                // rental and frees the old room in one transaction.
                if ($tenant->apartment_id !== null) {
                    return back()->with('error', __('messages.flash_tenant_already_housed'));
                }

                $this->attachTenantLogin($tenant);
            } else {
                $tenantUser = User::forceCreate([
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
            // upload guard above), resilient so a storage hiccup never aborts
            // the whole assignment.
            if ($validated['tenant_option'] === 'new' && $request->hasFile('id_pdf') && $request->file('id_pdf')->isValid()) {
                try {
                    $attachments->storeMany($tenant, [$request->file('id_pdf')], Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');
                } catch (\Throwable $e) {
                    Log::error('Tenant document upload failed during assignment: '.$e->getMessage());
                }
            }

            $rental = Rentals::create([
                'apartment_id' => $apartment->id,
                'tenant_id' => $tenant->id,
                'start_date' => Carbon::parse($validated['move_in_date']),
                'end_date' => null,
                'rent_amount' => $apartment->monthly_rent,
                'deposit' => $validated['deposit'],
            ]);

            $this->recordDepositIncome($apartment, $rental, (float) $validated['deposit']);

            return redirect()->route('admin.floors.index')->with('success', __('messages.flash_tenant_assigned'));
        });
    }

    /**
     * Give an existing tenant a portal login. The users table is one global
     * login namespace, so the lookup is global: attach only a same-account row;
     * a phone held by another account's login can't be reused — the assignment
     * proceeds without a login (fix the tenant's phone, then re-attach).
     */
    private function attachTenantLogin(Tenants $tenant): void
    {
        if ($tenant->user_id || ! $tenant->phone) {
            return;
        }

        $existingUser = User::where('phone', $tenant->phone)->first();

        if ($existingUser && $existingUser->account_id !== current_account_id()) {
            Log::warning('Tenant login not created: phone belongs to another account\'s user', [
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        if (! $existingUser) {
            $existingUser = User::forceCreate([
                'name' => $tenant->name,
                'phone' => $tenant->phone,
                'password' => Str::random(16),
                'account_id' => current_account_id(),
            ]);
        }

        if (! $existingUser->hasRole('tenant')) {
            $existingUser->assignRole('tenant');
        }

        $tenant->update(['user_id' => $existingUser->id]);
    }

    /**
     * Book the security deposit as deposit income. Skipped (with a warning)
     * when no fiscal period is open — accounts.fiscal_period_id is NOT NULL,
     * so the old unconditional write 500'd the whole assignment.
     */
    private function recordDepositIncome(Apartments $apartment, Rentals $rental, float $deposit): void
    {
        if ($deposit <= 0) {
            return;
        }

        $activePeriod = FiscalPeriods::where('user_id', Auth::id())
            ->where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();

        if (! $activePeriod) {
            Log::warning('No active fiscal period — deposit income not recorded on assignment', [
                'rental_id' => $rental->id,
                'deposit' => $deposit,
            ]);

            return;
        }

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
                'description' => 'Security deposit — Apt '.($apartment->apartment_number ?? 'N/A'),
                'amount' => $deposit,
                'transaction_date' => now()->toDateString(),
                'note' => 'Initial deposit collected on tenant assignment',
                'reference_number' => $reference,
            ]
        );
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
