<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $monthlyPeriod->name }} – Transaction Summary</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; padding: 32px; }
        .header { text-align: center; margin-bottom: 28px; border-bottom: 2px solid #1e40af; padding-bottom: 16px; }
        .header h1 { font-size: 22px; font-weight: 700; color: #1e40af; }
        .header p { color: #555; margin-top: 4px; font-size: 12px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px; }
        .meta div { font-size: 12px; }
        .meta .label { color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px; }
        .meta .value { font-weight: 700; color: #111; font-size: 14px; }
        .balance-flow { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 24px; }
        .flow-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; text-align: center; }
        .flow-card .label { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: 600; }
        .flow-card .amount { font-size: 16px; font-weight: 700; margin-top: 4px; }
        .flow-card.income .amount { color: #16a34a; }
        .flow-card.expense .amount { color: #dc2626; }
        .flow-card.net .amount { color: #1e40af; }
        .flow-arrow { display: flex; align-items: center; justify-content: center; font-size: 18px; color: #9ca3af; }
        .section { margin-bottom: 22px; }
        .section h2 { font-size: 14px; font-weight: 700; color: #374151; border-left: 3px solid #1e40af; padding-left: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table thead tr { background: #1e40af; color: #fff; }
        table th { padding: 8px 10px; text-align: left; font-weight: 600; }
        table th.right { text-align: right; }
        table td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
        table td.right { text-align: right; }
        table tr:nth-child(even) { background: #f9fafb; }
        table tfoot tr { background: #f3f4f6; font-weight: 700; }
        table tfoot td { padding: 8px 10px; border-top: 2px solid #d1d5db; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 700; }
        .status-open { background: #dcfce7; color: #166534; }
        .status-closed { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 32px; padding-top: 14px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; }
        @media print {
            body { padding: 20px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="background:#1e40af;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;margin:-32px -32px 28px -32px;">
    <span style="font-weight:600;">Monthly Period Summary – Print Preview</span>
    <div style="display:flex;gap:12px;">
        <button onclick="window.print()" style="background:#fff;color:#1e40af;border:none;padding:6px 16px;border-radius:4px;cursor:pointer;font-weight:600;">Print / Save PDF</button>
        <button onclick="window.history.back()" style="background:transparent;color:#fff;border:1px solid #fff;padding:6px 16px;border-radius:4px;cursor:pointer;">← Back</button>
    </div>
</div>

<div class="header">
    <h1>Monthly Transaction Summary</h1>
    <p>{{ $monthlyPeriod->name }} &nbsp;|&nbsp; {{ $monthlyPeriod->start_date->format('M d, Y') }} – {{ $monthlyPeriod->end_date->format('M d, Y') }}</p>
    <p>Fiscal Period: {{ $fiscalperiod->name }}</p>
</div>

<div class="meta">
    <div>
        <div class="label">Period</div>
        <div class="value">{{ $monthlyPeriod->name }}</div>
    </div>
    <div>
        <div class="label">Status</div>
        <div class="value">
            <span class="status-badge {{ $monthlyPeriod->status === 'open' ? 'status-open' : 'status-closed' }}">
                {{ ucfirst($monthlyPeriod->status) }}
            </span>
        </div>
    </div>
    <div>
        <div class="label">Opening Balance</div>
        <div class="value">${{ number_format($monthlyPeriod->opening_balance, 2) }}</div>
    </div>
    <div>
        <div class="label">Closing Balance</div>
        <div class="value">
            @if($monthlyPeriod->isClosed())
                ${{ number_format($monthlyPeriod->closing_balance, 2) }}
            @else
                ${{ number_format($monthlyPeriod->opening_balance + $financials['net_income'], 2) }} <span style="font-size:10px;color:#6b7280;">(projected)</span>
            @endif
        </div>
    </div>
    <div>
        <div class="label">Generated</div>
        <div class="value">{{ now()->format('M d, Y') }}</div>
    </div>
</div>

{{-- Balance Flow --}}
<div class="balance-flow">
    <div class="flow-card">
        <div class="label">Opening</div>
        <div class="amount">${{ number_format($monthlyPeriod->opening_balance, 2) }}</div>
    </div>
    <div class="flow-card income">
        <div class="label">+ Income</div>
        <div class="amount">${{ number_format($financials['total_income'], 2) }}</div>
    </div>
    <div class="flow-card expense">
        <div class="label">− Expenses</div>
        <div class="amount">${{ number_format($financials['total_expenses'], 2) }}</div>
    </div>
    <div class="flow-card net">
        <div class="label">Net</div>
        <div class="amount" style="{{ $financials['net_income'] >= 0 ? 'color:#16a34a' : 'color:#dc2626' }}">
            {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
        </div>
    </div>
    <div class="flow-card">
        <div class="label">Closing</div>
        <div class="amount">
            @if($monthlyPeriod->isClosed())
                ${{ number_format($monthlyPeriod->closing_balance, 2) }}
            @else
                ${{ number_format($monthlyPeriod->opening_balance + $financials['net_income'], 2) }}
            @endif
        </div>
    </div>
</div>

{{-- Income Section --}}
<div class="section">
    <h2>Income</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Rent Payments ({{ $financials['payment_count'] }} payment{{ $financials['payment_count'] != 1 ? 's' : '' }})</td>
                <td class="right">${{ number_format($financials['rent_income'], 2) }}</td>
            </tr>
            @if($financials['late_fees'] > 0)
            <tr>
                <td>Late Fees</td>
                <td class="right">${{ number_format($financials['late_fees'], 2) }}</td>
            </tr>
            @endif
            @if($financials['other_income'] > 0)
            <tr>
                <td>Other Income</td>
                <td class="right">${{ number_format($financials['other_income'], 2) }}</td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td>Total Income</td>
                <td class="right" style="color:#16a34a;">${{ number_format($financials['total_income'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Expenses Section --}}
<div class="section">
    <h2>Expenses</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($financials['utility_expenses'] as $type => $amount)
            <tr>
                <td style="text-transform:capitalize;">{{ str_replace('_', ' ', $type) }}</td>
                <td class="right">${{ number_format($amount, 2) }}</td>
            </tr>
            @empty
            @endforelse
            @if($financials['fixed_expenses'] > 0)
            <tr>
                <td>Fixed / Other Expenses</td>
                <td class="right">${{ number_format($financials['fixed_expenses'], 2) }}</td>
            </tr>
            @endif
            @if(empty($financials['utility_expenses']) && $financials['fixed_expenses'] == 0)
            <tr>
                <td colspan="2" style="color:#9ca3af;text-align:center;">No expenses recorded</td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td>Total Expenses</td>
                <td class="right" style="color:#dc2626;">${{ number_format($financials['total_expenses'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Net Result --}}
<div style="background:{{ $financials['net_income'] >= 0 ? '#f0fdf4' : '#fef2f2' }};border:1px solid {{ $financials['net_income'] >= 0 ? '#86efac' : '#fca5a5' }};border-radius:6px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Net Result for {{ $monthlyPeriod->name }}</div>
        <div style="font-size:11px;color:#9ca3af;margin-top:2px;">Total Income − Total Expenses</div>
    </div>
    <div style="font-size:24px;font-weight:700;color:{{ $financials['net_income'] >= 0 ? '#16a34a' : '#dc2626' }};">
        {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
    </div>
</div>

<div class="footer">
    <p>Generated on {{ now()->format('F d, Y \a\t H:i') }} &nbsp;|&nbsp; {{ $fiscalperiod->name }} &nbsp;|&nbsp; For official use only</p>
</div>

</body>
</html>
