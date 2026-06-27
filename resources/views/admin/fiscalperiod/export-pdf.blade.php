<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $fiscalperiod->name }} – {{ __('messages.annual_summary') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; padding: 32px; }
        h2 { font-size: 14px; font-weight: 700; color: #374151; border-left: 3px solid #1e40af; padding-left: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
        table thead tr { background: #1e40af; color: #fff; }
        table th { padding: 8px 10px; text-align: left; font-weight: 600; }
        table th.right { text-align: right; }
        table td { padding: 7px 10px; border-bottom: 1px solid #e5e7eb; }
        table td.right { text-align: right; }
        table tr:nth-child(even) { background: #f9fafb; }
        table tfoot tr { background: #f3f4f6; font-weight: 700; }
        table tfoot td { padding: 8px 10px; border-top: 2px solid #d1d5db; }
        .section { margin-bottom: 22px; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; }
        .box-title { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 10px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 12px; }
        .row .label { color: #6b7280; }
        .row-total { display: flex; justify-content: space-between; padding: 6px 0 2px; font-size: 13px; font-weight: 700; border-top: 1px solid #e5e7eb; margin-top: 4px; }
        .net-box { border-radius: 6px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .footer { margin-top: 32px; padding-top: 14px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; }
        @media print { .no-print { display: none !important; } body { padding: 20px; } }
    </style>
    @include('partials.khmer_fonts')
</head>
<body>

<div class="no-print" style="background:#1e40af;color:#fff;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;margin:-32px -32px 28px -32px;">
    <span style="font-weight:600;">{{ __('messages.annual_summary_preview') }}</span>
    <div style="display:flex;gap:12px;">
        <button onclick="window.print()" style="background:#fff;color:#1e40af;border:none;padding:6px 16px;border-radius:4px;cursor:pointer;font-weight:600;">{{ __('messages.print_save_pdf') }}</button>
        <button onclick="window.history.back()" style="background:transparent;color:#fff;border:1px solid #fff;padding:6px 16px;border-radius:4px;cursor:pointer;">← Back</button>
    </div>
</div>

@include('partials.business_header')

<div style="text-align:center;margin-bottom:28px;border-bottom:2px solid #1e40af;padding-bottom:16px;">
    <h1 style="font-size:22px;font-weight:700;color:#1e40af;">{{ $fiscalperiod->name }}</h1>
    <p style="color:#555;margin-top:4px;font-size:13px;">{{ __('messages.annual_financial_summary') }}</p>
    <p style="color:#9ca3af;font-size:11px;margin-top:3px;">
        {{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}
        &nbsp;|&nbsp; Status: {{ status_label($fiscalperiod->status) }}
        &nbsp;|&nbsp; Generated {{ now()->format('F d, Y') }}
    </p>
    <p style="color:#374151;font-size:12px;margin-top:4px;font-weight:600;">
        {{ __('messages.viewing_property', ['name' => ($selectedProperty->name ?? __('messages.all_properties_consolidated'))]) }}
    </p>
</div>

{{-- Period Details --}}
<div class="section">
    <h2>{{ __('messages.period_details') }}</h2>
    <div class="two-col">
        <div class="box">
            <div class="row"><span class="label">{{ __('messages.opening_date') }}</span><span style="font-weight:600;">{{ $fiscalperiod->opening_date->format('M d, Y') }}</span></div>
            <div class="row"><span class="label">{{ __('messages.closing_date') }}</span><span style="font-weight:600;">{{ $fiscalperiod->closing_date->format('M d, Y') }}</span></div>
        </div>
        <div class="box">
            <div class="row"><span class="label">{{ __('messages.opening_balance') }}</span><span style="font-weight:700;">{{ money($fiscalperiod->opening_balance) }}</span></div>
            <div class="row"><span class="label">{{ __('messages.closing_balance') }}</span><span style="font-weight:700;">{{ money($fiscalperiod->closing_balance) }}</span></div>
        </div>
    </div>
</div>

{{-- Income & Expense Summary --}}
@if(isset($periodFinancials))
<div class="section">
    <h2>{{ __('messages.income_expense_summary') }}@if(!empty($selectedProperty)) — {{ $selectedProperty->name }}@endif</h2>
    <div class="two-col">
        <div class="box">
            <div class="box-title">{{ __('messages.income') }}</div>
            <div class="row"><span class="label">{{ __('messages.rent_payments') }}</span><span style="color:#16a34a;font-weight:600;">{{ money($periodFinancials['rent_income']) }}</span></div>
            @if($periodFinancials['late_fees'] > 0)
            <div class="row"><span class="label">{{ __('messages.late_fees') }}</span><span style="color:#16a34a;font-weight:600;">{{ money($periodFinancials['late_fees']) }}</span></div>
            @endif
            @if($periodFinancials['other_income'] > 0)
            <div class="row"><span class="label">{{ __('messages.other_income') }}</span><span style="color:#16a34a;font-weight:600;">{{ money($periodFinancials['other_income']) }}</span></div>
            @endif
            <div class="row-total"><span>{{ __('messages.total_income') }}</span><span style="color:#16a34a;">{{ money($periodFinancials['total_income']) }}</span></div>
        </div>
        <div class="box">
            <div class="box-title">{{ __('messages.expenses_word') }}</div>
            @foreach($periodFinancials['utility_expenses'] as $type => $amount)
            <div class="row"><span class="label" style="text-transform:capitalize;">{{ str_replace('_',' ',$type) }}</span><span style="color:#dc2626;font-weight:600;">{{ money($amount) }}</span></div>
            @endforeach
            @if($periodFinancials['fixed_expenses'] > 0)
            <div class="row"><span class="label">{{ __('messages.fixed_other') }}</span><span style="color:#dc2626;font-weight:600;">{{ money($periodFinancials['fixed_expenses']) }}</span></div>
            @endif
            <div class="row-total"><span>{{ __('messages.total_expenses') }}</span><span style="color:#dc2626;">{{ money($periodFinancials['total_expenses']) }}</span></div>
        </div>
    </div>
    <div class="net-box" style="background:{{ $periodFinancials['net_income'] >= 0 ? '#f0fdf4' : '#fef2f2' }};border:1px solid {{ $periodFinancials['net_income'] >= 0 ? '#86efac' : '#fca5a5' }};">
        <div style="font-size:13px;font-weight:700;color:#374151;">Net {{ $periodFinancials['net_income'] >= 0 ? 'Profit' : 'Loss' }} for the Year</div>
        <div style="font-size:22px;font-weight:700;color:{{ $periodFinancials['net_income'] >= 0 ? '#16a34a' : '#dc2626' }};">
            {{ $periodFinancials['net_income'] >= 0 ? '+' : '' }}{{ money($periodFinancials['net_income']) }}
        </div>
    </div>
</div>
@endif

{{-- Balance Sheet Items --}}
<div class="section">
    <h2>{{ __('messages.balance_sheet_items') }}</h2>
    @if(!empty($selectedProperty))
        <p style="font-size:11px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:4px;padding:6px 10px;margin-bottom:10px;">{{ __('messages.account_wide_note') }}</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>{{ __('messages.type') }}</th>
                <th>{{ __('messages.name') }}</th>
                <th class="right">{{ __('messages.amount') }}</th>
                <th>{{ __('messages.as_of_date') }}</th>
                <th>{{ __('messages.reference') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($balanceSheetItems as $item)
            <tr>
                <td style="font-weight:600;">{{ ucfirst($item->item_type) }}</td>
                <td>{{ $item->name }}</td>
                <td class="right">{{ money($item->amount) }}</td>
                <td>{{ $item->as_of_date->format('M d, Y') }}</td>
                <td>{{ $item->reference_number ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align:center;color:#9ca3af;">{{ __('messages.no_balance_items') }}</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Balance Sheet Summary --}}
<div class="section">
    <h2>{{ __('messages.balance_sheet_summary') }}</h2>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px;">
        <div class="box" style="text-align:center;">
            <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:6px;">{{ __('messages.total_assets') }}</div>
            <div style="font-size:20px;font-weight:700;color:#1e40af;">{{ money($summary['total_assets']) }}</div>
        </div>
        <div class="box" style="text-align:center;">
            <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:6px;">{{ __('messages.total_liabilities') }}</div>
            <div style="font-size:20px;font-weight:700;color:#dc2626;">{{ money($summary['total_liabilities']) }}</div>
        </div>
        <div class="box" style="text-align:center;">
            <div style="font-size:11px;color:#6b7280;font-weight:600;text-transform:uppercase;margin-bottom:6px;">{{ __('messages.total_equity') }}</div>
            <div style="font-size:20px;font-weight:700;color:#16a34a;">{{ money($summary['total_equity']) }}</div>
        </div>
    </div>
    <div style="background:{{ $summary['balance_check'] ? '#f0fdf4' : '#fefce8' }};border:1px solid {{ $summary['balance_check'] ? '#86efac' : '#fde68a' }};border-radius:6px;padding:12px 16px;font-size:12px;color:{{ $summary['balance_check'] ? '#166534' : '#92400e' }};">
        <strong>Assets ({{ money($summary['total_assets']) }})</strong> =
        <strong>Liabilities ({{ money($summary['total_liabilities']) }})</strong> +
        <strong>Equity ({{ money($summary['total_equity']) }})</strong>
        &nbsp;&nbsp; {{ $summary['balance_check'] ? '✓ Balanced' : '✗ Not Balanced' }}
    </div>
</div>

<div class="footer">
    <p>Generated on {{ now()->format('F d, Y \a\t H:i') }} &nbsp;|&nbsp; For official use only</p>
</div>

<script>
    window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
