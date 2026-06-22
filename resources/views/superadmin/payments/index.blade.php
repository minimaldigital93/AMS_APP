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

        {{-- Realtime search / filter / sort --}}
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-wrap gap-2.5 items-center">
                {{-- Search --}}
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                    </svg>
                    <input id="paymentSearch" type="text" placeholder="{{ __('Search transaction, account or plan') }}"
                        class="w-full h-10 pl-10 pr-4 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                </div>

                {{-- Status filter --}}
                <select id="paymentStatusFilter" class="h-10 w-44 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="paid">{{ __('Paid') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="refunded">{{ __('Refunded') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                    <option value="expired">{{ __('Expired') }}</option>
                    <option value="cancelled">{{ __('Cancelled') }}</option>
                </select>

                {{-- Sort by account --}}
                <button id="paymentSortBtn" type="button" class="inline-flex items-center justify-center gap-1.5 h-10 px-3.5 text-sm font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-800 transition" title="{{ __('Sort') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h12M3 12h8M3 17h4M17 7v10m0 0l-3-3m3 3l3-3" />
                    </svg>
                    <span id="paymentSortLabel">{{ __('Sort') }}</span>
                </button>

                {{-- Clear --}}
                <button id="paymentClearFilters" type="button" class="inline-flex items-center justify-center h-10 w-10 text-gray-400 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition" title="{{ __('Clear') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-3">{{ __('No') }}</th>
                        <th class="px-5 py-3">{{ __('Transaction') }}</th>
                        <th class="px-5 py-3">{{ __('Account') }}</th>
                        <th class="px-5 py-3">{{ __('Plan') }}</th>
                        <th class="px-5 py-3">{{ __('Amount') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('When') }}</th>
                        <th class="px-5 py-3">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody" class="divide-y divide-gray-50">
                    @forelse ($payments as $payment)
                        <tr class="align-top" data-status="{{ $payment->status }}">
                            <td class="px-5 py-3 text-gray-500">{{ $payments->firstItem() + $loop->index }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-gray-700">{{ $payment->transaction_id }}</td>
                            <td class="px-5 py-3">{{ $payment->subscription?->account?->name ?? '—' }}</td>
                            <td class="px-5 py-3">{{ $payment->subscription?->plan?->name ?? '—' }}</td>
                            <td class="px-5 py-3">{{ currency_symbol() }}{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-5 py-3">
                                <x-payment-status-badge :status="$payment->status" />
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ ($payment->paid_at ?? $payment->created_at)?->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                @include('superadmin.payments._actions', ['payment' => $payment])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">{{ __('No subscription payments yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div id="paymentsCardList" class="md:hidden divide-y divide-gray-50">
            @forelse ($payments as $payment)
                <div class="px-5 py-4 space-y-1" data-status="{{ $payment->status }}"
                     data-search="{{ \Illuminate\Support\Str::lower($payment->transaction_id.' '.($payment->subscription?->account?->name ?? '').' '.($payment->subscription?->plan?->name ?? '')) }}">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs text-gray-600">{{ $payment->transaction_id }}</span>
                        <x-payment-status-badge :status="$payment->status" />
                    </div>
                    <div class="text-sm text-gray-800">{{ $payment->subscription?->account?->name ?? '—' }} · {{ $payment->subscription?->plan?->name ?? '—' }}</div>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-sm font-semibold">{{ currency_symbol() }}{{ number_format($payment->amount, 2) }}</span>
                        @include('superadmin.payments._actions', ['payment' => $payment])
                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('paymentSearch');
    const statusFilter = document.getElementById('paymentStatusFilter');
    const clearBtn = document.getElementById('paymentClearFilters');
    const sortBtn = document.getElementById('paymentSortBtn');
    const sortLabel = document.getElementById('paymentSortLabel');
    const tbody = document.getElementById('paymentsTableBody');
    const cardList = document.getElementById('paymentsCardList');
    let sortAsc = true;

    function normalize(text){ return (text||'').toString().trim().toLowerCase(); }

    function matches(haystack, status, q, sf){
        const okQuery = q === '' || haystack.includes(q);
        const okStatus = sf === '' || status === sf;
        return okQuery && okStatus;
    }

    function filterList() {
        const q = normalize(searchInput.value);
        const sf = normalize(statusFilter.value);

        if (tbody) {
            Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                if (row.dataset.status === undefined) return; // empty-state row
                const text = normalize(row.children[1].innerText + ' ' + row.children[2].innerText + ' ' + row.children[3].innerText);
                row.style.display = matches(text, normalize(row.dataset.status), q, sf) ? '' : 'none';
            });
        }
        if (cardList) {
            Array.from(cardList.querySelectorAll('[data-status]')).forEach(card => {
                card.style.display = matches(normalize(card.dataset.search), normalize(card.dataset.status), q, sf) ? '' : 'none';
            });
        }
    }

    function sortByAccount() {
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => r.dataset.status !== undefined);
        rows.sort((a, b) => normalize(a.children[2].innerText)
            .localeCompare(normalize(b.children[2].innerText)) * (sortAsc ? 1 : -1));
        rows.forEach(r => tbody.appendChild(r));
        sortAsc = !sortAsc;
        if (sortLabel) sortLabel.textContent = sortAsc ? 'Sort ▲' : 'Sort ▼';
    }

    if (searchInput) searchInput.addEventListener('input', filterList);
    if (statusFilter) statusFilter.addEventListener('change', filterList);
    if (clearBtn) clearBtn.addEventListener('click', function(){ searchInput.value=''; statusFilter.value=''; filterList(); });
    if (sortBtn) sortBtn.addEventListener('click', sortByAccount);
    if (sortLabel) sortLabel.textContent = 'Sort ▲';
});
</script>
@endsection
