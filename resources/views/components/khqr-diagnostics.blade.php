{{-- "Why couldn't we start the payment?" popup.

     Subscribing and renewing both hand the browser to khqr.cc with
     redirect()->away(), which is a one-way door: a profile that can't transact
     answers with a raw JSON body and the customer ends up reading
     {"responseCode":1,…} on someone else's domain. The preflight now refuses
     that handoff — this popup is what says WHY, on our own page.

     Two audiences, one component:
       - $endpoint set (admin) → runs the live gateway checks and quotes the
         gateway verbatim. That is the fix-it view.
       - $endpoint null (public signup) → plain language, no probe. The detail
         names the profile id and the gateway's internals; a visitor must never
         see it, and an unauthenticated probe endpoint would be a free way to
         spend a metered Bakong token.

     Opens automatically when a checkout was just refused ($autoOpen), and from
     any element with x-on:click="$dispatch('khqr-diagnostics')" anywhere on the
     page — that dispatcher is how the checkout pages' "the payment page showed
     an error" button reaches it. --}}
@props([
    'endpoint' => null,
    'reason' => null,
    'autoOpen' => false,
    'retryUrl' => null,
    'settingsUrl' => null,
])

<div x-data="khqrDiagnostics({
        endpoint: @js($endpoint),
        autoOpen: @js((bool) $autoOpen),
     })"
     x-on:khqr-diagnostics.window="open()"
     x-init="init()">

    <div x-show="shown" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="khqr-diag-title">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" x-on:click="close()"></div>

        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5">
            <div class="flex items-start gap-4 border-b border-slate-100 p-6">
                <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-100">
                    <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="flex-1 pt-0.5">
                    <h3 id="khqr-diag-title" class="text-base font-semibold text-slate-900">
                        {{ __('messages.khqr_diag_title') }}
                    </h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">
                        {{ $reason ?: __('messages.khqr_diag_intro') }}
                    </p>
                </div>
            </div>

            <div class="max-h-[55vh] overflow-y-auto p-6">
                @if ($endpoint)
                    {{-- The last refusal the preflight actually recorded. Shown
                         above the fresh run because the gateway is allowed to
                         answer differently a minute later, and an all-green
                         report in front of someone staring at the failure is
                         worse than no report. --}}
                    <template x-if="lastFault">
                        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">
                                {{ __('messages.khqr_diag_last_fault') }}
                            </p>
                            <p class="mt-1 break-words font-mono text-xs leading-relaxed text-amber-900"
                               x-text="[lastFault.status ? 'HTTP ' + lastFault.status : null, lastFault.message].filter(Boolean).join(' · ')"></p>
                            <p class="mt-1 text-xs text-amber-700" x-text="lastFault.at"></p>
                        </div>
                    </template>

                    <div x-show="loading" class="flex items-center gap-2 text-sm text-slate-500">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                        </svg>
                        {{ __('messages.khqr_diag_running') }}
                    </div>

                    <ul x-show="!loading && checks.length" class="space-y-3">
                        <template x-for="check in checks" :key="check.key">
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                      :class="{ ok: 'bg-emerald-500', fail: 'bg-red-500', warn: 'bg-amber-500', info: 'bg-slate-400' }[check.state] || 'bg-slate-400'"
                                      x-text="{ ok: '✓', fail: '✕', warn: '!', info: 'i' }[check.state] || 'i'"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-800" x-text="check.label"></p>
                                    <p class="mt-0.5 break-words font-mono text-xs leading-relaxed text-slate-500" x-text="check.detail"></p>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <p x-show="!loading && failed" x-cloak class="mt-4 text-sm leading-relaxed text-slate-600">
                        {{ __('messages.khqr_diag_next_steps') }}
                    </p>
                @else
                    {{-- Public signup: no probe, no internals. Say what happened,
                         that no money moved, and what to do. --}}
                    <ul class="space-y-2 text-sm leading-relaxed text-slate-600">
                        <li>• {{ __('messages.khqr_diag_guest_no_charge') }}</li>
                        <li>• {{ __('messages.khqr_diag_guest_retry') }}</li>
                        <li>• {{ __('messages.khqr_diag_guest_support') }}</li>
                    </ul>
                @endif
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 p-4 sm:flex-row sm:justify-end">
                <button type="button" x-on:click="close()"
                        class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ __('messages.close') }}
                </button>
                @if ($endpoint)
                    <button type="button" x-on:click="run()" x-bind:disabled="loading"
                            class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                        {{ __('messages.khqr_diag_recheck') }}
                    </button>
                @endif
                @if ($settingsUrl)
                    <a href="{{ $settingsUrl }}"
                       class="inline-flex justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                        {{ __('messages.khqr_diag_open_settings') }}
                    </a>
                @endif
                @if ($retryUrl)
                    <a href="{{ $retryUrl }}"
                       class="inline-flex justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        {{ __('messages.payment_try_again') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

@once
<script>
    function khqrDiagnostics({ endpoint, autoOpen }) {
        return {
            shown: false,
            loading: false,
            checks: [],
            lastFault: null,
            ran: false,
            init() { if (autoOpen) this.open(); },
            open() {
                this.shown = true;
                // Probe once per opening, not on page load: each run costs live
                // requests against a metered Bakong token, so it is paid for by
                // someone actually asking what went wrong.
                if (endpoint && !this.ran) this.run();
            },
            close() { this.shown = false; },
            get failed() { return this.checks.some(c => c.state === 'fail'); },
            async run() {
                if (!endpoint) return;
                this.ran = true;
                this.loading = true;
                try {
                    const res = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.checks = data.checks || [];
                    this.lastFault = data.last_fault || null;
                } catch (e) {
                    // The popup exists because something is already broken —
                    // it must never end up blank.
                    this.checks = [{
                        key: 'fetch',
                        state: 'fail',
                        label: @json(__('messages.khqr_diag_failed')),
                        detail: String(e),
                    }];
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endonce
