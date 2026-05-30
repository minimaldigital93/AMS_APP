<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Concerns\HasDashboardMonthNavigation;
use App\Http\Controllers\Concerns\HasFiscalPeriodScope;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Apartments;
use App\Models\FiscalPeriods;
use App\Services\Dashboard\ApartmentRevenueComparisonService;
use App\Services\Dashboard\DashboardCalendarService;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\FiscalPeriodSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use HasDashboardMonthNavigation;
    use HasFiscalPeriodScope;

    /**
     * Supervisors read from the admin's fiscal periods (not their own).
     */
    protected function fiscalPeriodsQuery(): Builder
    {
        return FiscalPeriods::whereHas('user', fn ($q) => $q->role('admin'));
    }

    /**
     * Ledger rows are owned by the admin; supervisors look them up via the
     * active fiscal period.
     */
    protected function ledgerUserId(): ?int
    {
        return $this->getActiveFiscalPeriod()?->user_id;
    }

    public function index(Request $request): View
    {
        $apartmentIds = Apartments::pluck('id')->toArray();

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

        $userId = $this->ledgerUserId() ?? 0; // ?? 0 keeps the type contract; period absent → no rows match anyway

        $stats = (new DashboardStatsService($userId, $apartmentIds))
            ->build($dateRange['start'], $dateRange['end'], $displayMonth);
        $fiscalData = (new FiscalPeriodSummaryService($userId, $apartmentIds))
            ->build($activePeriod);
        $calendarData = $isFullPeriod
            ? null
            : (new DashboardCalendarService($userId, $apartmentIds))->build($activePeriod, $displayMonth);

        $apartmentsWithRentals = Apartments::with(['rentals' => function ($q) {
            $q->where(function ($q2) {
                $q2->whereNull('end_date')->orWhere('end_date', '>=', now());
            })->with('tenant');
        }])
            ->whereIn('id', $apartmentIds)
            ->where('status', 'occupied')
            ->orderBy('apartment_number')
            ->get();

        $recentTransactions = $this->loadRecentTransactions($activePeriod, $apartmentIds, $dateRange);

        $apartmentRevenues = $isFullPeriod
            ? []
            : (new ApartmentRevenueComparisonService($apartmentIds))->build($displayMonth);

        $monthNavigation = $this->getMonthNavigation($periodMonths, $displayMonth, $isFullPeriod);

        return view('supervisor.dashboard', compact(
            'stats', 'fiscalData', 'calendarData',
            'activePeriod', 'apartmentsWithRentals', 'recentTransactions', 'apartmentRevenues',
            'selectedMonth', 'periodMonths', 'monthNavigation', 'isFullPeriod', 'displayMonth'
        ));
    }

    /**
     * Recent ledger entries shown in the dashboard's activity feed.
     *
     * Scoped to the supervisor's apartments: includes income rows tied to
     * those apartments via payment->rental, plus any manual expense entries
     * (whereNull payment_id) which are admin-wide overhead and visible to
     * all supervisors.
     */
    private function loadRecentTransactions(?FiscalPeriods $activePeriod, array $apartmentIds, array $dateRange)
    {
        if (! $activePeriod) {
            return collect();
        }

        return Accounts::where('fiscal_period_id', $activePeriod->id)
            ->where(function ($q) use ($apartmentIds) {
                $q->whereHas('payment', function ($pq) use ($apartmentIds) {
                    $pq->whereHas('rental', function ($rq) use ($apartmentIds) {
                        $rq->whereIn('apartment_id', $apartmentIds);
                    });
                })->orWhere(function ($q2) {
                    $q2->where('account_type', Accounts::TYPE_EXPENSE)->whereNull('payment_id');
                });
            })
            ->whereBetween('transaction_date', [
                $dateRange['start']->copy()->startOfDay(),
                $dateRange['end']->copy()->endOfDay(),
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();
    }
}
