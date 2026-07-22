@extends('layouts.admin')

@section('title', __('Billing & Subscription'))

@section('content')
<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('Billing & Subscription') }}</h1>

    {{-- Persistent "you are locked out" banner while the account has no active
         subscription. Unlike the auto-dismissing flash, this stays put until the
         admin renews (see EnsureSubscriptionActive). --}}
    @if (! ($subscription && $subscription->isActive()))
        <div class="mt-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div>
                <p class="font-semibold text-red-800">{{ __('messages.subscription_blocked_title') }}</p>
                <p class="mt-0.5 leading-relaxed">{{ __('messages.subscription_blocked_banner') }}</p>
            </div>
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
                        {{ status_label($sub->status) }}{{ $active && $sub->expires_at ? ' · '.__('renews').' '.$sub->expires_at->format('M j, Y') : '' }}
                    </span>
                @else
                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">{{ __('None') }}</span>
                @endif
            </div>
        </div>

        <!-- Usage -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php($cards = [
                ['label' => __('messages.properties'), 'used' => $usage['properties_used'], 'max' => $usage['properties_max']],
                ['label' => __('messages.rooms'), 'used' => $usage['rooms_used'], 'max' => $usage['rooms_max']],
                ['label' => __('messages.staff'), 'used' => $usage['staff_used'], 'max' => $usage['staff_max']],
                ['label' => __('messages.floors'), 'used' => $usage['floors_used'], 'max' => null],
            ])
            @foreach ($cards as $card)
                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">{{ $card['label'] }}</span>
                        <span class="font-semibold text-gray-900">{{ $card['used'] }} / {{ $card['max'] ?? '∞' }}</span>
                    </div>
                    @if ($card['max'])
                        <div class="mt-2 h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full bg-indigo-500" style="width: {{ min(100, round($card['used'] / max($card['max'], 1) * 100)) }}%"></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Plans / renew -->
    <div x-data="{ cycle: 'monthly' }">
        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Renew or change plan') }}</h2>
            <div class="inline-flex rounded-full bg-gray-100 p-1 text-sm font-medium">
                <button type="button" @click="cycle = 'monthly'" :class="cycle === 'monthly' ? 'bg-white text-gray-900 shadow' : 'text-gray-500'" class="rounded-full px-4 py-1.5">{{ __('messages.monthly') }}</button>
                <button type="button" @click="cycle = 'yearly'" :class="cycle === 'yearly' ? 'bg-white text-gray-900 shadow' : 'text-gray-500'" class="rounded-full px-4 py-1.5">{{ __('messages.yearly') }}</button>
            </div>
        </div>
        {{-- List view: one row per plan so the layout stays readable regardless
             of how many plans the superadmin has created. --}}
        <div class="mt-4 divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white shadow-sm">
            @foreach ($plans as $p)
                @php($current = $plan && $plan->id === $p->id)
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between {{ $current ? 'bg-indigo-50/50' : '' }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900">{{ $p->name }}</h3>
                            @if ($current)
                                <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">{{ __('Current plan') }}</span>
                            @endif
                        </div>
                        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                            <li>{{ $p->max_properties === null ? __('messages.unlimited_properties') : $p->max_properties.' '.__('messages.properties') }}</li>
                            <li>{{ $p->max_rooms === null ? __('messages.unlimited_rooms') : $p->max_rooms.' '.__('messages.rooms') }}</li>
                            <li>{{ __('messages.unlimited_floors') }}</li>
                            <li>{{ $p->max_staff === null ? __('messages.unlimited_staff') : $p->max_staff.' '.__('messages.staff') }}</li>
                        </ul>
                    </div>
                    <div class="flex items-center gap-4 sm:justify-end">
                        <div class="text-right">
                            <span class="text-2xl font-extrabold text-gray-900" x-show="cycle === 'monthly'">${{ rtrim(rtrim(number_format($p->price_usd, 2), '0'), '.') }}</span>
                            <span class="text-sm text-gray-500" x-show="cycle === 'monthly'">/{{ __('mo') }}</span>
                            <span class="text-2xl font-extrabold text-gray-900" x-show="cycle === 'yearly'" x-cloak>${{ rtrim(rtrim(number_format($p->hasYearly() ? $p->price_yearly_usd : $p->price_usd, 2), '0'), '.') }}</span>
                            <span class="text-sm text-gray-500" x-show="cycle === 'yearly'" x-cloak>/{{ __('messages.year') }}</span>
                        </div>
                        <form method="POST" action="{{ route('admin.billing.renew') }}">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $p->slug }}">
                            <input type="hidden" name="billing_cycle" x-model="cycle">
                            <button type="submit" class="rounded-xl px-4 py-2.5 text-sm font-semibold whitespace-nowrap {{ $current ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-900 text-white hover:bg-gray-700' }}">
                                {{ $current ? __('Renew via KHQR') : __('Switch via KHQR') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
