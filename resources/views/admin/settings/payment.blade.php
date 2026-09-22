{{--
    Payment Settings, in the same iOS-style rows System Settings uses: one card
    per question, a line icon and a label on the left, the value on the right.

    Every stacked <label>, hint and explanatory paragraph is gone. Two things
    stayed, because neither can be read off the fields themselves:

      • MerchantBakongCredentials::diagnose(), the only place that names
        BAKONG_API_ENABLED — a landlord can fill this page in correctly and
        still get silence because of a platform switch nothing else here
        mentions,
      • the stored token's fingerprint and expiry.

    Auto-confirm is one "Active" row with the same toggle the utility-prices and
    expense-category pages use.
--}}
@extends('layouts.admin')

@section('title', __('messages.payment_settings'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.payment_settings') }}</h1>
            <a href="{{ route('admin.settings.index') }}" class="ml-auto flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-600 transition" title="{{ __('messages.back') }}" aria-label="{{ __('messages.back') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm" role="alert">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            // Same minimal line icons the System Settings rows use.
            $rowIcons = [
                'bakong_account_id'   => 'M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zm0 0c0 1.657 1.007 3 2.25 3S21 13.657 21 12a9 9 0 10-2.636 6.364M16.5 12V8.25',
                'bank_name'           => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z',
                'bank_account_name'   => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                'bank_account_number' => 'M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 015.25 3.75h13.5A2.25 2.25 0 0121 6v3.776',
                'currency'            => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'token'               => 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z',
                'stored'              => 'M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z',
                'active'              => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'qr'                  => 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Zm0 9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm9.75-9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm0 9.75h3m3 0h-.008v.008H19.5v-.008Zm-3 3h.008v.008H16.5v-.008Zm3 3h.008v.008H19.5v-.008Zm-3 0h.008v.008H16.5v-.008Zm-3-3h.008v.008H13.5v-.008Zm0 3h.008v.008H13.5v-.008Z',
            ];

            // The payout rows — every one of them is printed on the QR a tenant
            // scans, which is why they are rendered straight back into the form.
            $payoutFields = [
                'bakong_account_id'   => __('messages.bakong_account_id_placeholder'),
                'bank_name'           => '',
                'bank_account_name'   => '',
                'bank_account_number' => '',
            ];

            $inputClasses = 'flex-1 min-w-0 bg-transparent border-0 p-0 text-right text-[15px] text-gray-500 focus:text-gray-900 placeholder-gray-400 focus:ring-0 focus:outline-none';
            // bg-none removes the forms-plugin "v" arrow; the iOS chevron is drawn separately
            $selectClasses = 'appearance-none bg-none bg-transparent border-0 p-0 pr-5 text-right text-[15px] text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none cursor-pointer';
            $chevronIcon = 'M8 9l4-4 4 4m0 6l-4 4-4-4';
        @endphp

        <form method="POST" action="{{ route('admin.settings.payment.update') }}" class="space-y-8">
            @csrf
            @method('PUT')

            <!-- Where rent is paid -->
            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.rent_payout_title') }}</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                    @foreach($payoutFields as $field => $placeholder)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons[$field] }}" />
                        </svg>
                        <label for="{{ $field }}" class="text-[15px] text-gray-900 whitespace-nowrap">{{ __('messages.'.$field) }}</label>
                        <input type="text" name="{{ $field }}" id="{{ $field }}"
                            value="{{ old($field, $settings?->{$field}) }}"
                            class="{{ $inputClasses }}"
                            placeholder="{{ $placeholder ?: __('messages.enter').' '.__('messages.'.$field) }}">
                    </div>
                    @endforeach

                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons['currency'] }}" />
                        </svg>
                        <label for="currency" class="text-[15px] text-gray-900 whitespace-nowrap">{{ __('messages.currency') }}</label>
                        <span class="relative ml-auto inline-flex items-center">
                            <select name="currency" id="currency" class="{{ $selectClasses }}">
                                <option value="USD" @selected(old('currency', $settings?->currency ?? 'USD') === 'USD')>USD ($)</option>
                                <option value="KHR" @selected(old('currency', $settings?->currency) === 'KHR')>KHR (៛)</option>
                            </select>
                            <svg class="pointer-events-none absolute right-0 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevronIcon }}" />
                            </svg>
                        </span>
                    </div>
                </div>
            </div>

            {{-- The landlord's OWN Bakong credential — deliberately not the
                 platform's, which is metered for the whole installation and
                 shared with subscriptions, so one building's rent day would lock
                 out every other landlord and every signup. --}}
            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.bakong_auto_confirm_title') }}</p>

                {{-- The diagnosis, not the raw settings. "expired" and "not
                     configured" are already visible from the rows below, so this
                     only speaks up for the two causes that would otherwise leave
                     a correctly-filled page confirming nothing: the toggle being
                     off, and the platform-level switch, which nothing else here
                     shows. --}}
                @unless(in_array($bakongDiagnosis['reason'], ['not_configured', 'expired'], true))
                    <div @class([
                            'mb-2 rounded-lg border px-4 py-3 text-[13px]',
                            'bg-emerald-50 border-emerald-200 text-emerald-700' => $bakongDiagnosis['active'],
                            'bg-amber-50 border-amber-200 text-amber-700' => ! $bakongDiagnosis['active'],
                        ])>
                        {{ __('messages.bakong_diag_'.$bakongDiagnosis['reason']) }}
                    </div>
                @endunless

                <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                    @if($bakongToken['configured'])
                    {{-- The stored credential is reported as a fingerprint and an
                         expiry, never as its value. --}}
                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 {{ $bakongToken['expired'] ? 'text-red-400' : 'text-emerald-500' }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons['stored'] }}" />
                        </svg>
                        <div class="min-w-0">
                            <p class="text-[15px] font-mono text-gray-900 truncate">{{ $bakongToken['fingerprint'] }}</p>
                            <p class="text-[12px] text-gray-400">
                                @if($bakongToken['expired'])
                                    <span class="font-medium text-red-600">{{ __('messages.bakong_token_expired_badge') }}</span>
                                @elseif($bakongToken['expires_at'])
                                    {{ __('messages.expires') }} · {{ $bakongToken['expires_at']->toDayDateTimeString() }}
                                @else
                                    {{ __('messages.bakong_token_no_expiry') }}
                                @endif
                            </p>
                        </div>
                        <button type="button" onclick="document.getElementById('forget-bakong-token').submit()"
                            class="ml-auto flex-shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 transition"
                            title="{{ __('messages.remove') }}" aria-label="{{ __('messages.remove') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                    @endif

                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons['token'] }}" />
                        </svg>
                        <label for="bakong_token" class="text-[15px] text-gray-900 whitespace-nowrap">{{ __('messages.bakong_token_label') }}</label>
                        {{-- value is ALWAYS empty: a live bearer credential is
                             never rendered back into a page. Blank on save means
                             keep the stored one. --}}
                        <input type="password" name="bakong_token" id="bakong_token" value="" autocomplete="new-password"
                            placeholder="{{ $bakongToken['configured'] ? __('messages.bakong_token_keep_placeholder') : 'eyJhbGciOi…' }}"
                            class="{{ $inputClasses }} font-mono">
                    </div>

                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons['active'] }}" />
                        </svg>
                        <label for="bakong_enabled" class="text-[15px] text-gray-900">{{ __('messages.active') }}</label>
                        {{-- Unchecked checkboxes don't POST, so ship an explicit 0 first. --}}
                        <input type="hidden" name="bakong_enabled" value="0">
                        <label class="ml-auto relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="bakong_enabled" id="bakong_enabled" value="1"
                                class="sr-only peer" @checked(old('bakong_enabled', $settings?->bakong_enabled ?? false))>
                            <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-500 transition-colors"></span>
                            <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- The static scan-to-pay QR has a page of its own; this row only
                 says where it lives. --}}
            <div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <a href="{{ route('admin.settings.payment_qr') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons['qr'] }}" />
                        </svg>
                        <p class="min-w-0 text-[15px] text-gray-900">{{ __('messages.payment_qr_code') }}</p>
                        <svg class="ml-auto flex-shrink-0 w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white font-semibold py-2.5 px-8 rounded-full transition duration-200 flex items-center gap-2 shadow-sm" title="{{ __('messages.save') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                    </svg></button>
            </div>
        </form>

        {{-- Its own form: removing a credential is a deliberate action, never a
             side effect of saving a bank name. Outside the settings form because
             forms cannot nest. --}}
        <form id="forget-bakong-token" method="POST" action="{{ route('admin.settings.payment.forget_token') }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>

    </div>
</div>
@endsection
