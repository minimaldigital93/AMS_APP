<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HasDashboardMonthNavigation;
use App\Http\Controllers\Concerns\HasFiscalPeriodScope;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\FiscalPeriods;
use App\Services\Dashboard\ApartmentRevenueComparisonService;
use App\Services\Dashboard\DashboardCalendarService;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Dashboard\FiscalPeriodSummaryService;
use App\Services\NotificationService;
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

        // Scope every dashboard widget to the globally selected property.
        $propertyId = current_property_id();

        $stats = (new DashboardStatsService($this->ledgerUserId(), null, $propertyId))
            ->build($dateRange['start'], $dateRange['end'], $displayMonth);
        $fiscalData = (new FiscalPeriodSummaryService($this->ledgerUserId(), null, $propertyId))
            ->build($activePeriod);
        $calendarData = $isFullPeriod
            ? null
            : (new DashboardCalendarService($this->ledgerUserId(), null, $propertyId))->build($activePeriod, $displayMonth);

        $recentTransactions = Accounts::where('user_id', Auth::id())
            ->forProperty($propertyId)
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
            : (new ApartmentRevenueComparisonService(null, $propertyId))->build($displayMonth);

        $monthNavigation = $this->getMonthNavigation($periodMonths, $displayMonth, $isFullPeriod);

        // Renewal banner when this admin's subscription is due within 3 days.
        $subscriptionAlert = app(NotificationService::class)->subscriptionDueAlert(Auth::user());

        return view('admin.dashboard', compact(
            'stats', 'fiscalData', 'calendarData',
            'activePeriod', 'recentTransactions', 'apartmentRevenues',
            'selectedMonth', 'periodMonths', 'monthNavigation', 'isFullPeriod', 'displayMonth',
            'subscriptionAlert'
        ));
    }
}
