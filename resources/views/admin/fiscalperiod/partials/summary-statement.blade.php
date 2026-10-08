{{-- Financial statements, laid out the way an accountant reads them but kept
     plain: four headline figures, then (1) the income statement with a % of
     revenue column, (2) a statement of cash flows and (3) a month-by-month
     breakdown. Negatives in (brackets), results double-ruled.

     Two consumers: the printed Overview tab of Reports & Exports (whole period,
     $cash = cashFlow()) and the date-range PDF (KhmerPdf / mPDF,
     $cash = cashForRange()). Both carry the same per-month rows in
     $cash['months']. Inline styles and tables only, so it renders the same in
     browser print and in mPDF.

     Vars: $pf (FiscalPeriodFinancialsService::forRange shape)
           $cash (opening_balance, closing_balance, total_withdrawals, is_consolidated, months[])
           $from, $to (Carbon; $to may be null for an open-ended period)
           $scopeLabel (property name or "All properties")
           $drawsByMonthEnd (optional) — add the note on how draws are dated --}}
@php
    $acct = fn ($v) => round($v, 2) < 0 ? '('.money(abs($v)).')' : money($v);
    $less = fn ($v) => $acct(-$v); // a deduction: bracketed unless it is zero
    $pct = fn ($v) => $pf['total_income'] > 0 ? number_format($v / $pf['total_income'] * 100, 1).'%' : '—';
    $revenueLines = array_filter([
        __('messages.rent_income') => $pf['rent_income'],
        __('messages.fs_utility_recoveries') => $pf['utility_income'],
        __('messages.late_fees') => $pf['late_fees'],
        __('messages.fs_deposits_received') => $pf['deposit_income'],
        __('messages.other_income') => $pf['misc_income'],
    ], fn ($v) => round($v, 2) != 0);
    $expenseLines = [];
    foreach ($pf['utility_expenses'] as $type => $amount) {
        $expenseLines[ucfirst(str_replace('_', ' ', $type))] = $amount;
    }
    foreach ($pf['operating_expenses'] as $cat => $amount) {
        $label = $cat === \App\Models\Accounts::CAT_DEPOSIT_EXPENSE
            ? __('messages.fs_deposit_refunds')
            : \App\Models\ExpenseCategory::labelFor($cat);
        $expenseLines[$label] = ($expenseLines[$label] ?? 0) + $amount;
    }

    // Cash flows: deposits are shown on their own lines, both ways, because
    // they are tenants' money held rather than earnings.
    $depositRefunds = (float) ($pf['operating_expenses'][\App\Models\Accounts::CAT_DEPOSIT_EXPENSE] ?? 0);
    $consolidated = $cash['is_consolidated'];
    $netChange = $pf['net_income'] - $cash['total_withdrawals'];

    $months = $cash['months'] ?? [];
    $anyPartial = collect($months)->contains(fn ($m) => $m['partial'] ?? false);

    $fromLabel = $from?->format('d M Y');
    $toLabel = $to?->format('d M Y') ?? __('messages.current');
    $th = 'padding:6px 8px; font-size:10px; text-transform:uppercase; letter-spacing:0.05em; color:#374151; border-bottom:1.5px solid #111827;';
    $td = 'padding:4px 8px; font-size:11px; color:#111827;';
    $num = 'text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap;';
    $sec = 'padding:10px 8px 4px; font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;';
    $sub = 'border-top:1px solid #111827;';
    $dbl = 'border-bottom:3px double #111827;';
    $tile = 'width:25%; padding:8px 10px; border:1px solid #e5e7eb; background:#f9fafb; vertical-align:top;';
    $tileLabel = 'font-size:9px; text-transform:uppercase; letter-spacing:0.05em; color:#6b7280;';
    $tileValue = 'font-size:14px; font-weight:700; margin-top:3px; white-space:nowrap;';
