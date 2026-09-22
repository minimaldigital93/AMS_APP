@props([
    'plan',
    // Each call site keeps its own typography — the three pricing surfaces
    // (login grid, signup card, billing grid) look deliberately different.
    'amountClass' => 'text-2xl font-extrabold text-gray-900',
    'suffixClass' => 'text-sm text-gray-500',
])
{{-- A plan's price under the monthly/yearly toggle.

     Renders inside the page's own `x-data="{ cycle: … }"` scope, so it is a
     drop-in for the spans each of these pages used to inline.

     THE POINT OF IT: a plan with no price_yearly_usd is billed MONTHLY
     whatever the toggle says — BillingController::renew() coerces the cycle
     (`… === 'yearly' && $plan->hasYearly()`) and Plan::priceFor() falls back
     to the monthly figure. The money was always right; it was the LABEL that
     lied, printing the monthly price under "/yr" so a $12 plan read "$12/year"
     and charged $12 for thirty days. Print what will actually be charged, and
     say why the toggle appears to do nothing. Three pages had their own copy
     of this line — hence one component, not three fixes. --}}
@php($monthly = '$'.rtrim(rtrim(number_format($plan->price_usd, 2), '0'), '.'))
@php($yearly = $plan->hasYearly() ? '$'.rtrim(rtrim(number_format($plan->price_yearly_usd, 2), '0'), '.') : null)
<span class="{{ $amountClass }}" x-show="cycle === 'monthly'">{{ $monthly }}</span>
<span class="{{ $suffixClass }}" x-show="cycle === 'monthly'">/{{ __('mo') }}</span>
<span class="{{ $amountClass }}" x-show="cycle === 'yearly'" x-cloak>{{ $yearly ?? $monthly }}</span>
<span class="{{ $suffixClass }}" x-show="cycle === 'yearly'" x-cloak>/{{ $yearly ? __('messages.year') : __('mo') }}</span>
@unless ($plan->hasYearly())
    <span class="block {{ $suffixClass }}" x-show="cycle === 'yearly'" x-cloak>{{ __('messages.plan_monthly_only') }}</span>
@endunless
