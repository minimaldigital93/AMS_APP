{{-- Row actions for a platform subscription payment: view details + record refund.
     Each instance owns its own Alpine modal state; modals are teleported to <body>
     so they overlay correctly regardless of the table/card they live in. --}}
<div class="flex items-center gap-1" x-data="{ showView: false, showRefund: false }">
    {{-- View details --}}
    <button type="button" @click="showView = true" title="{{ __('View') }}"
            class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-slate-500 hover:bg-slate-100 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
    </button>

    {{-- Record refund (paid transactions only) --}}
    @if ($payment->isPaid())
        <button type="button" @click="showRefund = true" title="{{ __('Refund') }}"
                class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-red-600 hover:bg-red-50 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 2 2 2-2 2 2 4-2z" />
            </svg>
        </button>
    @endif

    {{-- View details modal --}}
    <template x-teleport="body">
        <div x-show="showView" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="showView = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl border border-gray-100 w-full max-w-md"
                 @keydown.escape.window="showView = false">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">{{ __('Payment details') }}</h3>
                    <button type="button" @click="showView = false" class="text-gray-400 hover:text-gray-600" title="{{ __('Close') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <dl class="px-5 py-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Transaction') }}</dt>
                        <dd class="font-mono text-xs text-gray-800 text-right break-all">{{ $payment->transaction_id }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Account') }}</dt>
                        <dd class="text-gray-800 text-right">{{ $payment->subscription?->account?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Plan') }}</dt>
                        <dd class="text-gray-800 text-right">{{ $payment->subscription?->plan?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Amount') }}</dt>
                        <dd class="font-semibold text-gray-900 text-right">{{ currency_symbol() }}{{ number_format($payment->amount, 2) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Status') }}</dt>
                        <dd class="text-right"><x-payment-status-badge :status="$payment->status" /></dd>
                    </div>
                    @if ($payment->channel)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('Channel') }}</dt>
                            <dd class="text-gray-800 text-right">{{ str_replace('_', ' ', $payment->channel) }}</dd>
                        </div>
                    @endif
                    @if ($payment->provider_ref)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ __('Provider ref') }}</dt>
                            <dd class="font-mono text-xs text-gray-800 text-right break-all">{{ $payment->provider_ref }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Paid at') }}</dt>
                        <dd class="text-gray-800 text-right">{{ $payment->paid_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ __('Created') }}</dt>
                        <dd class="text-gray-800 text-right">{{ $payment->created_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                    </div>
                </dl>
                @if ($payment->isPaid())
                    <div class="px-5 py-4 border-t border-gray-100 text-right">
                        <button type="button" @click="showView = false; showRefund = true"
                                class="text-sm font-medium text-red-600 hover:underline">{{ __('Refund') }}</button>
                    </div>
                @endif
            </div>
        </div>
    </template>

    {{-- Refund modal --}}
    @if ($payment->isPaid())
        <template x-teleport="body">
            <div x-show="showRefund" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showRefund = false"></div>
                <div class="relative bg-white rounded-xl shadow-xl border border-gray-100 w-full max-w-sm"
                     @keydown.escape.window="showRefund = false">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">{{ __('Record refund') }}</h3>
                        <button type="button" @click="showRefund = false" class="text-gray-400 hover:text-gray-600" title="{{ __('Close') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-xs text-gray-500 mb-3">{{ __('Transaction') }}: <span class="font-mono">{{ $payment->transaction_id }}</span></p>
                        @include('superadmin.payments._refund_form', ['payment' => $payment])
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>
