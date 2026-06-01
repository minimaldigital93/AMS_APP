<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HasDashboardMonthNavigation;
use App\Http\Controllers\Concerns\HasFiscalPeriodScope;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Models\Payments;
use App\Models\Rentals;
use App\Services\Dashboard\ApartmentRevenueComparisonService;
use App\Services\Dashboard\DashboardCalendarService;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\FiscalPeriodSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use HasDashboardMonthNavigation;
    use HasFiscalPeriodScope;

    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::where('user_id', Auth::id());
    }

    protected function ledgerUserId(): ?int
    {
        return Auth::id();
    }

    public function index(Request $request): View
    {
        $activePeriod = $this->getActiveFiscalPeriod();
        $periodMonths = $activePeriod ? $this->buildPeriodMonths($activePeriod) : [];
        $isFullPeriod = $activePeriod && $request->query('view') === 'all';
        $selectedMonth = $isFullPeriod ? null : $this->resolveSelectedMonth(
            $activePeriod,
            $request->integer('month'),
            $request->integer('year')
        );
        $dateRange = $this->resolveDateRange($activePeriod, $selectedMonth, $isFullPeriod);
        $displayMonth = $selectedMonth ?: $this->resolveDisplayMonth($activePeriod, $periodMonths);

        $stats = (new DashboardStatsService($this->ledgerUserId()))
            ->build($dateRange['start'], $dateRange['end'], $displayMonth);
        $fiscalData = (new FiscalPeriodSummaryService($this->ledgerUserId()))
            ->build($activePeriod);
        $calendarData = $isFullPeriod
            ? null
            : (new DashboardCalendarService($this->ledgerUserId()))->build($activePeriod, $displayMonth);

        // Apartments with active rentals for the quick-record-revenue modal
        $apartmentsWithRentals = Apartments::with(['rentals' => function ($q) {
            $q->where(function ($q2) {
                $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
            })->with('tenant');
        }])
            ->where('status', 'occupied')
            ->orderBy('apartment_number')
            ->get();

        $recentTransactions = Accounts::where('user_id', Auth::id())
            ->whereBetween('transaction_date', [
                $dateRange['start']->copy()->startOfDay(),
                $dateRange['end']->copy()->endOfDay(),
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        $apartmentRevenues = $isFullPeriod
            ? []
            : (new ApartmentRevenueComparisonService)->build($displayMonth);

        $monthNavigation = $this->getMonthNavigation($periodMonths, $displayMonth, $isFullPeriod);

        return view('admin.dashboard', compact(
            'stats', 'fiscalData', 'calendarData',
            'activePeriod', 'apartmentsWithRentals', 'recentTransactions', 'apartmentRevenues',
            'selectedMonth', 'periodMonths', 'monthNavigation', 'isFullPeriod', 'displayMonth'
        ));
    }

    /**
     * Quick "Record revenue" modal on the dashboard. Writes a Payments row +
     * a matching Accounts ledger entry (category mapped from payment_type).
     */
    public function storeQuickRevenue(Request $request)
    {
        $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'payment_type' => 'required|in:rent,deposit,late_fee,other',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_payment',
            'note' => 'nullable|string|max:500',
        ]);

        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return back()->with('error', __('messages.no_fiscal_period'));
        }

        $rental = Rentals::with('tenant', 'apartment')->findOrFail($request->rental_id);

        $payment = Payments::create([
            'rental_id' => $rental->id,
            'amount' => $request->amount,
            'late_fee' => 0,
            'payment_type' => $request->payment_type,
            'payment_method' => $request->payment_method,
            'payment_status' => 'paid',
            'paid_at' => $request->transaction_date,
            'note' => $request->note,
        ]);

        $category = match ($request->payment_type) {
            'rent' => Accounts::CAT_RENT_INCOME,
            'late_fee' => Accounts::CAT_LATE_FEE_INCOME,
            'deposit' => Accounts::CAT_DEPOSIT_INCOME,
            default => Accounts::CAT_OTHER_INCOME,
        };

        Accounts::create([
            'user_id' => Auth::id(),
            'fiscal_period_id' => $activePeriod->id,
            'payment_id' => $payment->id,
            'account_type' => Accounts::TYPE_INCOME,
            'category' => $category,
            'amount' => $request->amount,
            'description' => ucfirst($request->payment_type).' - '
                                  .($rental->apartment->apartment_number ?? 'N/A')
                                  .' ('.($rental->tenant->name ?? 'N/A').')',
            'transaction_date' => $request->transaction_date,
        ]);

        return back()->with('success', __('messages.flash_revenue_recorded', ['amount' => number_format($request->amount, 2)]));
    }

    /**
     * Quick "Record expense" modal. Writes a single Accounts ledger entry
     * (no Payments row, since this is a non-tenant business expense).
     */
    public function storeQuickExpense(Request $request)
    {
        $request->validate([
            'category' => 'required|in:'.implode(',', [
                Accounts::CAT_UTILITIES_EXPENSE,
                Accounts::CAT_MAINTENANCE_EXPENSE,
                Accounts::CAT_BUSINESS_FIXED,
                Accounts::CAT_BUSINESS_VARIABLE,
                Accounts::CAT_OTHER_EXPENSE,
            ]),
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return back()->with('error', __('messages.no_fiscal_period'));
        }

        Accounts::create([
            'user_id' => Auth::id(),
            'fiscal_period_id' => $activePeriod->id,
            'account_type' => Accounts::TYPE_EXPENSE,
            'category' => $request->category,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->transaction_date,
            'note' => $request->note,
        ]);

        return back()->with('success', __('messages.flash_expense_recorded', ['amount' => number_format($request->amount, 2)]));
    }
}
