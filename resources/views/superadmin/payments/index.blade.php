@extends('layouts.superadmin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-900">{{ __('Payments') }}</h1>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Subscription payment transactions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">{{ __('Subscription transactions') }}</h2>
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">{{ __('Transaction') }}</th>
                        <th class="px-5 py-3">{{ __('Account') }}</th>
                        <th class="px-5 py-3">{{ __('Plan') }}</th>
                        <th class="px-5 py-3">{{ __('Amount') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('When') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($payments as $payment)
                        <tr class="align-top">
                            <td class="px-5 py-3 font-mono text-xs text-gray-700">{{ $payment->transaction_id }}</td>
                            <td class="px-5 py-3">{{ $payment->subscription?->account?->name ?? '—' }}</td>
                            <td class="px-5 py-3">{{ $payment->subscription?->plan?->name ?? '—' }}</td>
                            <td class="px-5 py-3">{{ currency_symbol() }}{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-5 py-3">
                                <x-payment-status-badge :status="$payment->status" />
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ ($payment->paid_at ?? $payment->created_at)?->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-right">
                                @if ($payment->isPaid())
                                    <details>
                                        <summary class="cursor-pointer text-red-600 hover:underline text-xs">{{ __('Refund') }}</summary>
                                        @include('superadmin.payments._refund_form', ['payment' => $payment])
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-gray-400">{{ __('No subscription payments yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-gray-50">
            @forelse ($payments as $payment)
                <div class="px-5 py-4 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs text-gray-600">{{ $payment->transaction_id }}</span>
                        <x-payment-status-badge :status="$payment->status" />
                    </div>
                    <div class="text-sm text-gray-800">{{ $payment->subscription?->account?->name ?? '—' }} · {{ $payment->subscription?->plan?->name ?? '—' }}</div>
                    <div class="text-sm font-semibold">{{ currency_symbol() }}{{ number_format($payment->amount, 2) }}</div>
                    @if ($payment->isPaid())
                        <details class="pt-1">
                            <summary class="cursor-pointer text-red-600 text-xs">{{ __('Refund') }}</summary>
                            @include('superadmin.payments._refund_form', ['payment' => $payment])
                        </details>
                    @endif
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-400">{{ __('No subscription payments yet.') }}</div>
            @endforelse
        </div>

        <div class="px-5 py-3">{{ $payments->links() }}</div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        {{-- Recent webhooks --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-800">{{ __('Recent webhooks') }}</h2></div>
            <ul class="divide-y divide-gray-50 text-sm">
                @forelse ($webhooks as $hook)
                    <li class="px-5 py-3 flex items-center justify-between">
                        <span class="font-mono text-xs text-gray-600">{{ $hook->transaction_id ?? $hook->event_id }}</span>
                        <span class="text-xs px-2 py-0.5 rounded {{ $hook->status === 'processed' ? 'bg-green-100 text-green-700' : ($hook->status === 'invalid' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">{{ $hook->status }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-gray-400">{{ __('No webhooks received yet.') }}</li>
                @endforelse
            </ul>
        </div>

        {{-- Recent refunds --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="font-semibold text-gray-800">{{ __('Recent refunds') }}</h2></div>
            <ul class="divide-y divide-gray-50 text-sm">
                @forelse ($recentRefunds as $refund)
                    <li class="px-5 py-3 flex items-center justify-between">
                        <span class="font-mono text-xs text-gray-600">{{ $refund->payment?->transaction_id ?? '—' }}</span>
                        <span class="text-gray-800">{{ currency_symbol() }}{{ number_format($refund->amount, 2) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-gray-400">{{ __('No refunds yet.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
