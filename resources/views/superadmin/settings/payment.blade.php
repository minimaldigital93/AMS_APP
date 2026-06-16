@extends('layouts.superadmin')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('Payment Settings') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('Where subscription payments from your landlords settle. Register your KHQRPay merchant details below — no server or developer setup needed.') }}</p>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('superadmin.settings.payment.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

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
                        placeholder="{{ __('From your khqr.cc dashboard') }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Secret Key') }}</label>
                    <input type="password" name="khqrpay_secret" value="" autocomplete="new-password"
                        placeholder="{{ $secretConfigured ? '••••••••  ('.__('configured').')' : '' }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">{{ __('Leave blank to keep the current secret.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Currency') }}</label>
                    <select name="currency" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="USD" @selected(old('currency', $settings?->currency ?? 'USD') === 'USD')>USD ($)</option>
                        <option value="KHR" @selected(old('currency', $settings?->currency) === 'KHR')>KHR (៛)</option>
                    </select>
                </div>
            </div>

            <div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
                <label class="block text-xs font-medium text-gray-700">{{ __('Webhook / Callback URL') }}</label>
                <p class="text-xs text-gray-400">{{ __('Paste this into the "Global Webhook URL" field of your khqr.cc profile so paid subscriptions are confirmed automatically.') }}</p>
                <code class="mt-1 block break-all rounded bg-white border border-gray-200 px-2 py-1 text-xs text-indigo-700">{{ route('khqr.callback') }}</code>
            </div>

            @if (config('services.khqrpay.demo'))
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700">
                    {{ __('Demo mode is ON — checkout QRs are simulated and auto-confirm without real money moving. Live payments use the credentials above.') }}
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
