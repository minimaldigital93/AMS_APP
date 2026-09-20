@props([
    // The array from BakongUsageReport::build(). Everything in it is read from
    // the local ledger, the cache and the token's own JWT — rendering this
    // component contacts nobody and costs no Bakong request.
    'report',
    // Where to re-fetch the same report as JSON. Null renders a static panel.
    'refreshUrl' => null,
])

@php
    // The bar's colour is the DAY's verdict, not merely its arithmetic:
    // 'exhausted' is also what an upstream refusal sets, at any spend.
    $tone = match ($report['state']) {
        'exhausted' => ['bar' => 'bg-red-500',    'text' => 'text-red-700',    'ring' => 'border-red-200 bg-red-50'],
        'critical'  => ['bar' => 'bg-orange-500', 'text' => 'text-orange-700', 'ring' => 'border-orange-200 bg-orange-50'],
        'warn'      => ['bar' => 'bg-amber-400',  'text' => 'text-amber-700',  'ring' => 'border-amber-200 bg-amber-50'],
        'unbounded' => ['bar' => 'bg-slate-400',  'text' => 'text-slate-600',  'ring' => 'border-slate-200 bg-slate-50'],
        default     => ['bar' => 'bg-emerald-500','text' => 'text-emerald-700','ring' => 'border-emerald-200 bg-emerald-50'],
    };

    $token = $report['token'];
    $daysLeft = $token['days_left'];
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-5"
     @if ($refreshUrl)
        x-data="bakongUsageMeter(@js($refreshUrl), @js($report))"
        x-init="start()"
     @endif>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.bakong_usage_title') }}</h2>
            <p class="text-sm text-gray-500">{{ __('messages.bakong_usage_subtitle') }}</p>
        </div>
        <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium
            {{ $report['enabled'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-100 text-gray-500' }}">
            {{ $report['enabled'] ? __('messages.active') : __('messages.off') }}
        </span>
    </div>

    {{-- ─────────────────────────── THE BAR ─────────────────────────── --}}
    <div>
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm text-gray-600">{{ __('messages.bakong_usage_spent_today') }}</p>
            <p class="text-sm font-semibold tabular-nums {{ $tone['text'] }}">
                <span @if ($refreshUrl) x-text="report.spent" @endif>{{ $report['spent'] }}</span>
                @if ($report['limit'] > 0)
                    <span class="font-normal text-gray-400">/ {{ $report['limit'] }}</span>
                @endif
            </p>
        </div>

        <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-gray-100"
             role="progressbar"
             aria-valuenow="{{ $report['percent'] }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('messages.bakong_usage_spent_today') }}">
            <div class="h-full rounded-full transition-all duration-500 {{ $tone['bar'] }}"
                 style="width: {{ max($report['percent'], $report['spent'] > 0 ? 2 : 0) }}%"
                 @if ($refreshUrl) x-bind:style="`width: ${Math.max(report.percent, report.spent > 0 ? 2 : 0)}%`" @endif></div>
        </div>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-gray-500">
            <span>{{ __('messages.bakong_usage_resets_in', ['time' => $report['resets_in']]) }}</span>
            @if ($report['checkouts_left'] !== null && $report['state'] !== 'exhausted')
                {{-- The question behind the question. "74 requests left" means
                     nothing to someone deciding whether today's signups will go
                     through; this is the same number in the unit they think in.

                     Hidden once the day is over, even when our own ceiling has
                     room: with an upstream refusal latched there is no room,
                     and a cheerful "5 more checkouts" printed under a red
                     "Bakong says the day's limit is reached" is the page
                     contradicting itself at the worst possible moment. --}}
                <span class="font-medium {{ $tone['text'] }}">
                    {{ trans_choice('messages.bakong_usage_checkouts_left', $report['checkouts_left'], [
                        'count' => $report['checkouts_left'],
                        'each' => $report['calls_per_checkout'],
                    ]) }}
                </span>
            @endif
        </div>
    </div>

    {{-- ──────────────── NBC SAID THE DAY IS OVER ────────────────
         Reported ABOVE the bar's arithmetic and never folded into it, because
         the two are different facts. NBC meters the TOKEN: anything else
         holding the same credential spends from the same allowance invisibly,
         so this can arrive while our own bar reads 6 of 80 — which is exactly
         what happened on this installation while khqr.cc shared the token. --}}
    @if ($report['upstream_exhausted'])
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm">
            <p class="font-semibold text-red-800">{{ __('messages.bakong_usage_upstream_title') }}</p>
            <p class="mt-1 text-xs leading-relaxed text-red-700">
                {{ __('messages.bakong_usage_upstream_body', ['time' => $report['upstream_exhausted']['until_human']]) }}
            </p>
            @if ($report['upstream_exhausted']['why'])
                <p class="mt-1 text-xs italic text-red-600">“{{ $report['upstream_exhausted']['why'] }}”</p>
            @endif
        </div>
    @endif

    @if ($report['backoff'])
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
            <p class="font-semibold text-amber-800">{{ __('messages.bakong_usage_backoff_title', ['time' => $report['backoff']['until_human']]) }}</p>
            @if ($report['backoff']['why'])
                <p class="mt-1 text-xs italic text-amber-700">“{{ $report['backoff']['why'] }}”</p>
            @endif
        </div>
    @endif

    {{-- ─────────────────────── THE TOKEN ITSELF ───────────────────────
         A different clock from the allowance: the allowance resets tonight, the
         token expires in about ninety days and is renewed by the scheduler.
         Both belong on this page because both stop payments dead. --}}
    <div class="rounded-xl border px-4 py-3 text-sm
        {{ $token['usable'] ? 'border-gray-200 bg-gray-50' : 'border-red-200 bg-red-50' }}">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="font-medium {{ $token['usable'] ? 'text-gray-700' : 'text-red-800' }}">{{ __('messages.bakong_usage_token') }}</p>
            @if ($token['usable'] && $daysLeft !== null)
                <p class="text-xs {{ $daysLeft <= 7 ? 'font-semibold text-amber-700' : 'text-gray-500' }}">
                    {{ trans_choice('messages.bakong_usage_token_days', $daysLeft, ['count' => $daysLeft]) }}
                </p>
            @endif
        </div>
        <p class="mt-1 text-xs {{ $token['usable'] ? 'text-gray-500' : 'text-red-700' }}">
            @if ($token['usable'])
                {{ __('messages.bakong_usage_token_valid', ['date' => $token['expires_at_human'] ?? '—', 'fingerprint' => $token['fingerprint']]) }}
            @elseif ($token['registered'])
                {{ __('messages.bakong_usage_token_unusable') }}
            @else
                {{ __('messages.bakong_usage_token_missing') }}
            @endif
        </p>
        @unless ($token['usable'])
            {{-- `import` for a first token, never `request`: /v1/request_token
                 is in NBC's v1.0.2 document but 404s on the live API, and the
                 attempt is metered before the 404 comes back. NBC issues the
                 first token from its web portal and emails it; import is
                 offline and costs nothing. Pointing at `request` here sent the
                 operator to spend a request on a dead endpoint at the exact
                 moment the page was telling them something was wrong. --}}
            <p class="mt-1 text-xs text-red-700">
                {{ __('messages.bakong_usage_token_remedy') }}
                <code class="rounded bg-white/70 px-1.5 py-0.5">php artisan bakong:token {{ $token['registered'] ? 'renew' : 'import' }}</code>
            </p>
            @unless ($token['registered'])
                <p class="mt-1 text-xs text-red-700">
                    {{ __('messages.bakong_usage_token_portal') }}
                    <a href="{{ rtrim(config('bakong.base_url'), '/') }}/register" target="_blank" rel="noopener noreferrer"
                       class="underline">{{ rtrim(config('bakong.base_url'), '/') }}/register</a>
                </p>
            @endunless
        @endunless
    </div>

    {{-- ─────────────────────── WHERE THE DAY WENT ───────────────────────
         A running total says the allowance is going; only a breakdown says that
         sixty of them were one abandoned checkout, or that something nobody
         remembered is running on a schedule. That is the first question of any
         quota investigation, and the one a bar cannot answer. --}}
    @if ($report['by_reason'] || $report['blocked_by_gate'])
        <details class="group">
            <summary class="cursor-pointer list-none text-xs font-medium text-indigo-600 hover:text-indigo-700">
                {{ __('messages.bakong_usage_breakdown') }}
                <span class="inline-block transition group-open:rotate-90">›</span>
            </summary>

            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('messages.bakong_usage_made') }}</p>
                    <ul class="mt-1.5 space-y-1 text-xs text-gray-600">
                        @forelse ($report['by_reason'] as $reason => $count)
                            <li class="flex justify-between gap-3">
                                <span class="truncate">{{ str_replace('_', ' ', $reason) }}</span>
                                <span class="font-semibold tabular-nums">{{ $count }}</span>
                            </li>
                        @empty
                            <li class="text-gray-400">{{ __('messages.bakong_usage_none') }}</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    {{-- Refusals are shown beside the spend because they are how
                         the guards report for duty: a large verify_cooldown
                         count is the throttle working, a large
                         daily_budget_exhausted count is the day already lost. --}}
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('messages.bakong_usage_refused') }}</p>
                    <ul class="mt-1.5 space-y-1 text-xs text-gray-600">
                        @forelse ($report['blocked_by_gate'] as $gate => $count)
                            <li class="flex justify-between gap-3">
                                <span class="truncate">{{ str_replace('_', ' ', $gate) }}</span>
                                <span class="font-semibold tabular-nums">{{ $count }}</span>
                            </li>
                        @empty
                            <li class="text-gray-400">{{ __('messages.bakong_usage_none') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </details>
    @endif

    {{-- ───────────────────────── THE LAST WEEK ─────────────────────────
         One day's number cannot tell a busy Tuesday from a leak. A week of
         them can — a flat line near the ceiling with nobody signing up is the
         shape the khqr.cc drain made. --}}
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('messages.bakong_usage_last_days') }}</p>
        <div class="mt-2 flex items-end gap-1.5">
            @foreach ($report['history'] as $day)
                <div class="flex flex-1 flex-col items-center gap-1"
                     title="{{ $day['date'] }} — {{ $day['spent'] }}">
                    <span class="text-[10px] tabular-nums text-gray-400">{{ $day['spent'] ?: '·' }}</span>
                    {{-- The bar track gets its OWN fixed height. Sharing one
                         height with the two labels left the bars a few pixels
                         to differ in, so a 19 and a 47 drew the same. --}}
                    <div class="flex w-full items-end rounded bg-gray-100" style="height: 44px;">
                        <div class="w-full rounded transition-all
                            {{ $day['percent'] >= 85 ? 'bg-red-400' : ($day['percent'] >= 60 ? 'bg-amber-300' : 'bg-indigo-300') }}"
                             {{-- A floor of 6% so a day with one call is visibly
                                  different from a day with none, which draws
                                  nothing at all. --}}
                             style="height: {{ $day['spent'] > 0 ? max(6, $day['percent']) : 0 }}%"></div>
                    </div>
                    <span class="text-[10px] text-gray-400">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <p class="text-[11px] leading-relaxed text-gray-400">
        {{ __('messages.bakong_usage_footnote', ['upstream' => $report['upstream_limit']]) }}
    </p>
</div>

@if ($refreshUrl)
    {{-- Inline rather than @push: the superadmin layout has no script stack,
         and @once already guarantees one copy per render. --}}
    @once
        <script>
            function bakongUsageMeter(url, initial) {
                return {
                    report: initial,
                    timer: null,
                    start() {
                        // 30s is fine BECAUSE THIS COSTS NOTHING: the endpoint
                        // reads the local ledger and the cache. Under KHQRPay
                        // the equivalent panel had to be click-to-run, since
                        // every refresh was two metered requests.
                        this.timer = setInterval(() => this.refresh(), 30000);
                        document.addEventListener('visibilitychange', () => {
                            if (!document.hidden) this.refresh();
                        });
                    },
                    async refresh() {
                        if (document.hidden) return;
                        try {
                            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                            if (!res.ok) return;
                            this.report = await res.json();
                        } catch (e) { /* leave the last good figures on screen */ }
                    },
                };
            }
        </script>
    @endonce
@endif
