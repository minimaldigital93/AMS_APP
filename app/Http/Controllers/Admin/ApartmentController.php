<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\User;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class ApartmentController extends Controller
{

    public function index(Request $request): View
    {
        $query = Apartments::with(['floor', 'tenants', 'supervisor']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('apartment_number', 'like', "%{$search}%");
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        $apartments = $query->get();
        
        // Group apartments by floor in ascending order
        $apartmentsByFloor = $apartments->filter(function($apartment) {
            return $apartment->floor !== null;
        })->groupBy(function($apartment) {
            return $apartment->floor->id;
        })->sortBy(function($group) {
            return $group->first()->floor->id;
        });
        
        $floorsWithApartments = Floors::with('apartments')->orderBy('id', 'asc')->get();
        $floors = Floors::orderBy('id', 'asc')->get();
        $statuses = Apartments::getStatuses();
        $supervisors = User::role('supervisor')->get();
        $availableTenants = Tenants::where('status', 'active')->whereNull('apartment_id')->get();

        return view('admin.apartments.index', compact('apartmentsByFloor', 'floors', 'floorsWithApartments', 'statuses', 'supervisors', 'availableTenants'));
    }   

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

        // Get active rental with payments
        $activeRental = Rentals::where('apartment_id', $apartment->id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->with(['tenant', 'payments' => function ($q) {
                $q->where('payment_type', 'rent')
                    ->where('payment_status', 'paid')
                    ->orderBy('paid_at', 'asc');
            }])
            ->latest('start_date')
            ->first();

        $rentProgress = [];

        if ($activeRental) {
            $startDate = Carbon::parse($activeRental->start_date)->startOfMonth();
            $endDate = $activeRental->end_date
                ? Carbon::parse($activeRental->end_date)->endOfMonth()
                : now()->endOfMonth();
            $monthlyRent = $activeRental->rent_amount;

            $rentalStartDay = Carbon::parse($activeRental->start_date)->day;
            $rentalStartMonth = Carbon::parse($activeRental->start_date)->month;
            $rentalStartYear = Carbon::parse($activeRental->start_date)->year;

            $current = $startDate->copy();
            while ($current->lte($endDate)) {
                $month = $current->month;
                $year = $current->year;

                // Find payments for this month
                $monthPayments = $activeRental->payments->filter(function ($p) use ($month, $year) {
                    return Carbon::parse($p->paid_at)->month === $month
                        && Carbon::parse($p->paid_at)->year === $year;
                });

                $paidAmount = $monthPayments->sum('amount');
                $lateFees = $monthPayments->sum('late_fee');
                $paidDate = $monthPayments->first()?->paid_at;
                $percent = $monthlyRent > 0 ? min(round(($paidAmount / $monthlyRent) * 100, 1), 100) : 0;

                // Due date is the rental start day-of-month within the current month
                $dueDay = min($rentalStartDay, $current->copy()->daysInMonth);
                $dueDate = Carbon::create($year, $month, $dueDay)->endOfDay();
                $isFirstMonth = ($month === $rentalStartMonth && $year === $rentalStartYear);
                $isPastDue = now()->gt($dueDate);
                $isCurrent = $current->month === now()->month && $current->year === now()->year;

                $status = 'upcoming';
                if ($percent >= 100) {
                    $status = 'paid';
                } elseif ($percent > 0) {
                    $status = 'partial';
                } elseif ($isPastDue && !$isFirstMonth) {
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

        return view('admin.apartments.show', compact(
            'apartment', 'activeRental', 'rentProgress',
            'totalPaid', 'totalExpected', 'overallPercent'
        ));
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
            'apartment_number' => 'required|string|unique:apartments',
            'floor_id' => 'required|exists:floors,id',
            'monthly_rent' => 'required|numeric|min:0',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        Apartments::create($validated);

        return redirect()->route('admin.apartments.index')->with('success', 'Apartment created successfully');
    }

    public function update(Request $request, Apartments $apartment)
    {
        $validated = $request->validate([
            'apartment_number' => 'required|string|unique:apartments,apartment_number,' . $apartment->id,
            'monthly_rent' => 'required|numeric|min:0',
            'status' => Apartments::getStatusValidationRule(),
            'supervisor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
        ]);

        $apartment->update($validated);

        return redirect()->route('admin.apartments.index')->with('success', 'Apartment updated successfully');
    }


    public function assignTenant(Request $request, Apartments $apartment)
    {
        $validated = $request->validate([
            'tenant_option' => 'required|in:existing,new',
            'tenant_id' => 'nullable|required_if:tenant_option,existing|exists:tenants,id',
            'name' => 'nullable|required_if:tenant_option,new|string|max:255',
            'email' => 'nullable|required_if:tenant_option,new|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'attached_photo' => 'nullable|file|mimes:jpeg,jpg,png,gif,pdf|max:5120',
            'id_pdf' => 'nullable|file|mimes:pdf,jpeg,jpg,png,gif|max:5120',
            'move_in_date' => 'required|date',
            'deposit' => 'required|numeric|min:0',
        ]);

        $tenant = null;
        $documentPath = null;
        $photoPath = null;

        // Handle attached photo upload
        if ($request->hasFile('attached_photo')) {
            $photoPath = $request->file('attached_photo')->store('tenants', 'public');
        }

        // Handle PDF file upload
        if ($request->hasFile('id_pdf')) {
            $documentPath = $request->file('id_pdf')->store('tenants/id_documents', 'public');
        }

        if ($validated['tenant_option'] === 'existing') {
            $tenant = Tenants::findOrFail($validated['tenant_id']);

            // If this existing tenant has no user account yet, create one now
            if (!$tenant->user_id && $tenant->email) {
                $existingUser = User::where('email', $tenant->email)->first();
                if (!$existingUser) {
                    $existingUser = User::create([
                        'name'     => $tenant->name,
                        'email'    => $tenant->email,
                        'password' => '12345678',
                    ]);
                    $existingUser->assignRole('tenant');
                }
                $tenant->update(['user_id' => $existingUser->id]);
            }
        } else {
            // Create a user account first, then create the tenant linked to it
            $tenantUser = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => '12345678',
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
                'user_id'       => $tenantUser->id,
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

        if ($documentPath) {
            $updateData['document_path'] = $documentPath;
        }

        $tenant->update($updateData);

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
        if (!empty($validated['deposit']) && $validated['deposit'] > 0) {
            // Determine active fiscal period for this user
            $activePeriod = FiscalPeriods::where('user_id', Auth::id())
                ->where('status', 'open')
                ->orderBy('opening_date', 'desc')
                ->first();

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

        return redirect()->route('admin.apartments.index')->with('success', 'Tenant assigned successfully with rental created.');
    }


    public function destroy(Apartments $apartment)
    {
        $floor = $apartment->floor;
        $apartment->delete();
        
        // Check if request came from floor edit page
        $referrer = request()->headers->get('referer');
        if ($referrer && str_contains($referrer, '/admin/floors/') && str_contains($referrer, '/edit')) {
            return back()->with('success', 'Apartment deleted successfully');
        }
        
        return redirect()->route('admin.apartments.index')->with('success', 'Apartment deleted successfully');
    }
}
