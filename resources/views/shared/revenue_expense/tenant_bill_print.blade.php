<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill — {{ $tenant->name ?? 'Tenant' }} — {{ $apartment?->apartment_number ?? 'N/A' }} — {{ $monthYear }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1a1a1a;
            background: #fff;
            padding: 20px;
            font-size: 14px;
            line-height: 1.5;
        }
        .bill-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header */
        .bill-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .bill-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .bill-header .subtitle {
            font-size: 13px;
            opacity: 0.85;
        }
        .bill-header .bill-info {
            text-align: right;
            font-size: 13px;
        }
        .bill-header .bill-number {
            font-size: 16px;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 4px;
            margin-bottom: 8px;
            display: inline-block;
        }

        /* Tenant & Apartment Info */
        .info-section {
            display: flex;
            justify-content: space-between;
            padding: 24px 30px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-block h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .info-block p {
            font-size: 14px;
            margin-bottom: 3px;
        }
        .info-block .name {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        /* Bill Items Table */
        .bill-body {
            padding: 24px 30px;
        }
        .bill-body h2 {
            font-size: 16px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            padding: 10px 12px;
            border-bottom: 2px solid #e5e7eb;
        }
        .items-table th:last-child {
            text-align: right;
        }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .items-table td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .items-table .category-label {
            color: #6b7280;
            font-size: 12px;
        }

        /* Totals */
        .totals-section {
            margin-top: 16px;
            border-top: 2px solid #e5e7eb;
            padding-top: 16px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 12px;
            font-size: 14px;
        }
        .total-row.grand {
            background: #f0fdf4;
            border: 2px solid #22c55e;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 20px;
            font-weight: 700;
        }
        .total-row.grand .label {
            color: #166534;
        }
        .total-row.grand .amount {
            color: #166534;
        }
        .total-row.paid {
            color: #16a34a;
        }
        .total-row.balance {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 20px;
            font-weight: 700;
            color: #92400e;
        }
        .total-row.paid-full {
            background: #f0fdf4;
            border: 2px solid #22c55e;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 18px;
            font-weight: 700;
            color: #166534;
            text-align: center;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge.paid {
            background: #dcfce7;
            color: #166534;
        }
        .status-badge.unpaid {
            background: #fef3c7;
            color: #92400e;
        }
        .status-badge.overdue {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Footer */
        .bill-footer {
            background: #f9fafb;
            padding: 20px 30px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6b7280;
        }

        /* Payment History */
        .payment-history {
            margin-top: 20px;
        }
        .payment-history h3 {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 12px;
            background: #f0fdf4;
            border-radius: 4px;
            margin-bottom: 4px;
            font-size: 13px;
        }

        /* Print styles */
        @media print {
            body {
                padding: 0;
                background: white;
            }
            .bill-container {
                border: none;
                box-shadow: none;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 10mm;
                size: A4;
            }
        }

        /* Print Button */
        .print-actions {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-print {
            background: #3b82f6;
            color: white;
        }
        .btn-print:hover {
            background: #2563eb;
        }
        .btn-back {
            background: #e5e7eb;
            color: #374151;
        }
        .btn-back:hover {
            background: #d1d5db;
        }
    </style>
    @include('partials.khmer_fonts')
</head>
<body>
    <!-- Print Actions (hidden in print) -->
    <div class="print-actions no-print">
        <button class="btn btn-print" onclick="window.print()" title="Print Bill">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px">
                <path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg></button>
        <a href="{{ route($panel.'.revenue_expense.record_income') }}" class="btn btn-back" title="Back to Billing">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:8px">
                <path d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg></a>
    </div>

    <!-- Bill Document -->
    <div class="bill-container">
        <!-- Header -->
        <div class="bill-header">
            <div>
                @if(settings('company_name'))
                <div style="font-size:13px;font-weight:600;letter-spacing:0.5px;opacity:0.9;margin-bottom:6px;text-transform:uppercase;">{{ settings('company_name') }}</div>
                @endif
                <h1>{{ __('messages.monthly_bill_caps') }}</h1>
                <p class="subtitle">{{ $monthYear }}</p>
                @if($activePeriod)
                <p class="subtitle">Fiscal Period: {{ $activePeriod->name }}</p>
                @endif
            </div>
            <div class="bill-info">
                <div class="bill-number">BILL-{{ $rental->id }}-{{ now()->format('Ym') }}</div>
                <p>Issue Date: {{ now()->format('M d, Y') }}</p>
                <p>Due Date: {{ $dueDate->format('M d, Y') }}</p>
            </div>
        </div>

        <!-- Tenant & Apartment Info -->
        <div class="info-section">
            <div class="info-block">
                <h3>{{ __('messages.tenant_information') }}</h3>
                <p class="name">{{ $tenant->name ?? 'N/A' }}</p>
                <p>{{ $tenant->phone ?? '' }}</p>
                <p>{{ $tenant->email ?? '' }}</p>
            </div>
            <div class="info-block" style="text-align: right;">
                <h3>{{ __('messages.apartment_details') }}</h3>
                <p class="name">{{ $apartment?->apartment_number ?? 'N/A' }}</p>
                <p>Floor {{ $floor->floor_number ?? 'N/A' }}</p>
                <p>Lease Start: {{ $rental->start_date ? $rental->start_date->format('M d, Y') : 'N/A' }}</p>
            </div>
        </div>

        <!-- Bill Items -->
        <div class="bill-body">
            <h2>{{ __('messages.bill_details') }}</h2>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.description') }}</th>
                        <th>{{ __('messages.category') }}</th>
                        <th>{{ __('messages.amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Rent -->
                    <tr>
                        <td><strong>Monthly Rent — {{ $monthYear }}</strong></td>
                        <td><span class="category-label">{{ __('messages.rent') }}</span></td>
                        <td>{{ money($rent_amount) }}</td>
                    </tr>

                    <!-- Utility Charges -->
                    @foreach($utilities as $utility)
                    <tr>
                        <td>
                            {{ ucfirst(str_replace('_', ' ', $utility->utility_type)) }}
                            @if($utility->meter_reading_in > 0 || $utility->meter_reading_out > 0)
                                <br><span class="category-label">Meter: {{ $utility->meter_reading_in }} → {{ $utility->meter_reading_out }}</span>
                            @endif
                        </td>
                        <td><span class="category-label">{{ __('messages.utility') }}</span></td>
                        <td>{{ money($utility->charge_amount) }}</td>
                    </tr>
                    @endforeach

                    <!-- Apartment Costs -->
                    @foreach($fixedExpenses as $expense)
                    <tr>
                        <td>{{ $expense->expense_name }}</td>
                        <td><span class="category-label">{{ __('messages.apartment_cost') }}</span></td>
                        <td>{{ money($expense->amount) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals-section">
                <div class="total-row">
                    <span>{{ __('messages.subtotal_rent') }}</span>
                    <span>{{ money($rent_amount) }}</span>
                </div>
                @if($totalUtilities > 0)
                <div class="total-row">
                    <span>{{ __('messages.subtotal_utilities') }}</span>
                    <span>{{ money($totalUtilities) }}</span>
                </div>
                @endif
                @if($totalFixed > 0)
                <div class="total-row">
                    <span>{{ __('messages.subtotal_apt_costs') }}</span>
                    <span>{{ money($totalFixed) }}</span>
                </div>
                @endif

                <div class="total-row grand">
                    <span class="label">{{ __('messages.total_bill_caps') }}</span>
                    <span class="amount">{{ money($totalBill) }}</span>
                </div>

                @if($totalPaid > 0)
                <div class="total-row paid" style="margin-top: 12px;">
                    <span>{{ __('messages.amount_paid') }}</span>
                    <span>-{{ money($totalPaid) }}</span>
                </div>
                @endif

                @if($balance > 0)
                <div class="total-row balance">
                    <span>{{ __('messages.balance_due_caps') }}</span>
                    <span>{{ money($balance) }}</span>
                </div>
                @elseif($paidThisMonth)
                <div class="total-row paid-full">
                    ✓ PAID IN FULL
                </div>
                @endif
            </div>

            <!-- Payment History -->
            @if($payments->isNotEmpty())
            <div class="payment-history">
                <h3>Payment History ({{ $monthYear }})</h3>
                @foreach($payments as $payment)
                <div class="payment-item">
                    <span>
                        {{ ucfirst($payment->payment_type) }} — {{ ucfirst($payment->payment_method) }}
                        @if($payment->transaction_reference)
                            (Ref: {{ $payment->transaction_reference }})
                        @endif
                    </span>
                    <span>{{ money($payment->amount + ($payment->late_fee ?? 0)) }} — {{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="bill-footer">
            <div>
                @php
                    $bizContact = array_filter([
                        settings('company_address') ?: null,
                        settings('company_phone') ? __('messages.tel').': '.settings('company_phone') : null,
                        settings('company_email') ?: null,
                    ]);
                @endphp
                @if(settings('company_name') || count($bizContact))
                <p style="font-weight:600;color:#374151;">{{ settings('company_name') }}</p>
                @if(count($bizContact))
                <p>{{ implode('  ·  ', $bizContact) }}</p>
                @endif
                @endif
                <p>Generated on {{ now()->format('M d, Y \a\t h:i A') }}</p>
                <p>{{ __('messages.thank_you_payment') }}</p>
            </div>
            <div style="text-align: right;">
                <span class="status-badge {{ $paidThisMonth ? 'paid' : ($balance > 0 && now()->gt($dueDate) ? 'overdue' : 'unpaid') }}">
                    {{ $paidThisMonth ? 'PAID' : ($balance > 0 && now()->gt($dueDate) ? 'OVERDUE' : 'UNPAID') }}
                </span>
            </div>
        </div>
    </div>
</body>
</html>
