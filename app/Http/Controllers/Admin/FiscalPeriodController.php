<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FiscalPeriods;
use App\Models\BalanceSheet;
use App\Models\User;
use App\Models\Payments;
use App\Models\Utilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FiscalPeriodController extends Controller
{
    /**
     * Display a listing of fiscal periods.
     */
    public function index()
    {
        $fiscalPeriods = FiscalPeriods::where('user_id', Auth::id())
            ->orderBy('opening_date', 'desc')
            ->paginate(15);

        return view('admin.fiscalperiod.index', compact('fiscalPeriods'));
    }

    /**
     * Show the form for creating a new fiscal period.
     */
    public function create()
    {
        return view('admin.fiscalperiod.open_close_periods');
    }

    /**
     * Store a newly created fiscal period.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'opening_date' => 'required|date|before:closing_date',
            'closing_date' => 'required|date|after:opening_date',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = 'open';
        $validated['closing_balance'] = 0;

        $fiscalPeriod = FiscalPeriods::create($validated);

        return redirect()
            ->route('admin.fiscalperiod.balance-sheet', $fiscalPeriod->id)
            ->with('success', 'Fiscal period created successfully.');
    }

    /**
     * Show fiscal period details.
     */
    public function show(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);
        
        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('as_of_date', 'desc')
            ->get();

        return view('admin.fiscalperiod.show', compact('fiscalperiod', 'balanceSheetItems'));
    }

    /**
     * Show form to manage balance sheet items.
     */
    public function balanceSheet(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('item_type')
            ->get()
            ->groupBy('item_type');

        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        return view('admin.fiscalperiod.balance_sheet_items', compact('fiscalperiod', 'balanceSheetItems', 'summary'));
    }

    /**
     * Store a balance sheet item.
     */
    public function storeBalanceItem(Request $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $validated = $request->validate([
            'item_type' => 'required|in:asset,liability,equity',
            'sub_type' => 'required|string',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'as_of_date' => 'required|date|after_or_equal:' . $fiscalperiod->opening_date->format('Y-m-d') . '|before_or_equal:' . $fiscalperiod->closing_date->format('Y-m-d'),
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $validated['fiscal_period_id'] = $fiscalperiod->id;
        $validated['user_id'] = Auth::id();

        BalanceSheet::create($validated);

        return back()->with('success', 'Balance sheet item added successfully.');
    }

    /**
     * Show form for opening and closing balances.
     */
    public function openCloseBalances(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $assets = $fiscalperiod->balanceSheets()->where('item_type', 'asset')->sum('amount');
        $liabilities = $fiscalperiod->balanceSheets()->where('item_type', 'liability')->sum('amount');
        $equity = $fiscalperiod->balanceSheets()->where('item_type', 'equity')->sum('amount');

        $closingBalance = $assets - $liabilities;

        return view('admin.fiscalperiod.open_close_balances', compact('fiscalperiod', 'assets', 'liabilities', 'equity', 'closingBalance'));
    }

    /**
     * Update opening and closing balances and close period.
     */
    public function closeperiod(Request $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $validated = $request->validate([
            'closing_balance' => 'required|numeric',
        ]);

        $fiscalperiod->update([
            'closing_balance' => $validated['closing_balance'],
            'status' => 'closed',
        ]);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', 'Fiscal period closed successfully.');
    }

    /**
     * Show reports and export options.
     */
    public function reports(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        // Calculate Revenue from Payments (Rent Income)
        $revenue = Payments::whereHas('rental', function($query) use ($fiscalperiod) {
            $query->whereHas('apartment', function($subQuery) use ($fiscalperiod) {
                $subQuery->where('supervisor_id', $fiscalperiod->user_id);
            });
        })
        ->where('payment_status', 'paid')
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->sum('amount');

        // Calculate Expenses by Utility Type
        $utilities = Utilities::whereHas('rental', function($query) use ($fiscalperiod) {
            $query->whereHas('apartment', function($subQuery) use ($fiscalperiod) {
                $subQuery->where('supervisor_id', $fiscalperiod->user_id);
            });
        })
        ->where('paid_status', true)
        ->whereBetween('paid_at', [$fiscalperiod->opening_date, $fiscalperiod->closing_date])
        ->get()
        ->groupBy('utility_type');

        $expenses = [];
        $totalExpenses = 0;

        foreach ($utilities as $type => $items) {
            $typeTotal = $items->sum('charge_amount');
            $expenses[$type] = $typeTotal;
            $totalExpenses += $typeTotal;
        }

        // Calculate Breakeven Point
        $breakevenPoint = $revenue - $totalExpenses;

        return view('admin.fiscalperiod.period_reports_exports', compact('fiscalperiod', 'balanceSheetItems', 'summary', 'revenue', 'expenses', 'totalExpenses', 'breakevenPoint'));
    }

    /**
     * Export balance sheet as PDF.
     */
    public function exportPDF(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()->get();
        $summary = $this->calculateBalanceSheetSummary($fiscalperiod);

        // Create PDF using available library or HTML table
        $html = $this->generateBalanceSheetHTML($fiscalperiod, $balanceSheetItems, $summary);

        // For now, return view that can be printed as PDF
        return view('admin.fiscalperiod.export-pdf', compact('fiscalperiod', 'balanceSheetItems', 'summary', 'html'));
    }

    /**
     * Export balance sheet as CSV.
     */
    public function exportCSV(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $balanceSheetItems = $fiscalperiod->balanceSheets()
            ->orderBy('item_type')
            ->get();

        $fileName = "balance_sheet_{$fiscalperiod->id}_" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        $callback = function () use ($fiscalperiod, $balanceSheetItems) {
            $file = fopen('php://output', 'w');
            
            // Write headers
            fputcsv($file, [
                'Fiscal Period: ' . $fiscalperiod->name,
                'Period: ' . $fiscalperiod->opening_date . ' to ' . $fiscalperiod->closing_date,
                'Generated: ' . now()->format('Y-m-d H:i:s'),
            ]);
            fputcsv($file, []);

            fputcsv($file, ['Item Type', 'Sub Type', 'Name', 'Amount', 'As Of Date', 'Reference Number', 'Notes']);

            foreach ($balanceSheetItems as $item) {
                fputcsv($file, [
                    ucfirst($item->item_type),
                    ucfirst(str_replace('_', ' ', $item->sub_type)),
                    $item->name,
                    number_format($item->amount, 2),
                    $item->as_of_date,
                    $item->reference_number,
                    $item->notes,
                ]);
            }

            // Add summary
            fputcsv($file, []);
            $summary = $this->calculateBalanceSheetSummary($fiscalperiod);
            fputcsv($file, ['SUMMARY']);
            fputcsv($file, ['Total Assets', number_format($summary['total_assets'], 2)]); 
            fputcsv($file, ['Total Liabilities', number_format($summary['total_liabilities'], 2)]);
            fputcsv($file, ['Total Equity', number_format($summary['total_equity'], 2)]);
            fputcsv($file, ['Opening Balance', number_format($fiscalperiod->opening_balance, 2)]);
            fputcsv($file, ['Closing Balance', number_format($fiscalperiod->closing_balance, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Edit fiscal period.
     */
    public function edit(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);
        
        return view('admin.fiscalperiod.edit', compact('fiscalperiod'));
    }

    /**
     * Update fiscal period.
     */
    public function update(Request $request, FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'opening_date' => 'required|date|before:closing_date',
            'closing_date' => 'required|date|after:opening_date',
            'opening_balance' => 'required|numeric|min:0',
        ]);

        $fiscalperiod->update($validated);

        return redirect()
            ->route('admin.fiscalperiod.show', $fiscalperiod->id)
            ->with('success', 'Fiscal period updated successfully.');
    }

    /**
     * Delete balance sheet item.
     */
    public function deleteBalanceItem(FiscalPeriods $fiscalperiod, BalanceSheet $balanceSheet)
    {
        $this->authorizeUser($fiscalperiod);

        if ($balanceSheet->fiscal_period_id !== $fiscalperiod->id) {
            abort(403);
        }

        $balanceSheet->delete();

        return back()->with('success', 'Balance sheet item deleted successfully.');
    }

    /**
     * Delete fiscal period.
     */
    public function destroy(FiscalPeriods $fiscalperiod)
    {
        $this->authorizeUser($fiscalperiod);

        if ($fiscalperiod->status === 'closed') {
            return back()->with('error', 'Cannot delete a closed fiscal period.');
        }

        $fiscalperiod->delete();

        return redirect()
            ->route('admin.fiscalperiod.index')
            ->with('success', 'Fiscal period deleted successfully.');
    }

    /**
     * Calculate balance sheet summary.
     */
    protected function calculateBalanceSheetSummary(FiscalPeriods $fiscalperiod): array
    {
        $assets = $fiscalperiod->balanceSheets()
            ->where('item_type', 'asset')
            ->sum('amount');

        $liabilities = $fiscalperiod->balanceSheets()
            ->where('item_type', 'liability')
            ->sum('amount');

        $equity = $fiscalperiod->balanceSheets()
            ->where('item_type', 'equity')
            ->sum('amount');

        return [
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'net_worth' => $assets - $liabilities,
            'balance_check' => ($liabilities + $equity) == $assets ? true : false,
        ];
    }

    /**
     * Generate HTML for balance sheet.
     */
    protected function generateBalanceSheetHTML(FiscalPeriods $fiscalperiod, $balanceSheetItems, array $summary): string
    {
        $html = '<h1>' . $fiscalperiod->name . '</h1>';
        $html .= '<p>Period: ' . $fiscalperiod->opening_date . ' to ' . $fiscalperiod->closing_date . '</p>';
        $html .= '<table border="1" cellpadding="5" width="100%">';
        $html .= '<tr><th>Item Type</th><th>Name</th><th>Amount</th><th>As Of Date</th></tr>';

        foreach ($balanceSheetItems as $item) {
            $html .= '<tr>';
            $html .= '<td>' . ucfirst($item->item_type) . '</td>';
            $html .= '<td>' . $item->name . '</td>';
            $html .= '<td>' . number_format($item->amount, 2) . '</td>';
            $html .= '<td>' . $item->as_of_date . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Authorize user access.
     */
    protected function authorizeUser(FiscalPeriods $fiscalperiod): void
    {
        if ($fiscalperiod->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access');
        }
    }
}
