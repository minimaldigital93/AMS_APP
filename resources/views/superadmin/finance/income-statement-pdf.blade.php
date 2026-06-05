<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Income Statement') }} — {{ $period->name }}</title>
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $periodLabel = $period->start_date->format('M j, Y') . ' — ' . $period->end_date->format('M j, Y');
    @endphp
    <style>
        :root { --muted:#6b7280; --line:#e5e7eb; --ink:#111827; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: var(--ink); }
        .page { padding: 24px 32px; }
        .header { border-bottom: 2px solid var(--ink); padding-bottom: 12px; margin-bottom: 18px; }
        .company { font-size: 16px; font-weight: 700; }
        h1 { font-size: 20px; margin: 6px 0 2px; letter-spacing: .3px; }
        .meta { color: var(--muted); font-size: 11px; }
        .meta strong { color: var(--ink); }
        table { width: 100%; border-collapse: collapse; }
        .stmt td { padding: 7px 4px; }
        .stmt .section td { padding-top: 16px; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: .5px; color: var(--muted); border-bottom: 1px solid var(--line); }
        .stmt .row td { border-bottom: 1px solid #f3f4f6; }
        .stmt .amount { text-align: right; white-space: nowrap; }
        .stmt .indent { padding-left: 18px; }
        .stmt .subtotal td { border-top: 1px solid var(--ink); font-weight: 700; }
        .stmt .total td { border-top: 2px solid var(--ink); border-bottom: 3px double var(--ink); font-weight: 700; font-size: 14px; }
        .pos { color: #047857; }
        .neg { color: #b91c1c; }
        .breakdown { margin-top: 28px; }
        .breakdown h2 { font-size: 13px; margin: 0 0 8px; }
        .breakdown table { font-size: 11px; }
        .breakdown th, .breakdown td { padding: 6px 8px; border-bottom: 1px solid var(--line); text-align: left; }
        .breakdown thead th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: var(--muted); }
        .breakdown .amount { text-align: right; white-space: nowrap; }
        .breakdown tfoot td { font-weight: 700; border-top: 1px solid var(--ink); }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid var(--line); color: var(--muted); font-size: 10px; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="company">{{ config('app.name', 'Platform') }}</div>
        <h1>{{ __('Income Statement') }}</h1>
        <div class="meta">
            <strong>{{ $period->name }}</strong> &nbsp;·&nbsp; {{ $periodLabel }}
            &nbsp;·&nbsp; {{ $pnl['period_closed'] ? __('Closed') : __('Open') }}
            <br>{{ __('Generated') }}: {{ now()->format('M j, Y g:i A') }}
        </div>
    </div>

    <table class="stmt">
        {{-- Revenue --}}
        <tr class="section"><td>{{ __('Revenue') }}</td><td class="amount"></td></tr>
        <tr class="row">
            <td class="indent">{{ __('Subscription revenue') }}</td>
            <td class="amount">{{ $money($pnl['revenue']) }}</td>
        </tr>
        <tr class="subtotal">
            <td>{{ __('Total revenue') }}</td>
            <td class="amount">{{ $money($pnl['revenue']) }}</td>
        </tr>

        {{-- Expenses --}}
        <tr class="section"><td>{{ __('Operating expenses') }}</td><td class="amount"></td></tr>
        @forelse ($byCategory as $cat)
            <tr class="row">
                <td class="indent">{{ __($cat['label']) }}</td>
                <td class="amount">({{ $money($cat['total']) }})</td>
            </tr>
        @empty
            <tr class="row">
                <td class="indent" style="color:var(--muted)">{{ __('No expenses recorded') }}</td>
                <td class="amount">{{ $money(0) }}</td>
            </tr>
        @endforelse
        <tr class="subtotal">
            <td>{{ __('Total expenses') }}</td>
            <td class="amount">({{ $money($pnl['expense']) }})</td>
        </tr>

        {{-- Net profit --}}
        <tr class="total">
            <td>{{ __('Net profit / (loss)') }}</td>
            <td class="amount {{ $pnl['profit'] >= 0 ? 'pos' : 'neg' }}">
                {{ $pnl['profit'] >= 0 ? $money($pnl['profit']) : '('.$money(abs($pnl['profit'])).')' }}
            </td>
        </tr>
        <tr class="row">
            <td class="indent" style="color:var(--muted)">{{ __('Net margin') }}</td>
            <td class="amount" style="color:var(--muted)">{{ number_format((float) $pnl['margin'], 1) }}%</td>
        </tr>
    </table>

    {{-- Monthly breakdown --}}
    <div class="breakdown">
        <h2>{{ __('Monthly breakdown') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Month') }}</th>
                    <th class="amount">{{ __('Revenue') }}</th>
                    <th class="amount">{{ __('Expense') }}</th>
                    <th class="amount">{{ __('Profit') }}</th>
                    <th class="amount">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pnl['months'] as $m)
                    <tr>
                        <td>{{ $m['label'] }}</td>
                        <td class="amount">{{ $money($m['revenue']) }}</td>
                        <td class="amount">{{ $money($m['expense']) }}</td>
                        <td class="amount {{ $m['profit'] >= 0 ? 'pos' : 'neg' }}">
                            {{ $m['profit'] >= 0 ? $money($m['profit']) : '('.$money(abs($m['profit'])).')' }}
                        </td>
                        <td class="amount">{{ $m['closed'] ? __('Closed') : __('Open') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>{{ __('Total') }}</td>
                    <td class="amount">{{ $money($pnl['revenue']) }}</td>
                    <td class="amount">{{ $money($pnl['expense']) }}</td>
                    <td class="amount {{ $pnl['profit'] >= 0 ? 'pos' : 'neg' }}">
                        {{ $pnl['profit'] >= 0 ? $money($pnl['profit']) : '('.$money(abs($pnl['profit'])).')' }}
                    </td>
                    <td class="amount"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Cash position --}}
    <div class="breakdown">
        <h2>{{ __('Cash position') }}</h2>
        <table>
            <tbody>
                <tr><td>{{ __('Opening balance') }}</td><td class="amount">{{ $money($pnl['opening_balance']) }}</td></tr>
                <tr><td>{{ __('Owner withdrawals') }}</td><td class="amount">({{ $money($pnl['withdrawn_total']) }})</td></tr>
                <tr><td>{{ __('Carried forward') }}</td><td class="amount">{{ $money($pnl['carried_total']) }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        {{ __('This income statement reflects confirmed subscription revenue less recorded platform expenses for the period shown.') }}
    </div>
</div>
</body>
</html>
