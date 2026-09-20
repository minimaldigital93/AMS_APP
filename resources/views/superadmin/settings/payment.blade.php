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

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                {{ __('messages.save') }}
            </button>
        </div>
    </form>
</div>
@endsection
