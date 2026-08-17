<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\ProcessTenantLeaveRequest;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\Attachment;
use App\Models\FiscalPeriods;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use App\Services\Property\PropertyContext;
use App\Services\Tenants\LeaseSyncService;
use App\Services\Tenants\TenantLeaveProcessor;
use App\Services\Tenants\TenantPendingChargesQuery;
use App\Services\Tenants\TenantRentProgressCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * The active-tenant roster, grouped property → floor → room.
     */
    public function index(Request $request, PropertyContext $propertyContext): View
    {

        $showingAll = $propertyContext->showingAllProperties();
        $scopeId = $showingAll ? null : current_property_id();

        $query = Tenants::whereIn('tenants.status', ['active', 'pending'])
            ->with(['apartment.floor.property'])
            ->forProperty($scopeId);

        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('tenants.name', 'like', "%{$search}%")
                    ->orWhere('tenants.phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('apartment') && ! empty($request->apartment)) {
            $query->where('tenants.apartment_id', $request->apartment);
        }

        if ($request->has('status') && ! empty($request->status)) {
            $query->where('tenants.status', $request->status);
        }

        $tenants = $query->orderBy('tenants.id', 'desc')->get();

        $tenants = $tenants->sortBy(
            fn ($t) => sprintf(
                '%s|%020d|%s',
                $t->apartment?->floor?->property?->name ?? '~',
                $t->apartment?->floor?->id ?? PHP_INT_MAX,
                $t->apartment?->apartment_number ?? '~',
            ),
            SORT_NATURAL | SORT_FLAG_CASE
        )->values();

        $rentProgressMap = $this->rentProgressCalculator->map($tenants);

        $rentStatus = $request->input('rent_status');
        if (in_array($rentStatus, ['paid', 'overdue', 'pending'], true)) {
            $tenants = $tenants->filter(
                fn ($t) => ($rentProgressMap[$t->id]['status'] ?? 'unknown') === $rentStatus
            )->values();
        }

        $activeTenantCount = Tenants::where('status', 'active')->forProperty($scopeId)->count();
        $archivedTenantCount = $this->scopeArchivedToActiveProperty(Tenants::onlyTrashed(), $scopeId)->count();
        $totalDeposits = Tenants::where('status', 'active')->forProperty($scopeId)->sum('deposit');

        return view('admin.tenants.index', compact('tenants', 'rentProgressMap', 'activeTenantCount', 'archivedTenantCount', 'totalDeposits', 'showingAll'));
    }

    /**
     * Show the form for checking in a new tenant.
     */
    public function create(): View
    {
        $apartments = Apartments::rentable()
            ->where('status', 'available')
            ->with('floor')
            ->get();

        return view('shared.tenants.create', compact('apartments') + ['panel' => 'admin']);
    }

    /**
     * Check a tenant in: login, tenant row, room occupancy, lease and deposit.
     */
    public function store(Request $request, AttachmentService $attachments): RedirectResponse
    {
        $minBirthDate = now()->subYears(16)->toDateString();
        $minMoveInDate = now()->subDays(3)->toDateString();

        $validated = $request->validate([

            'apartment_id' => [
                'required',
                Rule::exists('apartments', 'id')
                    ->where('status', 'available')
                    ->where('under_maintenance', 0)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email|max:255',
            'phone' => [
                'required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/',
                Rule::unique('tenants', 'phone')->where('account_id', current_account_id())->whereNull('deleted_at'),
                Rule::unique('users', 'phone'),
            ],
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date|before_or_equal:'.$minBirthDate,
            'move_in_date' => 'required|date|after_or_equal:'.$minMoveInDate,
            'move_out_date' => 'nullable|date|after:move_in_date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0|max:99999999.99',
            'photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,heic,heif|max:10240',
            'documents' => 'nullable|array|max:4',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,heic,heif|max:10240',
            'notes' => 'nullable|string',
        ], [
            'apartment_id.exists' => __('messages.validation_apartment_unavailable'),
            'photo.uploaded' => __('messages.validation_photo_upload_failed', ['max' => '10 MB']),
            'photo.max' => __('messages.validation_photo_too_large', ['max' => '10 MB']),
            'photo.mimes' => __('messages.validation_photo_type'),
            'documents.*.uploaded' => __('messages.validation_photo_upload_failed', ['max' => '10 MB']),
            'phone.unique' => __('messages.validation_phone_taken'),
            'phone.regex' => __('messages.phone_must_be_english'),
            'date_of_birth.before_or_equal' => __('messages.tenant_must_be_18'),
            'move_in_date.after_or_equal' => __('messages.move_in_date_min'),
        ]);

        $validated = convert_money_input($validated, ['deposit']);

        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            try {
                $photoPath = $request->file('photo')->store('tenants', 'public');
                $validated['photo_path'] = $photoPath;
            } catch (\Exception $e) {
                Log::error('Photo upload failed: '.$e->getMessage());
            }
        }

        $tenant = DB::transaction(function () use ($validated) {
            // No Hash::make() — the User model's 'hashed' cast does it.
            $tenantUser = User::forceCreate([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'password' => \Illuminate\Support\Str::random(16), // handed out via the reset-password flow
                'account_id' => current_account_id(),
            ]);
            $tenantUser->assignRole('tenant');

            $validated['user_id'] = $tenantUser->id;
            $tenant = Tenants::create($validated);

            $apartment = Apartments::findOrFail($validated['apartment_id']);
            $apartment->update(['status' => 'occupied']);

            $rental = Rentals::create([
                'apartment_id' => $apartment->id,
                'tenant_id' => $tenant->id,
                'start_date' => Carbon::parse($validated['move_in_date']),
                'end_date' => ($validated['move_out_date'] ?? null) ? Carbon::parse($validated['move_out_date']) : null,
                'rent_amount' => $apartment->monthly_rent,
                'payment_due_day' => Carbon::parse($validated['move_in_date'])->day,
                'deposit' => $validated['deposit'] ?? 0,
            ]);

            $depositAmount = $validated['deposit'] ?? 0;
            if ($depositAmount > 0) {
                $activePeriod = FiscalPeriods::where('user_id', current_account_id())
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
                            'user_id' => current_account_id(),
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

            return $tenant;
        });

        if ($request->hasFile('documents')) {
            $attachments->storeMany($tenant, $request->file('documents'), Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', __('messages.flash_tenant_created'));
    }

    /**
     * The tenant profile page.
     */
    public function show(Tenants $tenant): View
    {
        $tenant->load(['apartment.floor', 'apartment.activeFixedExpenses', 'rentals.apartment', 'rentals.payments', 'utilities', 'attachments', 'vehicles']);

        return view('shared.tenants.show', compact('tenant') + ['panel' => 'admin']);
    }

    /**
     * Show the form for editing a tenant.
     */
    public function edit(Tenants $tenant): View
    {
        $apartments = Apartments::where(function ($q) use ($tenant) {
            $q->where(fn ($sub) => $sub->where('status', 'available')->where('under_maintenance', false))
                ->orWhere('id', $tenant->apartment_id);
        })
            ->get();

        return view('admin.tenants.edit', compact('tenant', 'apartments'));
    }

    /**
     * Update a tenant, moving rooms and re-syncing the lease when asked.
     */
    public function update(Request $request, Tenants $tenant, AttachmentService $attachments, LeaseSyncService $leases): RedirectResponse
    {
        $validated = $request->validate([
            'apartment_id' => [
                'required',
                Rule::exists('apartments', 'id')->whereNull('deleted_at')->where(
                    fn ($q) => $q->where(
                        fn ($sub) => $sub->where('status', 'available')->where('under_maintenance', false)
                    )->orWhere('id', $tenant->apartment_id)
                ),
            ],
            'name' => 'required|string|max:255',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email|max:255',
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('tenants', 'phone')->ignore($tenant->id)->where('account_id', current_account_id())->whereNull('deleted_at'),
            ],
            'id_card_number' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'move_in_date' => 'required|date',
            'status' => 'required|in:pending,active,inactive',
            'deposit' => 'nullable|numeric|min:0|max:99999999.99',
            'photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,heic,heif|max:10240',
            'documents' => 'nullable|array|max:4',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,heic,heif|max:10240',
            'notes' => 'nullable|string',
        ], [
            'apartment_id.exists' => __('messages.validation_apartment_unavailable'),
            'photo.uploaded' => __('messages.validation_photo_upload_failed', ['max' => '10 MB']),
            'photo.max' => __('messages.validation_photo_too_large', ['max' => '10 MB']),
            'photo.mimes' => __('messages.validation_photo_type'),
            'documents.*.uploaded' => __('messages.validation_photo_upload_failed', ['max' => '10 MB']),
            'phone.unique' => __('messages.validation_phone_taken'),
        ]);

        $validated = convert_money_input($validated, ['deposit']);

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

        DB::transaction(function () use ($tenant, $validated, $leases) {
            $oldApartmentId = $tenant->apartment_id;
            $newApartmentId = $validated['apartment_id'];

            if ($oldApartmentId != $newApartmentId) {
                Apartments::where('id', $oldApartmentId)->update(['status' => 'available']);
                Apartments::where('id', $newApartmentId)->update(['status' => 'occupied']);

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
                    'payment_due_day' => Carbon::parse($validated['move_in_date'])->day,
                    'deposit' => $validated['deposit'] ?? 0,
                ]);
            }

            $tenant->update($validated);

            // Rent and arrears derive from the lease, not this row — carry the
            // edit across or profile and bill disagree. No-op after a room move.
            $leases->syncFromTenantEdit($tenant);
        });

        if ($request->hasFile('documents')) {
            $attachments->storeMany($tenant, $request->file('documents'), Attachment::KIND_TENANT_DOCUMENT, 'tenants/documents');
        }

        return redirect()->route('admin.tenants.show', $tenant->id)
            ->with('success', __('messages.flash_tenant_updated'));
    }

    /**
     * Remove one uploaded document from a tenant's file.
     */
    public function destroyDocument(Tenants $tenant, Attachment $attachment, AttachmentService $attachments): RedirectResponse
    {
        abort_unless(
            $attachment->attachable_type === Tenants::class && $attachment->attachable_id === $tenant->id,
            404
        );

        $attachments->delete($attachment);

        return redirect()->back()->with('success', __('messages.flash_attachment_removed'));
    }

    /**
     * The archive: tenants who have moved out (soft-deleted).
     */
    public function archived(Request $request): View
    {
        $query = $this->scopeArchivedToActiveProperty(
            Tenants::onlyTrashed()->with(['apartment.floor', 'leaves.apartment.floor', 'attachments'])
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

        return view('shared.tenants.archived', compact('tenants', 'floors', 'archivedTenantCount', 'recentlyArchivedCount', 'totalDeposits') + ['panel' => 'admin']);
    }

    /**
     * The move-out form: settlement preview and the charges still outstanding.
     */
    public function leave(Tenants $tenant): View
    {
        $tenant->load(['apartment', 'rentals']);

        $rental = $tenant->rentals()
            ->where('apartment_id', $tenant->apartment_id)
            ->latest()
            ->first();

        if (! $rental) {
            $rental = new Rentals;
            $rental->apartment_id = $tenant->apartment_id;
            $rental->tenant_id = $tenant->id;
            $rental->rent_amount = $tenant->apartment?->monthly_rent ?? 0;
            $rental->start_date = $tenant->move_in_date;
            $rental->end_date = null;
        }

        $pendingCharges = $this->pendingChargesQuery->forRental($rental);

        return view('shared.tenants.leave', compact('tenant', 'rental', 'pendingCharges') + ['panel' => 'admin']);
    }

    public function processLeave(ProcessTenantLeaveRequest $request, Tenants $tenant): RedirectResponse
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

    private function scopeArchivedToActiveProperty(Builder $query, int|null|false $propertyId = false): Builder
    {

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

    private function recordAdminLeaveAccounting(Tenants $tenant, array $context): void
    {
        $settlement = $context['settlement'];
        $leaveDate = $context['leave_date'];
        $rental = $context['rental'];
        $selectedPayments = $context['selected_payments'];
        $selectedUtilities = $context['selected_utilities'];
        $extraCharges = $context['extra_charges'] ?? [];
        $depositAction = $context['deposit_action'] ?? 'return_deposit';

        $activePeriod = FiscalPeriods::where('user_id', current_account_id())
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
        // Payment-linked rows self-derive the property via Accounts' creating
        // hook; the payment-less rows below need it set explicitly.
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
                'user_id' => current_account_id(),
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
                'user_id' => current_account_id(),
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
                'user_id' => current_account_id(),
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
                'user_id' => current_account_id(),
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
                'user_id' => current_account_id(),
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
                    'user_id' => current_account_id(),
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
}
