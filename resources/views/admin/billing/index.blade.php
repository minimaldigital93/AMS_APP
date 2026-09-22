@extends('layouts.admin')

@section('title', __('messages.billing_subscription'))

@section('content')
<div class="mx-auto max-w-4xl">
    {{-- Reached from System Settings, so it carries that page's back arrow —
         the same header shape settings/section.blade.php uses.

         The arrow is hidden while the subscription is NOT active, on purpose:
         EnsureSubscriptionActive bounces every settings route back to this
         page, so offering "back to Settings" to a locked-out admin is a link
         that returns them to where they stand. Renewing is the way out, and
         the red banner below is the only thing that should be competing for
         their attention. --}}
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.billing_subscription') }}</h1>
        @if ($subscription && $subscription->isActive())
            <a href="{{ route('admin.settings.index') }}" class="ml-auto flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-600 transition" title="{{ __('messages.back') }}" aria-label="{{ __('messages.back') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </a>
        @endif
    </div>

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
            <div class="flex flex-wrap items-baseline gap-3">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Renew or change plan') }}</h2>
            </div>
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
                @php($fault = $planFaults[$p->id] ?? null)
                @php($blocked = $fault !== null)
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
                        {{-- The account has already outgrown this plan. Say so here
                             rather than letting the switch reach a QR the customer
                             would pay before finding out: finalizeSubscription()
                             applies the purchased plan the moment the money lands,
                             and the caps come straight off it. The wording is the
                             service's own, so this line and the flash from a stale
                             tab's POST read identically. --}}
                        @if ($blocked)
                            <p class="mt-2 flex items-start gap-1.5 text-xs leading-relaxed text-red-600">
                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span>{{ $fault }}</span>
                            </p>
                        @endif
                    </div>
                    <div class="flex items-center gap-4 sm:justify-end">
                        <div class="text-right">
                            <x-plan-price :plan="$p" />
                        </div>
                        @if ($blocked)
                            <button type="button" disabled title="{{ __('messages.plan_too_small_hint') }}"
                                    class="cursor-not-allowed rounded-xl bg-gray-200 px-4 py-2.5 text-sm font-semibold whitespace-nowrap text-gray-400">
                                {{ __('messages.switch_via_khqr') }}
                            </button>
                        @else
                            {{-- A switch is confirmed before it is minted; a renewal
                                 is not, because nothing about the account changes.
                                 data-confirm is bound rather than static so the price
                                 quoted matches the monthly/yearly toggle above. --}}
                            <form method="POST" action="{{ route('admin.billing.renew') }}"
                                  @if (! $current)
                                      data-confirm-title="{{ __('messages.plan_switch_confirm_title') }}"
                                      data-confirm-ok="{{ __('messages.plan_switch_confirm_ok') }}"
                                      :data-confirm="cycle === 'yearly'
                                          ? @js(__('messages.plan_switch_confirm', ['plan' => $p->name, 'amount' => '$'.number_format($p->priceFor('yearly'), 2)]))
                                          : @js(__('messages.plan_switch_confirm', ['plan' => $p->name, 'amount' => '$'.number_format($p->priceFor('monthly'), 2)]))"
                                  @endif>
                                @csrf
                                <input type="hidden" name="plan" value="{{ $p->slug }}">
                                <input type="hidden" name="billing_cycle" x-model="cycle">
                                <button type="submit" class="rounded-xl px-4 py-2.5 text-sm font-semibold whitespace-nowrap {{ $current ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-900 text-white hover:bg-gray-700' }}">
                                    {{ $current ? __('messages.renew_via_khqr') : __('messages.switch_via_khqr') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
