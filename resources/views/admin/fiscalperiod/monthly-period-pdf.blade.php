<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $monthlyPeriod->name }} – {{ __('messages.transaction_summary') }}</title>
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
    @include('partials.khmer_fonts')
</head>
<body>

<div class="no-print" style="background:#1e40af;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;margin:-32px -32px 28px -32px;">
    <span style="font-weight:600;">{{ __('messages.monthly_period_preview') }}</span>
    <div style="display:flex;gap:12px;">
        <button onclick="window.print()" style="background:#fff;color:#1e40af;border:none;padding:6px 16px;border-radius:4px;cursor:pointer;font-weight:600;">{{ __('messages.print_save_pdf') }}</button>
        <button onclick="window.history.back()" style="background:transparent;color:#fff;border:1px solid #fff;padding:6px 16px;border-radius:4px;cursor:pointer;">← Back</button>
    </div>
</div>

@include('partials.business_header')

<div class="header">
    <h1>{{ __('messages.monthly_transaction_summary') }}</h1>
    <p>{{ $monthlyPeriod->name }} &nbsp;|&nbsp; {{ $monthlyPeriod->start_date->format('M d, Y') }} – {{ $monthlyPeriod->end_date->format('M d, Y') }}</p>
    <p>Fiscal Period: {{ $fiscalperiod->name }}</p>
    @if($selectedProperty)
        <p style="color:#0369a1;font-size:11px;margin-top:6px;">{{ __('messages.fp_showing_property', ['name' => $selectedProperty->name]) }}</p>
    @elseif(! empty($showingAll))
        <p style="color:#b45309;font-size:11px;margin-top:6px;">{{ __('messages.all_properties_consolidated') }}</p>
    @endif
</div>

<div class="meta">
    <div>
        <div class="label">{{ __('messages.period') }}</div>
        <div class="value">{{ $monthlyPeriod->name }}</div>
    </div>
    <div>
        <div class="label">{{ __('messages.status') }}</div>
        <div class="value">
            <span class="status-badge {{ $monthlyPeriod->status === 'open' ? 'status-open' : 'status-closed' }}">
                {{ status_label($monthlyPeriod->status) }}
            </span>
        </div>
    </div>
    <div>
        <div class="label">{{ __('messages.opening_balance') }}</div>
        <div class="value">{{ money($openingBalance) }}</div>
    </div>
    <div>
        <div class="label">{{ __('messages.closing_balance') }}</div>
        <div class="value">
            @if($closingIsFirm)
                {{ money($closingBalance) }}
            @else
                {{ money($closingBalance) }} <span style="font-size:10px;color:#6b7280;">(projected)</span>
            @endif
        </div>
    </div>
    <div>
        <div class="label">{{ __('messages.generated') }}</div>
        <div class="value">{{ now()->format('M d, Y') }}</div>
    </div>
</div>

{{-- Balance Flow --}}
<div class="balance-flow">
    <div class="flow-card">
        <div class="label">{{ __('messages.opening') }}</div>
        <div class="amount">{{ money($openingBalance) }}</div>
    </div>
    <div class="flow-card income">
        <div class="label">+ Income</div>
        <div class="amount">{{ money($financials['total_income']) }}</div>
    </div>
    <div class="flow-card expense">
        <div class="label">− Expenses</div>
        <div class="amount">{{ money($financials['total_expenses']) }}</div>
    </div>
    <div class="flow-card net">
        <div class="label">{{ __('messages.net') }}</div>
        <div class="amount" style="{{ $financials['net_income'] >= 0 ? 'color:#16a34a' : 'color:#dc2626' }}">
            {{ $financials['net_income'] >= 0 ? '+' : '' }}{{ money($financials['net_income']) }}
        </div>
    </div>
    @if($consolidated && $monthlyPeriod->owner_withdrawal > 0)
    <div class="flow-card">
        <div class="label">− Owner Draw</div>
        <div class="amount" style="color:#7c3aed">{{ money($monthlyPeriod->owner_withdrawal) }}</div>
    </div>
    @endif
    <div class="flow-card">
        <div class="label">{{ __('messages.closing') }}</div>
        <div class="amount">{{ money($closingBalance) }}</div>
    </div>
</div>

