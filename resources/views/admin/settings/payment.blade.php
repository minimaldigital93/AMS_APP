@extends('layouts.admin')

@section('title', __('messages.payment_settings'))

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.payment_settings') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('messages.payment_settings_hint') }}</p>

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

    <form method="POST" action="{{ route('admin.settings.payment.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <!-- Where rent lands -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.rent_payout_title') }}</h2>
                <p class="text-sm text-gray-500">{{ __('messages.rent_payout_hint') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_account_id') }}</label>
                <input type="text" name="bakong_account_id" value="{{ old('bakong_account_id', $settings?->bakong_account_id) }}"
                    placeholder="{{ __('messages.bakong_account_id_placeholder') }}"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                {{-- The account id is what makes a per-tenant, exact-amount QR
                     possible at all. Without it checkout falls back to the
                     uploaded static image, which carries no amount — so the
                     difference is worth stating on the field rather than
                     leaving the collector to discover it at the counter. --}}
                <p class="mt-1 text-xs text-gray-400">{{ __('messages.bakong_account_id_rent_hint') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bank_name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $settings?->bank_name) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bank_account_name') }}</label>
                    <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $settings?->bank_account_name) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.bank_account_number') }}</label>
                    <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $settings?->bank_account_number) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="md:w-1/3">
                <label class="block text-sm font-medium text-gray-700">{{ __('messages.currency') }}</label>
                <select name="currency" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="USD" @selected(old('currency', $settings?->currency ?? 'USD') === 'USD')>USD ($)</option>
                    <option value="KHR" @selected(old('currency', $settings?->currency) === 'KHR')>KHR (៛)</option>
                </select>
            </div>

            {{-- Said plainly, because it is the workflow and not a limitation to
                 be discovered: nobody but the landlord can see rent arrive in
                 their own bank, so nobody but the landlord can confirm it. --}}
            <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
                <p class="text-xs font-medium text-gray-700">{{ __('messages.rent_manual_confirm_title') }}</p>
                <p class="mt-0.5 text-xs text-gray-500">{{ __('messages.rent_manual_confirm_body') }}</p>
            </div>
        </div>

        {{-- The static scan-to-pay QR is uploaded on System Settings (one place
             for every uploaded image); this page only says where it lives. --}}
        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            {{ __('messages.payment_qr_code_hint') }}
            <a href="{{ route('admin.settings.index') }}" class="font-medium text-indigo-600 hover:text-indigo-700">
                {{ __('messages.system_settings') }} →
            </a>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                {{ __('messages.save') }}
            </button>
        </div>
    </form>
</div>
@endsection
