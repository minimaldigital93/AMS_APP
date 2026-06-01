@extends('layouts.superadmin')

@section('content')
<h1 class="text-2xl font-bold text-gray-900">{{ __('Customer accounts') }}</h1>
<p class="mt-1 text-sm text-gray-500">{{ __('Every admin account on the platform.') }}</p>

<div class="mt-6 space-y-4">
    @forelse ($accounts as $account)
        @php($sub = $account->subscription)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" x-data="{ open: false }">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="font-semibold text-gray-900">{{ $account->name }}</div>
                    <div class="text-sm text-gray-500">{{ $account->phone }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700">{{ $sub?->plan?->name ?? __('No plan') }}</span>
                    <span class="rounded-full px-3 py-1 font-semibold
                        {{ $account->status === 'suspended' ? 'bg-red-100 text-red-700' : ($sub && $sub->isActive() ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700') }}">
                        {{ $account->status === 'suspended' ? __('Suspended') : ($sub && $sub->isActive() ? __('Active') : __('Inactive')) }}
                    </span>
                    <span class="text-gray-500">{{ __('Floors') }}: {{ $usage[$account->id]['floors'] }} · {{ __('Apts') }}: {{ $usage[$account->id]['apartments'] }}</span>
                    @if ($sub?->expires_at)
                        <span class="text-gray-400">{{ __('Expires') }} {{ $sub->expires_at->format('M j, Y') }}</span>
                    @endif
                    <button @click="open = !open" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">{{ __('Manage') }}</button>
                </div>
            </div>

            <div x-show="open" x-cloak class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                <form method="POST" action="{{ route('superadmin.accounts.plan', $account) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">{{ __('Set plan') }}</label>
                        <select name="plan" class="mt-1 rounded-lg border-gray-300 text-sm">
                            @foreach ($plans as $p)
                                <option value="{{ $p->slug }}" @selected($sub?->plan_id === $p->id)>{{ $p->name }} (${{ rtrim(rtrim(number_format($p->price_usd,2),'0'),'.') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Apply') }}</button>
                </form>

                <form method="POST" action="{{ route('superadmin.accounts.extend', $account) }}">
                    @csrf
                    <button class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-200">{{ __('Extend +1 period') }}</button>
                </form>

                <form method="POST" action="{{ route('superadmin.accounts.suspend', $account) }}">
                    @csrf
                    <button class="rounded-lg px-3 py-2 text-sm font-semibold {{ $account->status === 'suspended' ? 'bg-green-600 text-white hover:bg-green-500' : 'bg-red-600 text-white hover:bg-red-500' }}">
                        {{ $account->status === 'suspended' ? __('Reactivate') : __('Suspend') }}
                    </button>
                </form>
            </div>
        </div>
    @empty
        <p class="text-gray-400">{{ __('No accounts yet.') }}</p>
    @endforelse
</div>

<div class="mt-6">{{ $accounts->links() }}</div>
@endsection
