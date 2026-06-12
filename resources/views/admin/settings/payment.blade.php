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

    <form method="POST" action="{{ route('admin.settings.payment.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <!-- Bank details (manual channel) -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.bank_details') }}</h2>
                <p class="text-sm text-gray-500">{{ __('messages.bank_details_hint') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.currency') }}</label>
                    <select name="currency" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="USD" @selected(old('currency', $settings?->currency ?? 'USD') === 'USD')>USD ($)</option>
                        <option value="KHR" @selected(old('currency', $settings?->currency) === 'KHR')>KHR (៛)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('messages.khqr_image') }}</label>
                <p class="text-xs text-gray-400">{{ __('messages.khqr_image_hint') }}</p>
                @if ($settings?->khqr_image_path)
                    <div class="mt-2 flex items-center gap-4">
                        <img src="{{ Storage::disk('public')->url($settings->khqr_image_path) }}" alt="KHQR"
                            class="w-32 h-32 object-contain rounded-lg border border-gray-200 bg-white p-1">
                        <label class="inline-flex items-center gap-2 text-sm text-red-600">
                            <input type="checkbox" name="remove_khqr_image" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            {{ __('messages.remove') }}
                        </label>
                    </div>
                @endif
                <input type="file" name="khqr_image" accept="image/*"
                    class="mt-2 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-600 hover:file:bg-indigo-100">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('messages.bakong_account_id') }}</label>
                <p class="text-xs text-gray-400">{{ __('messages.bakong_account_id_hint') }}</p>
                <input type="text" name="bakong_account_id" value="{{ old('bakong_account_id', $settings?->bakong_account_id) }}"
                    placeholder="yourname@bank" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <!-- KHQRPay API (auto-verified channel) -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4" x-data="{ enabled: {{ old('khqrpay_enabled', $settings?->khqrpay_enabled) ? 'true' : 'false' }} }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.khqrpay_api') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('messages.khqrpay_api_hint') }}</p>
                </div>
                <label class="inline-flex items-center gap-2 shrink-0 text-sm font-medium text-gray-700">
                    <input type="hidden" name="khqrpay_enabled" value="0">
                    <input type="checkbox" name="khqrpay_enabled" value="1" x-model="enabled"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    {{ __('messages.enabled') }}
                </label>
            </div>

            <div x-show="enabled" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.khqrpay_profile_id') }}</label>
                    <input type="text" name="khqrpay_profile_id" value="{{ old('khqrpay_profile_id', $settings?->khqrpay_profile_id) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('messages.khqrpay_secret') }}</label>
                    <input type="password" name="khqrpay_secret" value="" autocomplete="new-password"
                        placeholder="{{ $secretConfigured ? '••••••••  ('.__('messages.khqrpay_secret_configured').')' : '' }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">{{ __('messages.khqrpay_secret_hint') }}</p>
                </div>
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
