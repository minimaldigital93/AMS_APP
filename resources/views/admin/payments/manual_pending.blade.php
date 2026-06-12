@extends('layouts.admin')

@section('title', __('messages.pending_khqr_payments'))

@section('content')
<div class="mx-auto max-w-5xl" x-data="manualPaymentReview()">
    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.pending_khqr_payments') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('messages.pending_khqr_payments_hint') }}</p>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        {{-- Desktop table --}}
        <div class="hidden md:block">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('messages.transaction') }}</th>
                        <th class="px-4 py-3">{{ __('messages.tenant') }}</th>
                        <th class="px-4 py-3">{{ __('messages.apartment') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('messages.amount') }}</th>
                        <th class="px-4 py-3">{{ __('messages.date') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($pending as $row)
                        <tr id="manual-row-{{ $row->id }}">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row->transaction_id }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $row->rental?->tenant?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row->rental?->apartment?->apartment_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ currency_symbol() }}{{ number_format($row->amount, 2) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $row->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                                <button @click="resolve('{{ route('admin.revenue_expense.khqr_confirm', $row->transaction_id) }}', {{ $row->id }})"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 transition">
                                    {{ __('messages.khqr_mark_received') }}
                                </button>
                                <button @click="resolve('{{ route('admin.revenue_expense.khqr_reject', $row->transaction_id) }}', {{ $row->id }})"
                                    class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-100 transition">
                                    {{ __('messages.khqr_reject') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">{{ __('messages.no_pending_khqr_payments') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-gray-100">
            @forelse ($pending as $row)
                <div class="p-4 space-y-2" id="manual-card-{{ $row->id }}">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-800">{{ $row->rental?->tenant?->name ?? '—' }}</span>
                        <span class="font-semibold text-gray-900">{{ currency_symbol() }}{{ number_format($row->amount, 2) }}</span>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ __('messages.apartment') }} {{ $row->rental?->apartment?->apartment_number ?? '—' }} · {{ $row->created_at->format('d M Y H:i') }}
                    </div>
                    <div class="font-mono text-[11px] text-gray-400">{{ $row->transaction_id }}</div>
                    <div class="flex gap-2 pt-1">
                        <button @click="resolve('{{ route('admin.revenue_expense.khqr_confirm', $row->transaction_id) }}', {{ $row->id }})"
                            class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-700 transition">
                            {{ __('messages.khqr_mark_received') }}
                        </button>
                        <button @click="resolve('{{ route('admin.revenue_expense.khqr_reject', $row->transaction_id) }}', {{ $row->id }})"
                            class="flex-1 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-100 transition">
                            {{ __('messages.khqr_reject') }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-gray-400">{{ __('messages.no_pending_khqr_payments') }}</div>
            @endforelse
        </div>
    </div>

    <div class="mt-4">{{ $pending->links() }}</div>
</div>

<script>
function manualPaymentReview() {
    return {
        async resolve(url, id) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                document.getElementById('manual-row-' + id)?.remove();
                document.getElementById('manual-card-' + id)?.remove();
            } catch (e) {
                alert(e.message || 'Failed.');
            }
        }
    };
}
</script>
@endsection
