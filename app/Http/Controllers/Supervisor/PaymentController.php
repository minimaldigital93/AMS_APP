<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\Apartments;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;

class PaymentController extends Controller
{
    /**
     * Get apartment IDs assigned to the current supervisor.
     */
    private function supervisorApartmentIds(): array
    {
        return Apartments::pluck('id')->toArray();
    }

    /**
     * Display payments for supervisor's assigned apartments.
     */
    public function index(Request $request): View
    {
        $apartmentIds = $this->supervisorApartmentIds();

        // Get admin's active fiscal period
        $activePeriod = FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
            ->orderBy('opening_date', 'desc')
            ->first();

        $query = Payments::whereHas('rental', function ($q) use ($apartmentIds) {
            $q->whereIn('apartment_id', $apartmentIds);
        })->with(['rental.tenant', 'rental.apartment']);

        // Scope to fiscal period date range
        if ($activePeriod) {
            $query->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
        }

        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('payment_type', $request->type);
        }

        if ($request->filled('apartment')) {
            $query->whereHas('rental', function ($q) use ($request) {
                $q->where('apartment_id', $request->apartment);
            });
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(20);
        $apartments = Apartments::whereIn('id', $apartmentIds)->get();

        // Summary stats scoped to fiscal period
        $periodScope = function ($baseQuery) use ($activePeriod) {
            if ($activePeriod) {
                $baseQuery->whereBetween('paid_at', [$activePeriod->opening_date, $activePeriod->closing_date]);
            }
            return $baseQuery;
        };

        $stats = [
            'total_collected' => $periodScope(
                Payments::whereHas('rental', function ($q) use ($apartmentIds) {
                    $q->whereIn('apartment_id', $apartmentIds);
                })->where('payment_status', 'paid')
            )->sum('amount'),
            'pending' => Payments::whereHas('rental', function ($q) use ($apartmentIds) {
                $q->whereIn('apartment_id', $apartmentIds);
            })->where('payment_status', 'pending')->sum('amount'),
            'overdue_count' => Payments::whereHas('rental', function ($q) use ($apartmentIds) {
                $q->whereIn('apartment_id', $apartmentIds);
            })->where('due_date', '<', now())->whereNull('paid_at')
              ->where('payment_status', '!=', 'cancelled')->count(),
            'this_period' => $periodScope(
                Payments::whereHas('rental', function ($q) use ($apartmentIds) {
                    $q->whereIn('apartment_id', $apartmentIds);
                })->where('payment_status', 'paid')
            )->sum('amount'),
        ];

        return view('supervisor.payments.index', compact('payments', 'apartments', 'stats', 'activePeriod'));
    }

    /**
     * Show payment recording form.
     */
    public function create(): View
    {
        $apartmentIds = $this->supervisorApartmentIds();

        $rentals = Rentals::whereIn('apartment_id', $apartmentIds)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->with(['tenant', 'apartment'])
            ->get();

        return view('supervisor.payments.create', compact('rentals'));
    }

    /**
     * Record a new payment.
     */
    public function store(Request $request)
    {
        $apartmentIds = $this->supervisorApartmentIds();

        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'required|in:rent,utilities,deposit,late_fee,other',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_payment',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        // Verify rental belongs to supervisor's apartment
        $rental = Rentals::with(['tenant', 'apartment'])->findOrFail($validated['rental_id']);
        if (!in_array($rental->apartment_id, $apartmentIds)) {
            abort(403, 'Unauthorized rental.');
        }

        $payment = Payments::create([
            'rental_id' => $rental->id,
            'amount' => $validated['amount'],
            'late_fee' => 0,
            'payment_type' => $validated['payment_type'],
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'paid',
            'paid_at' => $validated['transaction_date'],
            'note' => $validated['note'] ?? 'Recorded by supervisor',
        ]);

        // Record in accounts if active fiscal period exists
        $activePeriod = FiscalPeriods::where('status', 'open')
            ->whereHas('user', function ($q) {
                $q->role('admin');
            })
            ->orderBy('opening_date', 'desc')
            ->first();

        if ($activePeriod) {
            $categoryMap = [
                'rent' => Accounts::CAT_RENT_INCOME,
                'utilities' => Accounts::CAT_UTILITY_INCOME,
                'deposit' => Accounts::CAT_DEPOSIT_INCOME,
                'late_fee' => 'late_fee_income',
                'other' => Accounts::CAT_OTHER_INCOME,
            ];

            Accounts::create([
                'user_id' => $activePeriod->user_id,
                'fiscal_period_id' => $activePeriod->id,
                'account_type' => Accounts::TYPE_INCOME,
                'category' => $categoryMap[$validated['payment_type']] ?? Accounts::CAT_OTHER_INCOME,
                'amount' => $validated['amount'],
                'description' => ucfirst($validated['payment_type']) . ' - ' .
                    ($rental->apartment->apartment_number ?? 'N/A') . ' (' .
                    ($rental->tenant->name ?? 'N/A') . ') [by supervisor]',
                'transaction_date' => $validated['transaction_date'],
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
            ]);
        }

        return redirect()->route('supervisor.payments.index')
            ->with('success', 'Payment of $' . number_format($validated['amount'], 2) . ' recorded successfully.');
    }

    /**
     * Show payment details.
     */
    public function show(Payments $payment): View
    {
        $apartmentIds = $this->supervisorApartmentIds();

        $payment->load(['rental.tenant', 'rental.apartment']);

        if (!in_array($payment->rental->apartment_id, $apartmentIds)) {
            abort(403, 'Unauthorized.');
        }

        return view('supervisor.payments.show', compact('payment'));
    }
}
