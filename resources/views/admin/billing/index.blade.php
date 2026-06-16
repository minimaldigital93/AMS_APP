@extends('layouts.admin')

@section('title', __('Billing & Subscription'))

@section('content')
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('Billing & Subscription') }}</h1>

    @if (session('error'))
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @php($plan = $usage['plan'])
    @php($sub = $subscription)

    <!-- Current plan -->
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="text-sm text-gray-500">{{ __('Current plan') }}</div>
                <div class="text-xl font-bold text-gray-900">{{ $plan?->name ?? __('No active plan') }}</div>
            </div>
            <div class="text-right">
                @if ($sub)
                    @php($active = $sub->isActive())
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        {{ ucfirst($sub->status) }}{{ $active && $sub->expires_at ? ' · '.__('renews').' '.$sub->expires_at->format('M j, Y') : '' }}
                    </span>
                @else
                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ __('None') }}</span>
                @endif
            </div>
        </div>

        <!-- Usage -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @php($floorsMax = $usage['floors_max'])
            @php($aptsMax = $usage['apartments_max'])
            <div class="rounded-xl bg-gray-50 p-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ __('Floors') }}</span>
                    <span class="font-semibold text-gray-900">{{ $usage['floors_used'] }} / {{ $floorsMax ?? '∞' }}</span>
                </div>
                @if ($floorsMax)
                    <div class="mt-2 h-2 w-full rounded-full bg-gray-200">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ min(100, $floorsMax ? round($usage['floors_used'] / $floorsMax * 100) : 0) }}%"></div>
                    </div>
                @endif
            </div>
            <div class="rounded-xl bg-gray-50 p-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ __('Apartments') }}</span>
                    <span class="font-semibold text-gray-900">{{ $usage['apartments_used'] }} / {{ $aptsMax ?? '∞' }}</span>
                </div>
                @if ($aptsMax)
                    <div class="mt-2 h-2 w-full rounded-full bg-gray-200">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ min(100, $aptsMax ? round($usage['apartments_used'] / $aptsMax * 100) : 0) }}%"></div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Plans / renew -->
    <h2 class="mt-8 text-lg font-semibold text-gray-900">{{ __('Renew or change plan') }}</h2>
    <div class="mt-4 grid gap-5 sm:grid-cols-3">
        @foreach ($plans as $p)
            @php($current = $plan && $plan->id === $p->id)
            <div class="flex flex-col rounded-2xl border p-6 {{ $current ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-200' }} bg-white shadow-sm">
                <h3 class="text-base font-semibold text-gray-900">{{ $p->name }}</h3>
                <p class="mt-2"><span class="text-3xl font-extrabold text-gray-900">${{ rtrim(rtrim(number_format($p->price_usd, 2), '0'), '.') }}</span><span class="text-sm text-gray-500">/{{ __('mo') }}</span></p>
                <ul class="mt-4 space-y-2 text-sm text-gray-600">
                    <li>{{ $p->max_floors === null ? __('Unlimited floors') : $p->max_floors.' '.__('floors') }}</li>
                    <li>{{ $p->max_apartments === null ? __('Unlimited apartments') : $p->max_apartments.' '.__('apartments') }}</li>
                </ul>
                <form method="POST" action="{{ route('admin.billing.renew') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="plan" value="{{ $p->slug }}">
                    <button type="submit" class="w-full rounded-xl px-4 py-2.5 text-sm font-semibold {{ $current ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-900 text-white hover:bg-gray-700' }}">
                        {{ $current ? __('Renew via KHQR') : __('Switch via KHQR') }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
