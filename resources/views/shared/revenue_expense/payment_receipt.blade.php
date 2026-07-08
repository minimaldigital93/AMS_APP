<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.payment_receipt') }} — {{ $tenant->name ?? __('messages.tenant') }} — {{ $apartment?->apartment_number ?? 'N/A' }}</title>
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

        <!-- Receipt title + status -->
        <div class="center" style="margin-bottom:10px;">
            <div style="font-size:14px;font-weight:700;letter-spacing:.5px;">{{ strtoupper(__('messages.payment_receipt')) }}</div>
            <div style="margin-top:6px;">
                <span class="badge {{ $isPaid ? 'paid' : 'due' }}">{{ $isPaid ? __('messages.paid') : __('messages.pending') }}</span>
            </div>
        </div>

        <!-- Tenant & payment meta -->
        <div class="meta-row"><span class="label">{{ __('messages.receipt_number') }}</span><span class="value">{{ $receiptNumber }}</span></div>
        <div class="meta-row"><span class="label">{{ __('messages.payment_date') }}</span><span class="value">{{ \Carbon\Carbon::parse($paymentDate)->format('M d, Y · h:i A') }}</span></div>
        <div class="meta-row"><span class="label">{{ __('messages.billing_period') }}</span><span class="value">{{ $monthYear }}</span></div>
        <div class="meta-row"><span class="label">{{ __('messages.tenant') }}</span><span class="value">{{ $tenant->name ?? '—' }}</span></div>
        @if($property)
            <div class="meta-row"><span class="label">{{ __('messages.property_name') }}</span><span class="value">{{ $property->name }}</span></div>
        @endif
        <div class="meta-row"><span class="label">{{ __('messages.room_number') }}</span><span class="value">{{ $apartment?->apartment_number ?? 'N/A' }}</span></div>
        <div class="meta-row"><span class="label">{{ __('messages.bill_reference_number') }}</span><span class="value">{{ $billReference }}</span></div>

        <hr class="divider">

        <!-- Line items -->
        <table class="items">
            <thead>
                <tr>
                    <th class="name">{{ __('messages.description') }}</th>
                    <th class="qty">{{ __('messages.qty') }}</th>
                    <th class="price">{{ __('messages.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="name">{{ __('messages.rent') }} — {{ $monthYear }}</td>
                    <td class="qty">1</td>
                    <td class="price">{{ money($rentAmount) }}</td>
                </tr>
                @foreach($utilities as $utility)
                    <tr>
                        <td class="name">{{ ucfirst(str_replace('_', ' ', $utility->utility_type)) }}</td>
                        <td class="qty">1</td>
                        <td class="price">{{ money($utility->charge_amount) }}</td>
                    </tr>
                @endforeach
                @foreach($fixedExpenses as $expense)
                    <tr>
                        <td class="name">{{ $expense->expense_name }}</td>
                        <td class="qty">1</td>
                        <td class="price">{{ money($expense->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <hr class="divider">

        <!-- Total -->
        <div class="total-line">
            <span class="label">{{ __('messages.total') }}</span>
            <span class="amount">{{ money($totalBill) }}</span>
        </div>

        <div style="margin-top:10px;">
            <div class="pay-row">
                <span class="muted">{{ __('messages.payment_method') }}</span>
                <span>{{ $paymentMethod ? strtoupper($paymentMethod) : '—' }}</span>
            </div>
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
            @if($reference)
                <div class="pay-row">
                    <span class="muted">{{ __('messages.transaction_reference') }}</span>
                    <span>{{ $reference }}</span>
                </div>
            @endif
        </div>

        @if($note)
            <hr class="divider">
            <div>
                <div class="muted" style="font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px;">{{ __('messages.notes') }}</div>
                <div class="notes">{{ $note }}</div>
            </div>
        @endif

        <hr class="divider">

        <div class="meta-row"><span class="label">{{ __('messages.generated_by') }}</span><span class="value">{{ $generatedBy }}</span></div>
        <div class="meta-row"><span class="label muted" style="font-size:11px;">{{ $generatedAt->format('M d, Y · h:i A') }}</span><span class="value"></span></div>

        <hr class="divider">

        <div class="center" style="margin-top:6px;">
            <div class="thank-you">{{ strtoupper(__('messages.thank_you_payment')) }}</div>
        </div>
    </div>

    <script>
        // Auto-open the print dialog when navigated to with ?print=1
        if (new URLSearchParams(window.location.search).get('print') === '1') {
            window.addEventListener('load', () => window.print());
        }
    </script>
</body>
</html>
