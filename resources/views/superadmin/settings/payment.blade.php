@extends('layouts.superadmin')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('Payment Settings') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('Where subscription payments from your merchants go. Values saved here override the .env configuration; blank fields fall back to it.') }}</p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('superadmin.settings.payment.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <!-- Bank details -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('Bank Details') }}</h2>
                <p class="text-sm text-gray-500">{{ __('Your own bank account — where merchants\' subscription payments settle.') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Bank Name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $settings?->bank_name) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Account Name') }}</label>
                    <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $settings?->bank_account_name) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Account Number') }}</label>
                    <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $settings?->bank_account_number) }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Currency') }}</label>
                    <select name="currency" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="USD" @selected(old('currency', $settings?->currency ?? config('services.khqrpay.currency', 'USD')) === 'USD')>USD ($)</option>
                        <option value="KHR" @selected(old('currency', $settings?->currency ?? config('services.khqrpay.currency')) === 'KHR')>KHR (៛)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('KHQR Image') }}</label>
                <p class="text-xs text-gray-400">{{ __('Optional: the static KHQR from your banking app (max 2 MB), as a manual fallback for subscription payments.') }}</p>
                @if ($settings?->khqr_image_path)
                    <div class="mt-2 flex items-center gap-4">
                        <img src="{{ Storage::disk('public')->url($settings->khqr_image_path) }}" alt="KHQR"
                            class="w-32 h-32 object-contain rounded-lg border border-gray-200 bg-white p-1">
                        <label class="inline-flex items-center gap-2 text-sm text-red-600">
                            <input type="checkbox" name="remove_khqr_image" value="1" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            {{ __('Remove') }}
                        </label>
                    </div>
                @endif
                <input type="file" name="khqr_image" accept="image/*"
                    class="mt-2 block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-600 hover:file:bg-indigo-100">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Bakong Account ID') }}</label>
                    <p class="text-xs text-gray-400">{{ __('e.g. yourname@aclb — used to generate dynamic KHQR locally.') }}</p>
                    <input type="text" name="bakong_account_id" value="{{ old('bakong_account_id', $settings?->bakong_account_id) }}"
                        placeholder="{{ config('services.khqrpay.bakong_id') }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Merchant Name on QR') }}</label>
                    <p class="text-xs text-gray-400">{{ __('Shown in banking apps when scanning (max 25 characters).') }}</p>
                    <input type="text" name="merchant_name" maxlength="25" value="{{ old('merchant_name', $settings?->merchant_name) }}"
                        placeholder="{{ config('services.khqrpay.merchant_name') }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
        </div>

        <!-- KHQRPay API -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ __('KHQRPay API') }}</h2>
                <p class="text-sm text-gray-500">{{ __('From your KHQRPay (khqr.cc) dashboard — used to mint and auto-verify the subscription checkout QRs.') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Profile ID') }}</label>
                    <input type="text" name="khqrpay_profile_id" value="{{ old('khqrpay_profile_id', $settings?->khqrpay_profile_id) }}"
                        placeholder="{{ config('services.khqrpay.profile_id') ? __('(currently from .env)') : '' }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Secret Key') }}</label>
                    <input type="password" name="khqrpay_secret" value="" autocomplete="new-password"
                        placeholder="{{ $secretConfigured ? '••••••••  ('.__('configured').')' : '' }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">{{ __('Leave blank to keep the current secret.') }}</p>
                </div>
            </div>

            @if (config('services.khqrpay.demo'))
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
                    {{ __('Demo mode is ON (KHQRPAY_DEMO=true in .env) — QRs are simulated and auto-confirm. Turn it off once the live credentials above are verified.') }}
                </div>
            @endif
        </div>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                {{ __('Save') }}
            </button>
        </div>
    </form>
</div>
@endsection