{{-- Income Section --}}
<div class="section">
    <h2>{{ __('messages.income') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('messages.category') }}</th>
                <th class="right">{{ __('messages.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Rent Payments ({{ $financials['payment_count'] }} payment{{ $financials['payment_count'] != 1 ? 's' : '' }})</td>
                <td class="right">{{ money($financials['rent_income']) }}</td>
            </tr>
            @if($financials['late_fees'] > 0)
            <tr>
                <td>{{ __('messages.late_fees') }}</td>
                <td class="right">{{ money($financials['late_fees']) }}</td>
            </tr>
            @endif
            @if($financials['other_income'] > 0)
            <tr>
                <td>{{ __('messages.other_income') }}</td>
                <td class="right">{{ money($financials['other_income']) }}</td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('messages.total_income') }}</td>
                <td class="right" style="color:#16a34a;">{{ money($financials['total_income']) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Expenses Section --}}
<div class="section">
    <h2>{{ __('messages.expenses_word') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('messages.category') }}</th>
                <th class="right">{{ __('messages.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($financials['utility_expenses'] as $type => $amount)
            <tr>
                <td style="text-transform:capitalize;">{{ str_replace('_', ' ', $type) }}</td>
                <td class="right">{{ money($amount) }}</td>
            </tr>
            @empty
            @endforelse
            @if($financials['fixed_expenses'] > 0)
            <tr>
                <td>{{ __('messages.fixed_other_expenses') }}</td>
                <td class="right">{{ money($financials['fixed_expenses']) }}</td>
            </tr>
            @endif
            @if(empty($financials['utility_expenses']) && $financials['fixed_expenses'] == 0)
            <tr>
                <td colspan="2" style="color:#9ca3af;text-align:center;">{{ __('messages.no_expenses_recorded_short') }}</td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('messages.total_expenses') }}</td>
                <td class="right" style="color:#dc2626;">{{ money($financials['total_expenses']) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

{{-- Net Result --}}
<div style="background:{{ $financials['net_income'] >= 0 ? '#f0fdf4' : '#fef2f2' }};border:1px solid {{ $financials['net_income'] >= 0 ? '#86efac' : '#fca5a5' }};border-radius:6px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">Net Result for {{ $monthlyPeriod->name }}</div>
        <div style="font-size:11px;color:#9ca3af;margin-top:2px;">{{ __('messages.total_income_minus') }}</div>
    </div>
    <div style="font-size:24px;font-weight:700;color:{{ $financials['net_income'] >= 0 ? '#16a34a' : '#dc2626' }};">
        {{ $financials['net_income'] >= 0 ? '+' : '' }}{{ money($financials['net_income']) }}
    </div>
</div>

@if($consolidated && $monthlyPeriod->owner_withdrawal > 0)
{{-- Owner Profit Withdrawal (owner's draw — not an expense) --}}
<div style="background:#faf5ff;border:1px solid #d8b4fe;border-radius:6px;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;">{{ __('messages.owner_profit_withdrawal') }}</div>
        <div style="font-size:11px;color:#9ca3af;margin-top:2px;">Owner's draw — reduces carried-forward cash, not net income @if($monthlyPeriod->withdrawal_note)&nbsp;|&nbsp; {{ $monthlyPeriod->withdrawal_note }}@endif</div>
    </div>
    <div style="font-size:24px;font-weight:700;color:#7c3aed;">
        − {{ money($monthlyPeriod->owner_withdrawal) }}
    </div>
</div>
@endif

{{-- Balance Sheet as of this month end (auto-calculated, account-wide) --}}
@if($consolidated)
<div style="margin-bottom:24px;">
    <div style="font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:8px;">
        Balance Sheet — as of {{ $monthlyPeriod->end_date->format('M d, Y') }}
        ({{ $balanceSheet['balance_check'] ? 'Balanced' : 'Out of balance' }})
    </div>
    <table>
        <tbody>
            <tr>
                <td>{{ __('messages.assets') }}</td>
                <td class="right" style="color:#2563eb;font-weight:600;">{{ money($balanceSheet['total_assets']) }}</td>
            </tr>
            <tr>
                <td>{{ __('messages.liabilities') }}</td>
                <td class="right" style="color:#dc2626;font-weight:600;">{{ money($balanceSheet['total_liabilities']) }}</td>
            </tr>
            <tr>
                <td>{{ __('messages.equity') }}</td>
                <td class="right" style="color:#16a34a;font-weight:600;">{{ money($balanceSheet['total_equity']) }}</td>
            </tr>
        </tbody>
    </table>
</div>
@endif

<div class="footer">
    <p>Generated on {{ now()->format('F d, Y \a\t H:i') }} &nbsp;|&nbsp; {{ $fiscalperiod->name }} &nbsp;|&nbsp; For official use only</p>
</div>

</body>
</html>
