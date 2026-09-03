<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isReceipt ? __('messages.payment_receipt') : __('messages.bill_summary') }} — {{ $tenant->name ?? __('messages.tenant') }} — {{ $apartment?->apartment_number ?? 'N/A' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #e5e7eb;
            color: #1a1a1a;
            padding: 24px 12px;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Action bar (hidden when printing) */
        .actions {
            max-width: 320px;
            margin: 0 auto 16px;
            display: flex;
            gap: 8px;
        }
        .btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 12px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-print { background: #2563eb; color: #fff; }
        .btn-print:hover { background: #1d4ed8; }
        .btn-back { background: #fff; color: #374151; border: 1px solid #d1d5db; }
        .btn-back:hover { background: #f9fafb; }

        /* Receipt paper */
        .receipt {
            max-width: 320px;
            margin: 0 auto;
            background: #fff;
            padding: 26px 22px 30px;
            box-shadow: 0 6px 24px rgba(0,0,0,.12);
        }

        .center { text-align: center; }
        .muted { color: #6b7280; }
        .logo { max-width: 96px; max-height: 96px; object-fit: contain; margin: 0 auto 10px; display: block; }
        .company-name { font-size: 20px; font-weight: 800; letter-spacing: .3px; margin-bottom: 6px; }
        .company-line { font-size: 12px; color: #4b5563; }

        .divider { border: none; border-top: 1px dashed #9ca3af; margin: 14px 0; }

        /* Meta rows (receipt no, tenant, property…) */
        .meta-row { display: flex; justify-content: space-between; gap: 12px; padding: 2px 0; font-size: 12px; }
        .meta-row .label { color: #6b7280; white-space: nowrap; }
        .meta-row .value { text-align: right; font-weight: 600; color: #111827; word-break: break-word; }

        /* Items */
        .items { width: 100%; border-collapse: collapse; }
        .items th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #6b7280;
            font-weight: 600;
            padding-bottom: 6px;
        }
        .items th.qty, .items th.price, .items td.qty, .items td.price { text-align: right; }
        .items td { padding: 4px 0; font-size: 12.5px; vertical-align: top; }
        .items td.name { padding-right: 8px; }

        /* Totals */
        .total-line { display: flex; justify-content: space-between; align-items: baseline; }
        .total-line .label { font-size: 18px; font-weight: 800; }
        .total-line .amount { font-size: 20px; font-weight: 800; }
        .pay-row { display: flex; justify-content: space-between; font-size: 12.5px; padding: 2px 0; }
        .pay-row .muted { color: #6b7280; text-transform: uppercase; letter-spacing: .3px; font-size: 11.5px; }

        .badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.due { background: #fef3c7; color: #92400e; }

        /* Per-line settlement tag — a bill summary lists what has settled and
           what hasn't side by side, so each line says which it is. */
        .tag {
            display: inline-block;
            margin-left: 5px;
            padding: 0 5px;
            border-radius: 4px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
            vertical-align: 1px;
        }
        .tag.paid { background: #dcfce7; color: #166534; }
        .tag.due { background: #fef3c7; color: #92400e; }

        /* Receipt picker — rent and charges settle on separate visits, so a
           month routinely holds more than one receipt. */
        .picker { max-width: 320px; margin: 0 auto 16px; }
        .picker-title {
            font-size: 10.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #6b7280; margin-bottom: 6px;
        }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .chip {
            display: inline-flex; align-items: baseline; gap: 5px;
            padding: 5px 9px; border-radius: 7px; text-decoration: none;
            background: #fff; border: 1px solid #d1d5db; color: #374151;
            font-size: 11.5px; line-height: 1.3;
        }
        .chip:hover { background: #f9fafb; }
        .chip.active { background: #2563eb; border-color: #2563eb; color: #fff; }
        .chip .chip-amt { font-weight: 700; }
        .chip .chip-when { font-size: 10.5px; opacity: .75; }

        /* Scan-to-pay: the account's static KHQR, printed only on a document
           that still owes money. */
        .pay-qr-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .6px; color: #374151; margin-bottom: 6px;
        }
        .pay-qr-img {
            width: 160px; height: 160px; object-fit: contain; display: block;
            margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 6px;
            padding: 4px; background: #fff;
        }
        .pay-qr-name { margin-top: 6px; font-size: 12.5px; font-weight: 700; color: #111827; word-break: break-word; }
        .pay-qr-amount { margin-top: 4px; font-size: 14px; font-weight: 800; }

        .notes { font-size: 12px; color: #4b5563; word-break: break-word; }
        .thank-you { font-weight: 700; letter-spacing: 1px; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .receipt { box-shadow: none; max-width: 100%; padding: 8px 6px; }
            @page { margin: 6mm; }
        }
    </style>
    @include('partials.khmer_fonts')
</head>
<body>
    <!-- Actions -->
    @unless(request()->boolean('embed'))
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()" title="{{ __('messages.print_receipt') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ __('messages.print_receipt') }}
        </button>
        <a class="btn btn-back" href="{{ url()->previous() }}" title="{{ __('messages.back') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            {{ __('messages.back') }}
        </a>
    </div>
    @endunless

    @php
        // Every link stays inside whichever panel and frame we were opened in.
        $link = fn (?int $paymentId) => route($panel.'.revenue_expense.print_receipt', array_filter([
            'rental' => $rental->id,
            'month' => $month,
            'year' => $year,
            'payment' => $paymentId,
            'embed' => request()->boolean('embed') ? 1 : null,
        ], fn ($v) => $v !== null));
    @endphp

    <!-- Which payment: a month holds one receipt per collection visit -->
    <div class="picker no-print">
        <div class="picker-title">{{ __('messages.receipts_this_month') }} — {{ $monthYear }}</div>
        <div class="chips">
            <a class="chip {{ $isReceipt ? '' : 'active' }}" href="{{ $link(null) }}">{{ __('messages.bill_summary') }}</a>
            @forelse($monthPayments as $p)
                <a class="chip {{ $currentPaymentId === $p->id ? 'active' : '' }}" href="{{ $link($p->id) }}">
                    <span>{{ __('messages.'.$p->payment_type) }}</span>
                    <span class="chip-amt">{{ money($p->amount + $p->late_fee) }}</span>
                    <span class="chip-when">{{ optional($p->paid_at)->format('M j') }}</span>
                </a>
            @empty
                <span style="font-size:11.5px;color:#6b7280;">{{ __('messages.no_payments_recorded_month') }}</span>
            @endforelse
        </div>
    </div>

    <!-- Receipt -->
    <div class="receipt">
        @php $logo = settings('company_logo'); @endphp

        <!-- Company header -->
        <div class="center">
            @if($logo)
                <img class="logo" src="{{ asset('storage/' . $logo) }}" alt="{{ settings('company_name') }}">
            @endif
            <div class="company-name">{{ settings('company_name') ?: config('app.name') }}</div>
            @if(settings('company_address'))
                <div class="company-line">{{ settings('company_address') }}</div>
            @endif
            @php
                $contact = array_filter([
                    settings('company_phone') ? __('messages.tel') . ': ' . settings('company_phone') : null,
                    settings('company_email') ?: null,
                ]);
            @endphp
            @if(count($contact))
                <div class="company-line">{{ implode('  ·  ', $contact) }}</div>
            @endif
            @if(settings('company_website'))
                <div class="company-line">{{ settings('company_website') }}</div>
            @endif
        </div>

        <hr class="divider">

        <!-- Document title + status -->
        <div class="center" style="margin-bottom:10px;">
            <div style="font-size:14px;font-weight:700;letter-spacing:.5px;">
                {{ strtoupper($isReceipt ? __('messages.payment_receipt') : __('messages.bill_summary')) }}
            </div>
            <div style="margin-top:6px;">
                <span class="badge {{ $isPaid ? 'paid' : 'due' }}">{{ $isPaid ? __('messages.paid') : __('messages.outstanding') }}</span>
            </div>
        </div>

        <!-- Tenant & payment meta -->
        @if($receiptNumber)
            <div class="meta-row"><span class="label">{{ __('messages.receipt_number') }}</span><span class="value">{{ $receiptNumber }}</span></div>
        @endif
        @if($paymentDate)
            <div class="meta-row"><span class="label">{{ __('messages.payment_date') }}</span><span class="value">{{ $paymentDate->format('M d, Y · h:i A') }}</span></div>
        @endif
        <div class="meta-row">
            <span class="label">{{ __('messages.billing_period') }}</span>
            <span class="value">{{ $periodLabel ?? $monthYear }}</span>
        </div>
        <div class="meta-row"><span class="label">{{ __('messages.tenant') }}</span><span class="value">{{ $tenant->name ?? '—' }}</span></div>
        @if($property)
            <div class="meta-row"><span class="label">{{ __('messages.property_name') }}</span><span class="value">{{ $property->name }}</span></div>
        @endif
        <div class="meta-row"><span class="label">{{ __('messages.room_number') }}</span><span class="value">{{ $apartment?->apartment_number ?? 'N/A' }}</span></div>
        <div class="meta-row"><span class="label">{{ __('messages.bill_reference_number') }}</span><span class="value">{{ $billReference }}</span></div>

        <hr class="divider">

        <!-- Line items: on a receipt, only what this payment collected -->
        <table class="items">
            <thead>
                <tr>
                    <th class="name">{{ __('messages.description') }}</th>
                    <th class="qty">{{ __('messages.qty') }}</th>
                    <th class="price">{{ __('messages.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lines as $line)
                    @php
                        $utility = $line['utility'] ?? null;
                        $hasReadings = $utility && ($utility->meter_reading_in !== null || $utility->meter_reading_out !== null);
                        $usage = ($utility && $utility->meter_reading_in !== null && $utility->meter_reading_out !== null)
                            ? max($utility->meter_reading_out - $utility->meter_reading_in, 0) : null;
                        $fmt = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 2), '0'), '.') : '—';
                    @endphp
                    <tr>
                        <td class="name">
                            {{ $line['label'] }}
                            {{-- Settlement tags only make sense on the summary; every
                                 line on a receipt is money already taken. --}}
                            @unless($isReceipt)
                                <span class="tag {{ $line['settled'] ? 'paid' : 'due' }}">{{ $line['settled'] ? __('messages.paid') : __('messages.unpaid') }}</span>
                            @endunless
                            @if(!empty($line['sublabel']))
                                <div class="muted" style="font-size:11px;margin-top:1px;">{{ $line['sublabel'] }}</div>
                            @endif
                            @if($hasReadings)
                                <div class="muted" style="font-size:11px;margin-top:1px;">
                                    {{ __('messages.meter_in') }} {{ $fmt($utility->meter_reading_in) }}
                                    → {{ __('messages.meter_out') }} {{ $fmt($utility->meter_reading_out) }}
                                    · {{ __('messages.usage') }} {{ $fmt($usage) }}
                                </div>
                            @endif
                        </td>
                        <td class="qty">1</td>
                        <td class="price">{{ money($line['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <hr class="divider">

        <!-- Total -->
        <div class="total-line">
            <span class="label">{{ $isReceipt ? __('messages.amount_received') : __('messages.total') }}</span>
            <span class="amount">{{ money($total) }}</span>
        </div>

        <div style="margin-top:10px;">
            @if($isReceipt)
                <div class="pay-row">
                    <span class="muted">{{ __('messages.payment_method') }}</span>
                    <span>{{ $paymentMethod ? strtoupper($paymentMethod) : '—' }}</span>
                </div>
                @if($reference)
                    <div class="pay-row">
                        <span class="muted">{{ __('messages.transaction_reference') }}</span>
                        <span>{{ $reference }}</span>
                    </div>
                @endif
            @else
                <div class="pay-row">
                    <span class="muted">{{ __('messages.amount_paid') }}</span>
                    <span style="font-weight:700;">{{ money($amountPaid) }}</span>
                </div>
                @if($balance > 0)
                    <div class="pay-row">
                        <span class="muted">{{ __('messages.balance_due') }}</span>
                        <span style="font-weight:700;color:#b45309;">{{ money($balance) }}</span>
                    </div>
                @endif
            @endif
        </div>

        @if($note)
            <hr class="divider">
            <div>
                <div class="muted" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px;">{{ __('messages.notes') }}</div>
                <div class="notes">{{ $note }}</div>
            </div>
        @endif

        {{-- Scan-to-pay QR (Settings → Payment). Only on a document that still
             owes money — a receipt is money already received. --}}
        @if(!empty($payQrUrl) && $balance > 0)
            <hr class="divider">
            <div class="center">
                <div class="pay-qr-title">{{ __('messages.scan_to_pay') }}</div>
                <img class="pay-qr-img" src="{{ $payQrUrl }}" alt="{{ __('messages.scan_to_pay') }}">
                @if(!empty($payQrName))
                    <div class="pay-qr-name">{{ $payQrName }}</div>
                @endif
                <div class="pay-qr-amount">{{ money($balance) }}</div>
            </div>
        @endif

        <hr class="divider">

        <div class="meta-row"><span class="label">{{ __('messages.generated_by') }}</span><span class="value">{{ $generatedBy }}</span></div>
        <div class="meta-row"><span class="label muted" style="font-size:11px;">{{ $generatedAt->format('M d, Y · h:i A') }}</span><span class="value"></span></div>

        @if($isReceipt)
            <hr class="divider">
            <div class="center" style="margin-top:6px;">
                <div class="thank-you">{{ strtoupper(__('messages.thank_you_payment')) }}</div>
            </div>
        @endif
    </div>

    <script>
        // Auto-open the print dialog when navigated to with ?print=1
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