@endphp
<div style="color:#111827;">
    <div style="text-align:center; margin:4px 0 12px;">
        <div style="font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">{{ __('messages.fs_title') }}</div>
        <div style="font-size:11px; color:#4b5563; margin-top:2px;">{{ __('messages.fs_for_period_from_to', ['from' => $fromLabel, 'to' => $toLabel]) }}</div>
        <div style="font-size:10px; color:#6b7280; margin-top:1px;">{{ __('messages.fs_amounts_in', ['currency' => currency_symbol()]) }}</div>
    </div>

    {{-- The four figures most readers came for --}}
    <table style="width:100%; border-collapse:separate; border-spacing:6px 0; margin-bottom:16px; page-break-inside:avoid;">
        <tr>
            <td style="{{ $tile }}">
                <div style="{{ $tileLabel }}">{{ __('messages.total_revenue') }}</div>
                <div style="{{ $tileValue }}">{{ $acct($pf['total_income']) }}</div>
            </td>
            <td style="{{ $tile }}">
                <div style="{{ $tileLabel }}">{{ __('messages.total_expenses') }}</div>
                <div style="{{ $tileValue }}">{{ $acct($pf['total_expenses']) }}</div>
            </td>
            <td style="{{ $tile }}">
                <div style="{{ $tileLabel }}">{{ $pf['is_profitable'] ? __('messages.fs_tile_net_profit') : __('messages.fs_tile_net_loss') }}</div>
                <div style="{{ $tileValue }} color:{{ $pf['is_profitable'] ? '#047857' : '#b91c1c' }};">{{ $acct($pf['net_income']) }}</div>
            </td>
            <td style="{{ $tile }}">
                <div style="{{ $tileLabel }}">{{ __('messages.fs_tile_cash_end') }}</div>
                <div style="{{ $tileValue }}">{{ $acct($cash['closing_balance']) }}</div>
            </td>
        </tr>
    </table>

    {{-- 1. Income statement --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:18px;">
        <thead>
            <tr>
                <th style="{{ $th }} text-align:left;">1. {{ __('messages.fs_statement_of_income') }}</th>
                <th style="{{ $th }} {{ $num }} width:22%;">{{ __('messages.amount') }}</th>
                <th style="{{ $th }} {{ $num }} width:14%;">{{ __('messages.fs_pct_of_revenue') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="3" style="{{ $sec }}">{{ __('messages.revenue_caps') }}</td></tr>
            @forelse($revenueLines as $label => $amount)
                <tr>
                    <td style="{{ $td }} padding-left:22px;">{{ $label }}</td>
                    <td style="{{ $td }} {{ $num }}">{{ $acct($amount) }}</td>
                    <td style="{{ $td }} {{ $num }} color:#6b7280;">{{ $pct($amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="{{ $td }} padding-left:22px; color:#6b7280;">{{ __('messages.fs_none_recorded') }}</td></tr>
            @endforelse
            <tr style="page-break-inside:avoid;">
                <td style="{{ $td }} font-weight:700;">{{ __('messages.total_revenue') }}</td>
                <td style="{{ $td }} {{ $num }} {{ $sub }} font-weight:700;">{{ $acct($pf['total_income']) }}</td>
                <td style="{{ $td }} {{ $num }} {{ $sub }} font-weight:700;">{{ $pf['total_income'] > 0 ? '100.0%' : '—' }}</td>
            </tr>

            <tr><td colspan="3" style="{{ $sec }}">{{ __('messages.fs_operating_expenses') }}</td></tr>
            @forelse($expenseLines as $label => $amount)
                <tr>
                    <td style="{{ $td }} padding-left:22px;">{{ $label }}</td>
                    <td style="{{ $td }} {{ $num }}">{{ $acct($amount) }}</td>
                    <td style="{{ $td }} {{ $num }} color:#6b7280;">{{ $pct($amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="{{ $td }} padding-left:22px; color:#6b7280;">{{ __('messages.fs_none_recorded') }}</td></tr>
            @endforelse
            <tr style="page-break-inside:avoid;">
                <td style="{{ $td }} font-weight:700;">{{ __('messages.total_expenses') }}</td>
                <td style="{{ $td }} {{ $num }} {{ $sub }} font-weight:700;">{{ $less($pf['total_expenses']) }}</td>
                <td style="{{ $td }} {{ $num }} {{ $sub }} font-weight:700;">{{ $pct($pf['total_expenses']) }}</td>
            </tr>

            <tr style="page-break-inside:avoid;">
                <td style="{{ $td }} padding-top:10px; font-weight:700; font-size:12px;">{{ $pf['is_profitable'] ? __('messages.fs_net_profit') : __('messages.fs_net_loss') }}</td>
                <td style="{{ $td }} {{ $num }} padding-top:10px; font-weight:700; font-size:12px; {{ $dbl }}">{{ $acct($pf['net_income']) }}</td>
                <td style="{{ $td }} {{ $num }} padding-top:10px; font-weight:700; {{ $dbl }}">{{ $pct($pf['net_income']) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- 2. Statement of cash flows --}}
    <table style="width:100%; border-collapse:collapse; margin-bottom:18px; page-break-inside:avoid;">
        <thead>
            <tr>
                <th style="{{ $th }} text-align:left;">2. {{ __('messages.fs_statement_of_cash_flows') }}</th>
                <th style="{{ $th }} {{ $num }} width:22%;">{{ __('messages.amount') }}</th>
                <th style="{{ $th }} width:14%;"></th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="3" style="{{ $sec }}">{{ __('messages.fs_cf_operations') }}</td></tr>
            <tr><td style="{{ $td }} padding-left:22px;">{{ __('messages.fs_cf_received') }}</td><td style="{{ $td }} {{ $num }}">{{ $acct($pf['total_income'] - $pf['deposit_income']) }}</td><td></td></tr>
            @if(round($pf['deposit_income'], 2) != 0)
                <tr><td style="{{ $td }} padding-left:22px;">{{ __('messages.fs_deposits_received') }}</td><td style="{{ $td }} {{ $num }}">{{ $acct($pf['deposit_income']) }}</td><td></td></tr>
            @endif
            <tr><td style="{{ $td }} padding-left:22px;">{{ __('messages.fs_cf_paid') }}</td><td style="{{ $td }} {{ $num }}">{{ $less($pf['total_expenses'] - $depositRefunds) }}</td><td></td></tr>
            @if(round($depositRefunds, 2) != 0)
                <tr><td style="{{ $td }} padding-left:22px;">{{ __('messages.fs_deposit_refunds') }}</td><td style="{{ $td }} {{ $num }}">{{ $less($depositRefunds) }}</td><td></td></tr>
            @endif
            <tr>
                <td style="{{ $td }} font-weight:700;">{{ __('messages.fs_cf_net_operations') }}</td>
                <td style="{{ $td }} {{ $num }} {{ $sub }} font-weight:700;">{{ $acct($pf['net_income']) }}</td>
                <td></td>
            </tr>

            @if($consolidated)
                <tr><td colspan="3" style="{{ $sec }}">{{ __('messages.fs_cf_owner') }}</td></tr>
                <tr><td style="{{ $td }} padding-left:22px;">{{ __('messages.fs_cf_draws') }}</td><td style="{{ $td }} {{ $num }}">{{ $less($cash['total_withdrawals']) }}</td><td></td></tr>
            @endif

            <tr>
                <td style="{{ $td }} padding-top:10px; font-weight:700;">{{ __('messages.fs_cf_net_change') }}</td>
                <td style="{{ $td }} {{ $num }} padding-top:10px; {{ $sub }} font-weight:700;">{{ $acct($netChange) }}</td>
                <td></td>
            </tr>
            <tr><td style="{{ $td }}">{{ __('messages.fs_opening_cash', ['date' => $fromLabel]) }}</td><td style="{{ $td }} {{ $num }}">{{ $acct($cash['opening_balance']) }}</td><td></td></tr>
            <tr>
                <td style="{{ $td }} font-weight:700; font-size:12px;">{{ __('messages.fs_closing_cash', ['date' => $toLabel]) }}</td>
                <td style="{{ $td }} {{ $num }} font-weight:700; font-size:12px; {{ $sub }} {{ $dbl }}">{{ $acct($cash['closing_balance']) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- 3. Monthly breakdown --}}
    @if(count($months))
        <table style="width:100%; border-collapse:collapse; margin-bottom:18px;">
            <thead>
                <tr><th colspan="{{ $consolidated ? 6 : 5 }}" style="{{ $th }} text-align:left; border-bottom:none; padding-bottom:2px;">3. {{ __('messages.fs_monthly_breakdown') }}</th></tr>
                <tr>
                    <th style="{{ $th }} text-align:left; color:#6b7280; width:{{ $consolidated ? 25 : 32 }}%;">{{ __('messages.month') }}</th>
                    <th style="{{ $th }} {{ $num }} color:#6b7280; width:{{ $consolidated ? 15 : 17 }}%;">{{ __('messages.income') }}</th>
                    <th style="{{ $th }} {{ $num }} color:#6b7280; width:{{ $consolidated ? 15 : 17 }}%;">{{ __('messages.fs_col_expenses') }}</th>
                    <th style="{{ $th }} {{ $num }} color:#6b7280; width:{{ $consolidated ? 15 : 17 }}%;">{{ __('messages.fs_col_net') }}</th>
                    @if($consolidated)
                        <th style="{{ $th }} {{ $num }} color:#6b7280; width:{{ $consolidated ? 15 : 17 }}%;">{{ __('messages.fs_cf_draws') }}</th>
                    @endif
                    <th style="{{ $th }} {{ $num }} color:#6b7280; width:{{ $consolidated ? 15 : 17 }}%;">{{ __('messages.fs_col_cash_end') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($months as $m)
                    <tr style="page-break-inside:avoid;">
                        <td style="{{ $td }} border-bottom:1px solid #f3f4f6;">
                            {{ $m['name'] }}
                            @if($m['partial'] ?? false)<span style="font-size:9px; color:#6b7280;">({{ __('messages.fs_partial_month') }})</span>@endif
                        </td>
                        <td style="{{ $td }} {{ $num }} border-bottom:1px solid #f3f4f6;">{{ $acct($m['cash_in']) }}</td>
                        <td style="{{ $td }} {{ $num }} border-bottom:1px solid #f3f4f6;">{{ $less($m['cash_out']) }}</td>
                        <td style="{{ $td }} {{ $num }} border-bottom:1px solid #f3f4f6; font-weight:600;">{{ $acct($m['net_cash_flow']) }}</td>
                        @if($consolidated)
                            <td style="{{ $td }} {{ $num }} border-bottom:1px solid #f3f4f6;{{ $m['owner_withdrawal'] ? '' : ' color:#9ca3af;' }}">{{ $m['owner_withdrawal'] ? $less($m['owner_withdrawal']) : '—' }}</td>
                        @endif
                        <td style="{{ $td }} {{ $num }} border-bottom:1px solid #f3f4f6;">{{ $acct($m['closing_balance']) }}</td>
                    </tr>
                @endforeach
                <tr style="page-break-inside:avoid;">
                    <td style="{{ $td }} font-weight:700; {{ $sub }}">{{ __('messages.total') }}</td>
                    <td style="{{ $td }} {{ $num }} font-weight:700; {{ $sub }} {{ $dbl }}">{{ $acct(array_sum(array_column($months, 'cash_in'))) }}</td>
                    <td style="{{ $td }} {{ $num }} font-weight:700; {{ $sub }} {{ $dbl }}">{{ $less(array_sum(array_column($months, 'cash_out'))) }}</td>
                    <td style="{{ $td }} {{ $num }} font-weight:700; {{ $sub }} {{ $dbl }}">{{ $acct(array_sum(array_column($months, 'net_cash_flow'))) }}</td>
                    @if($consolidated)
                        <td style="{{ $td }} {{ $num }} font-weight:700; {{ $sub }} {{ $dbl }}">{{ $less(array_sum(array_column($months, 'owner_withdrawal'))) }}</td>
                    @endif
                    <td style="{{ $td }} {{ $num }} font-weight:700; {{ $sub }} {{ $dbl }}">{{ $acct($cash['closing_balance']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Notes --}}
    <div style="page-break-inside:avoid; margin-bottom:22px;">
        <div style="{{ $th }} border-bottom:1px solid #d1d5db; padding-left:0;">{{ __('messages.fs_notes') }}</div>
        <ol style="margin:6px 0 0 16px; padding:0; font-size:9.5px; color:#374151; line-height:1.5; list-style:decimal;">
            <li>{{ __('messages.fs_note_basis') }}</li>
            @if($pf['deposit_income'] > 0)
                <li>{{ __('messages.fs_note_deposits', ['amount' => money($pf['deposit_income'])]) }}</li>
            @endif
            @if($consolidated)
                <li>{{ __('messages.fs_note_draws') }}</li>
            @endif
            @if(($drawsByMonthEnd ?? false) && $consolidated)
                <li>{{ __('messages.fs_note_draws_month_end') }}</li>
            @endif
            @if($anyPartial)
                <li>{{ __('messages.fs_note_partial') }}</li>
            @endif
            @unless($consolidated)
                <li>{{ __('messages.fs_note_single_property', ['name' => $scopeLabel]) }}</li>
            @endunless
        </ol>
    </div>

    {{-- Sign-off. The rule sits on the cell itself: mPDF shrinks a block
         inside a table cell to its text, which left a stub of a line. --}}
    @php
        $signers = [
            __('messages.fs_prepared_by') => auth()->user()?->name,
            __('messages.fs_reviewed_by') => null,
            __('messages.fs_approved_by') => null,
        ];
    @endphp
    <table style="width:100%; border-collapse:collapse; page-break-inside:avoid; margin-top:8px;">
        <tr>
            @foreach($signers as $role => $who)
                <td style="width:30%; height:38px;"></td>
                @unless($loop->last)<td style="width:5%;"></td>@endunless
            @endforeach
        </tr>
        <tr>
            @foreach($signers as $role => $who)
                <td style="border-top:1px solid #111827; padding-top:4px; vertical-align:top;">
                    <div style="font-size:10px; font-weight:700;">{{ $role }}</div>
                    <div style="font-size:9.5px; color:#4b5563; margin-top:2px;">{{ __('messages.name') }}: {{ $who ?? '' }}</div>
                    <div style="font-size:9.5px; color:#4b5563; margin-top:2px;">{{ __('messages.date') }}: {{ $who ? now()->format('d M Y') : '' }}</div>
                </td>
                @unless($loop->last)<td></td>@endunless
            @endforeach
        </tr>
    </table>
</div>
