@extends('layouts.superadmin')

@section('content')
<h1 class="text-2xl font-bold text-gray-900">{{ __('Platform overview') }}</h1>

<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @php($cards = [
        ['Admin accounts', $accountsCount, 'text-gray-900'],
        ['Active subscriptions', $activeSubscriptions, 'text-green-600'],
        ['Pending', $pendingSubscriptions, 'text-yellow-600'],
        ['MRR', '$'.number_format($mrr, 2), 'text-indigo-600'],
    ])
    @foreach ($cards as [$label, $value, $color])
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __($label) }}</div>
            <div class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="text-sm text-gray-500">{{ __('Total platform revenue (paid subscription payments)') }}</div>
    <div class="mt-1 text-2xl font-bold text-gray-900">${{ number_format($platformRevenue, 2) }}</div>
</div>

<h2 class="mt-8 text-lg font-semibold text-gray-900">{{ __('Recent subscriptions') }}</h2>
<div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">{{ __('Account') }}</th>
                <th class="px-4 py-3">{{ __('Plan') }}</th>
                <th class="px-4 py-3">{{ __('Status') }}</th>
                <th class="px-4 py-3">{{ __('Expires') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($recentSubscriptions as $sub)
                <tr>
                    <td class="px-4 py-3">{{ $sub->account?->name ?? '—' }} <span class="text-gray-400">{{ $sub->account?->phone }}</span></td>
                    <td class="px-4 py-3">{{ $sub->plan?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                            {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : ($sub->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ ucfirst($sub->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $sub->expires_at?->format('M j, Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('No subscriptions yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
