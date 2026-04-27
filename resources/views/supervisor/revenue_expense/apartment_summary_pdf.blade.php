<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apartment Summary</title>
    <style>
        /* Owner-friendly print styles */
        :root{--brand:#0ea5a3;--accent:#2563eb;--muted:#6b7280;--bg:#ffffff}
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #111; background: var(--bg); }
        .page { padding: 20px 28px; }
        .header { display:flex; justify-content:space-between; align-items:center; gap:12px }
        .brand { display:flex; gap:12px; align-items:center }
        .brand-logo { width:56px; height:56px; background:var(--brand); border-radius:8px; display:inline-block }
        h1 { font-size:18px; margin:0; letter-spacing:0.2px }
        .company { font-size:12px; color:var(--muted) }
        .kpis { display:flex; gap:10px; margin-top:14px }
        .kpi { flex:1; background:#f8fafc; border:1px solid #e6eef0; padding:10px 12px; border-radius:8px }
        .kpi .label { font-size:11px; color:var(--muted); }
        .kpi .value { font-size:16px; font-weight:700; margin-top:4px }
        table { width:100%; border-collapse:collapse; margin-top:14px; font-size:12px }
        th, td { padding:8px 10px; border-bottom:1px solid #eef2f7; text-align:left }
        thead th { background:#f1f5f9; font-weight:700; color:#0f172a }
        tbody tr:nth-child(odd){ background:#ffffff }
        tbody tr:nth-child(even){ background:#fbfdff }
        .right { text-align:right }
        .muted { color:var(--muted); font-size:11px }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; color:#fff }
        .badge.paid{ background: #10b981 }
        .badge.unpaid{ background: #ef4444 }
        .summary { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
        .summary .tot { font-weight:700 }
        .note { font-size:11px; color:var(--muted); margin-top:10px }
        @media print{ .page{ padding:12mm } .kpi { page-break-inside:avoid } table { page-break-inside:auto } tr { page-break-inside:avoid; page-break-after:auto } }
    </style>
</head>
<body>
    @php
        $summaryOnly = $summaryOnly ?? false;
        $wholeNumbers = $wholeNumbers ?? false;
    @endphp
    @if(request()->header('X-Preview-Mode') === '1' || (isset($preview) && $preview))
    <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
        <form id="auto-gen-form" method="POST" action="{{ route('supervisor.revenue_expense.process_bills_auto') }}" style="margin:0;">
            @csrf
            <input type="hidden" name="billing_date" value="{{ $start }}">
            <button type="submit" style="padding:8px 12px; background:#0ea5a3; color:#fff; border:none; border-radius:6px; cursor:pointer;">Auto-generate bills</button>
        </form>
        <a href="{{ route('supervisor.revenue_expense.apartment_summary_pdf', ['start' => $start, 'end' => $end, 'summary_only' => $summaryOnly ?? false, 'whole' => $wholeNumbers ?? false]) }}" target="_blank" style="padding:8px 12px; background:#2563eb; color:#fff; border-radius:6px; text-decoration:none;">Download PDF</a>
        <button onclick="window.print()" style="padding:8px 12px; background:#6b7280; color:#fff; border:none; border-radius:6px; cursor:pointer;">Print</button>
    </div>
    @endif

    <div class="page">
        <div class="header">
            <div class="brand">
                <div class="brand-logo" aria-hidden></div>
                <div>
                    <h1>Apartment Summary</h1>
                    <div class="company">{{ $activePeriod->name ?? 'Fiscal Period' }} · Period: {{ $start }} — {{ $end }}</div>
                </div>
            </div>
            <div style="text-align:right">
                <div class="muted">Generated: {{ now()->toDateTimeString() }}</div>
            </div>
        </div>
        @php
            // quick KPI totals for owner clarity
            $total_rent = array_sum(array_map(function($a){ return floatval($a['income'] ?? 0); }, $perApartment));
            $total_util = array_sum(array_map(function($a){ return floatval($a['expenses'] ?? 0); }, $perApartment));
            $total_fixed = array_sum(array_map(function($a){ return floatval($a['fixed_expenses'] ?? 0); }, $perApartment));
            $total_net = $total_rent - ($total_util + $total_fixed);
            $paid_units = count(array_filter($perApartment, function($a){ return ($a['rent_status'] ?? '') === 'paid'; }));
            $total_units = count($perApartment);
        @endphp

        @php
            $fmt = function($v) use ($wholeNumbers) {
                if(!isset($v)) return '$0';
                return $wholeNumbers ? ('$' . number_format(round($v), 0)) : ('$' . number_format($v, 2));
            };
        @endphp

        <div class="kpis">
            <div class="kpi">
                <div class="label">Total Rent Recognized</div>
                <div class="value">{{ $fmt($total_rent) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Total Utilities</div>
                <div class="value">{{ $fmt($total_util) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Total Fixed Expenses</div>
                <div class="value">{{ $fmt($total_fixed) }}</div>
            </div>
            <div class="kpi">
                <div class="label">Net (Owner)</div>
                <div class="value">{{ $fmt($total_net) }}</div>
                <div class="muted" style="margin-top:6px">{{ $paid_units }} / {{ $total_units }} units paid</div>
            </div>
        </div>
        @php
            $occupiedList = array_filter($perApartment, fn($a) => !empty($a['has_active_rental']));
        @endphp

        @if(count($occupiedList) > 0)
            <h3 style="margin-top:14px; font-size:13px;">Income by Occupied Apartment</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width:120px">Unit</th>
                        <th class="right">Income</th>
                        <th>Tenant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($occupiedList as $it)
                        <tr>
                            <td>{{ $it['apartment_number'] }}</td>
                            <td class="right">{{ $fmt($it['income'] ?? 0) }}</td>
                            <td class="muted">{{ $it['tenant'] ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    @php
        // Simple GL account code mapping (customize to your chart of accounts)
        $gl = [
            'rent_income' => ['code' => '4000', 'name' => 'Rent Income'],
            'utilities_expense' => ['code' => '5000', 'name' => 'Utilities Expense'],
            'fixed_expense' => ['code' => '5100', 'name' => 'Fixed Expenses (Owner)'],
            'reconciliation' => ['code' => '2000', 'name' => 'Accounts Receivable / Payable']
        ];

        $totalDebits = 0.0;
        $totalCredits = 0.0;
    @endphp

    @if(!empty($summaryOnly))
        @php
            $sum_util = array_sum(array_map(fn($a)=>($a['expenses'] ?? 0), $perApartment));
            $sum_fixed = array_sum(array_map(fn($a)=>($a['fixed_expenses'] ?? 0), $perApartment));
            $sum_expenses = $sum_util + $sum_fixed;
            $sum_revenue = array_sum(array_map(fn($a)=>($a['income'] ?? 0), $perApartment));
            $sum_profit = $sum_revenue - $sum_expenses;
        @endphp

        <h2 style="margin-top:12px; font-size:14px;">Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Revenue</td>
                    <td class="right">{{ $fmt($sum_revenue) }}</td>
                </tr>
                <tr>
                    <td>Expenses</td>
                    <td class="right">{{ $fmt($sum_expenses) }}</td>
                </tr>
                <tr style="font-weight:700; border-top:2px solid #ddd;">
                    <td>Total Profit</td>
                    <td class="right">{{ $fmt($sum_profit) }}</td>
                </tr>
            </tbody>
        </table>
    @else
    <h2 style="margin-top:12px; font-size:14px;">General Ledger Entries (per apartment)</h2>
    <table>
        <thead>
            <tr>
                <th style="width:80px">Unit</th>
                <th style="width:120px">Account Code</th>
                <th>Account</th>
                <th class="right">Debit ($)</th>
                <th class="right">Credit ($)</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($perApartment as $apt)
                {{-- Utilities expense (Debit) --}}
                @php
                    $utilities = floatval($apt['expenses'] ?? 0);
                    $fixed = floatval($apt['fixed_expenses'] ?? 0);
                    // Use collected rent/income as credit; if zero, will be balanced by AR/Payable
                    $rentCredit = floatval($apt['income'] ?? 0);
                @endphp

                @if($utilities > 0)
                <tr>
                    <td>{{ $apt['apartment_number'] }}</td>
                    <td>{{ $gl['utilities_expense']['code'] }}</td>
                    <td>{{ $gl['utilities_expense']['name'] }}</td>
                    <td class="right">{{ number_format($utilities, 2) }}</td>
                    <td class="right">&nbsp;</td>
                    <td>Utilities cost for unit</td>
                </tr>
                @php $totalDebits += $utilities; @endphp
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="summary" style="margin-top:14px">
        <div class="muted">Total Debits</div>
        <div class="tot">{{ $fmt($totalDebits) }}</div>
        <div style="width:12px"></div>
        <div class="muted">Total Credits</div>
        <div class="tot">{{ $fmt($totalCredits) }}</div>
    </div>

    @endif

    </div>
</body>
</html>
