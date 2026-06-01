@extends('layouts.superadmin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('Subscriptions') }}</h1>
    <div class="flex gap-2 text-sm">
        @foreach (['' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $value => $label)
            <a href="{{ route('superadmin.subscriptions.index', array_filter(['status' => $value])) }}"
               class="rounded-full px-3 py-1 font-medium {{ ($status ?? '') === $value ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ __($label) }}
            </a>
        @endforeach
    </div>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">{{ __('Account') }}</th>
                <th class="px-4 py-3">{{ __('Plan') }}</th>
                <th class="px-4 py-3">{{ __('Status') }}</th>
                <th class="px-4 py-3">{{ __('Started') }}</th>
                <th class="px-4 py-3">{{ __('Expires') }}</th>
                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($subscriptions as $sub)
                <tr>
                    <td class="px-4 py-3">{{ $sub->account?->name ?? '—' }}<div class="text-xs text-gray-400">{{ $sub->account?->phone }}</div></td>
                    <td class="px-4 py-3">{{ $sub->plan?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                            {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : ($sub->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ ucfirst($sub->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $sub->started_at?->format('M j, Y') ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $sub->expires_at?->format('M j, Y') ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if ($sub->status !== 'active')
                                <form method="POST" action="{{ route('superadmin.subscriptions.activate', $sub) }}">
                                    @csrf
                                    <button class="rounded-lg bg-green-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-500">{{ __('Activate') }}</button>
                                </form>
                            @endif
                            @if ($sub->status !== 'cancelled')
                                <form method="POST" action="{{ route('superadmin.subscriptions.cancel', $sub) }}">
                                    @csrf
                                    <button class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500">{{ __('Cancel') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('No subscriptions found.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">{{ $subscriptions->links() }}</div>
@endsection
