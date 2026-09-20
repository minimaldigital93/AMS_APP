@extends('layouts.superadmin')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('messages.payment_settings') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('messages.platform_payment_settings_hint') }}</p>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- The meter goes FIRST, above the form.
         This page is opened for two different reasons and only one of them is
         editing: far more often somebody is here because a payment failed, and
         the allowance is the likeliest answer. Putting the form first would
         make them scroll past the fields they must not change to reach the
         number they came for. --}}
    <div class="mt-6">
        <x-bakong-usage :report="$usage" :refresh-url="route('superadmin.settings.payment.usage')" />
    </div>

    <form method="POST" action="{{ route('superadmin.settings.payment.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.bakong_payout_title') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('messages.bakong_payout_hint') }}</p>
                </div>
                <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium
                    {{ $bakongEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-100 text-gray-500' }}">
                    {{ $bakongEnabled ? __('messages.active') : __('messages.off') }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_account_id') }}</label>
                    <input type="text" name="bakong_account_id" value="{{ old('bakong_account_id', $settings?->bakong_account_id) }}"
                        placeholder="{{ __('messages.bakong_account_id_placeholder') }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    {{-- A blank field is not "no account" — it falls through to
                         .env. Saying which value is actually in force saves the
                         operator guessing whether a server variable still holds
                         one. --}}
                    <p class="mt-1 text-xs {{ $bakong->isConfigured() ? 'text-gray-400' : 'text-red-600' }}">
                        @if ($bakong->isConfigured())
                            {{ __('messages.bakong_account_in_use') }} <span class="font-medium">{{ $bakong->accountId }}</span>
                            <span class="text-gray-400">({{ __('messages.bakong_account_from', ['source' => $bakong->source()]) }})</span>
                        @else
                            {{ __('messages.bakong_account_unset') }}
                        @endif
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.merchant_name') }}</label>
                    <input type="text" name="merchant_name" maxlength="25" value="{{ old('merchant_name', $settings?->merchant_name) }}"
                        placeholder="{{ $bakong->merchantName }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">{{ __('messages.merchant_name_hint') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.merchant_city') }}</label>
                    <input type="text" name="merchant_city" maxlength="15" value="{{ old('merchant_city', $settings?->merchant_city) }}"
                        placeholder="{{ $bakong->merchantCity }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">{{ __('messages.merchant_city_hint') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.currency') }}</label>
                    <select name="currency" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="USD" @selected(old('currency', $settings?->currency ?? 'USD') === 'USD')>USD ($)</option>
                        <option value="KHR" @selected(old('currency', $settings?->currency) === 'KHR')>KHR (៛)</option>
                    </select>
                </div>
            </div>

            {{-- The access token is deliberately NOT a field here. It is issued
                 from a terminal, stored encrypted and never rendered — a token
                 that passes through a browser, a form field or a flash message
                 is a token in someone's scrollback. --}}
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 space-y-1">
                <p class="text-xs font-medium text-gray-700">{{ __('messages.bakong_server_side_title') }}</p>
                <p class="text-xs text-gray-500">
                    {{ __('messages.bakong_api_endpoint') }}
                    <code class="rounded bg-white border border-gray-200 px-1.5 py-0.5 text-indigo-700">{{ $baseUrl }}</code>
                </p>
                <p class="text-xs text-gray-500">
                    {{ __('messages.bakong_token_server_side') }}
                    <code class="rounded bg-white border border-gray-200 px-1.5 py-0.5 text-indigo-700">php artisan bakong:token status</code>
                </p>
                {{-- Stated positively rather than left as an absence: an
                     operator who has used a hosted gateway before WILL go
                     looking for a callback field, and finding none looks like a
                     missing feature instead of a property of the API. --}}
                <p class="text-xs text-gray-400">{{ __('messages.bakong_no_webhook_note') }}</p>
            </div>
        </div>

        {{-- ─────────────────── the switch + who we are to NBC ─────────────────
             These used to be .env only, which meant the person who read the
             verification code out of the inbox could not correct the address it
             was sent to without SSH. Every field here falls back to .env when
             left blank, so clearing one means "stop overriding", not "set it to
             nothing". --}}
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.bakong_api_title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('messages.bakong_api_hint') }}</p>
            </div>

            <label class="flex items-start gap-3">
                <input type="hidden" name="bakong_enabled" value="0">
                <input type="checkbox" name="bakong_enabled" value="1"
                       @checked(old('bakong_enabled', $settings?->bakong_enabled ?? $envDefaults['bakong.enabled'] ?? false))
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">{{ __('messages.bakong_enabled_label') }}</span>
                    {{-- Said plainly because the failure is silent: with this
                         off, checkout REFUSES rather than falling back. There is
                         nothing left to fall back to. --}}
                    <span class="block text-xs text-gray-500">{{ __('messages.bakong_enabled_hint') }}</span>
                </span>
            </label>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_email_label') }}</label>
                    <input type="email" name="bakong_email"
                           value="{{ old('bakong_email', $settings?->bakong_email) }}"
                           placeholder="{{ $envDefaults['bakong.integrator.email'] ?: __('messages.bakong_not_set') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    {{-- The one field on this page whose mistake is invisible
                         for ninety days: it is renew_token's entire payload. --}}
                    <p class="mt-1 text-xs text-gray-500">{{ __('messages.bakong_email_hint') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_organization_label') }}</label>
                    <input type="text" name="bakong_organization"
                           value="{{ old('bakong_organization', $settings?->bakong_organization) }}"
                           placeholder="{{ $envDefaults['bakong.integrator.organization'] ?: __('messages.bakong_not_set') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_project_label') }}</label>
                    <input type="text" name="bakong_project"
                           value="{{ old('bakong_project', $settings?->bakong_project) }}"
                           placeholder="{{ $envDefaults['bakong.integrator.project'] ?: __('messages.bakong_not_set') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            {{-- ───────────────────────── the access token ─────────────────────
                 The only credential on this page, and the only field that is
                 never rendered back. What is shown instead is the fingerprint
                 and expiry of what is stored — enough to tell two tokens apart
                 and to see when this one dies, without putting a live bearer
                 credential in a browser, a screenshot or a scrollback.

                 It is `password`, autocomplete is off, and it is in
                 dontFlash() so a validation error elsewhere on this form
                 cannot bounce it back into old(). --}}
            <div class="border-t border-gray-100 pt-4">
                <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_token_label') }}</label>

                <div class="mt-1 flex items-center gap-2 text-xs">
                    @if ($usage['token']['usable'])
                        <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 font-medium text-green-700">{{ __('messages.bakong_token_stored') }}</span>
                        <span class="text-gray-500">{{ __('messages.bakong_token_stored_detail', [
                            'fingerprint' => $usage['token']['fingerprint'],
                            'date' => $usage['token']['expires_at_human'] ?? '—',
                        ]) }}</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700">{{ __('messages.bakong_token_none') }}</span>
                    @endif
                </div>

                <input type="password" name="bakong_token" value="" autocomplete="off" spellcheck="false"
                       placeholder="{{ __('messages.bakong_token_placeholder') }}"
                       class="mt-2 w-full rounded-lg border-gray-300 font-mono text-xs focus:border-indigo-500 focus:ring-indigo-500">

                @error('bakong_token')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                {{-- Says where it comes from, because the command whose name
                     matches what the operator wants (`request`) is metered and
                     404s. --}}
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('messages.bakong_token_hint') }}
                    <a href="{{ rtrim(config('bakong.base_url'), '/') }}/register" target="_blank" rel="noopener noreferrer"
                       class="underline">{{ rtrim(config('bakong.base_url'), '/') }}/register</a>
                </p>
                <p class="mt-1 text-xs text-gray-400">{{ __('messages.bakong_token_blank_note') }}</p>
            </div>
        </div>

        {{-- ──────────────────────── the quota guards ───────────────────────
             The only thing between a busy day and errorCode 17. They live next
             to the meter above on purpose: tuning them is a decision made while
             looking at what today actually spent. --}}
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.bakong_quota_title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('messages.bakong_quota_hint') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['bakong_daily_request_limit', 'bakong.daily_request_limit', 'bakong_daily_limit_label', 'bakong_daily_limit_hint'],
                    ['bakong_verify_cooldown',     'bakong.verify_cooldown',     'bakong_cooldown_label',    'bakong_cooldown_hint'],
                    ['bakong_qr_ttl',              'bakong.qr_ttl',              'bakong_qr_ttl_label',      'bakong_qr_ttl_hint'],
                    ['bakong_max_verify_attempts', 'bakong.max_verify_attempts', 'bakong_max_attempts_label','bakong_max_attempts_hint'],
                ] as [$field, $configKey, $label, $hint])
                    <div>
                        {{-- Reserved height: these labels wrap to two lines at
                             some widths and not others, which left the four
                             inputs on a ragged baseline. --}}
                        <label class="block text-sm font-medium text-gray-700 sm:min-h-[2.5rem]">{{ __('messages.'.$label) }}</label>
                        <input type="number" name="{{ $field }}"
                               value="{{ old($field, $settings?->{$field}) }}"
                               placeholder="{{ $envDefaults[$configKey] }}"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">{{ __('messages.'.$hint) }}</p>
                    </div>
                @endforeach
            </div>

            <label class="flex items-start gap-3 border-t border-gray-100 pt-4">
                <input type="hidden" name="bakong_reconcile_enabled" value="0">
                <input type="checkbox" name="bakong_reconcile_enabled" value="1"
                       @checked(old('bakong_reconcile_enabled', $settings?->bakong_reconcile_enabled ?? $envDefaults['bakong.reconcile_enabled'] ?? false))
                       class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">{{ __('messages.bakong_reconcile_label') }}</span>
                    {{-- Ships off, and the hint says why rather than just what:
                         Bakong sends no webhook, so this is the only net for a
                         payer who closed the tab — and pure spend on the days
                         nobody does. --}}
                    <span class="block text-xs text-gray-500">{{ __('messages.bakong_reconcile_hint') }}</span>
                </span>
            </label>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                {{ __('messages.save') }}
            </button>
        </div>
    </form>
</div>
@endsection
