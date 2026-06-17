{{-- Subscription-expired blocking modal. Rendered app-wide via layouts.admin;
     shows whenever the current admin account has no active subscription. The
     server-side EnsureSubscriptionActive middleware is the real lock — this is
     the prominent "renew to reactivate" prompt + one-tap KHQR repay. --}}
@if (! empty($subscriptionBlocked))
    @php($plan = $subscriptionRenewPlan ?? null)
    <div x-data="{ open: true }" x-show="open" x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center p-4"
         role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/75 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-md rounded-2xl border border-red-100 bg-white p-6 text-center shadow-2xl">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>

            <h2 class="mt-4 text-lg font-bold text-slate-900">{{ __('messages.subscription_blocked_title') }}</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ __('messages.subscription_blocked_msg') }}</p>

            @if ($plan)
                <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm">
                    <span class="font-semibold text-slate-800">{{ $plan->name }}</span>
                    <span class="text-slate-500">— ${{ rtrim(rtrim(number_format($plan->price_usd, 2), '0'), '.') }}/{{ __('mo') }}</span>
                </div>

                <form method="POST" action="{{ route('admin.billing.renew') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="plan" value="{{ $plan->slug }}">
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v3a1 1 0 001 1h3M20 17v-3a1 1 0 00-1-1h-3M4 12h.01M20 12h.01M8 4h8M8 20h8M12 8v8" />
                        </svg>
                        {{ __('messages.subscription_blocked_repay') }}
                    </button>
                </form>

                <button type="button" @click="open = false"
                        class="mt-3 block w-full text-xs text-slate-400 underline transition hover:text-slate-600">
                    {{ __('messages.subscription_blocked_choose_plan') }}
                </button>
            @else
                <a href="{{ route('admin.billing.index') }}"
                   class="mt-5 block w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-700">
                    {{ __('messages.subscription_blocked_choose_plan') }}
                </a>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="text-xs text-slate-400 transition hover:text-slate-600">
                    {{ __('messages.logout') }}
                </button>
            </form>
        </div>
    </div>
@endif
