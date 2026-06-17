@extends('layouts.superadmin')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900">{{ __('Payment Settings') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('Your KHQRPay credentials for subscription payments. Enter the Profile ID and Secret from your khqr.cc dashboard — no server or developer setup needed.') }}</p>

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
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        {{ __('Secret Key') }}
                        @if ($secretConfigured)
                            <span class="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                {{ __('Configured') }}
                            </span>
                        @endif
                    </label>
                    <input type="password" name="khqrpay_secret" value="" autocomplete="new-password"
                        placeholder="{{ $secretConfigured ? __('Enter a new key to replace the saved one') : '' }}"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @if ($secretConfigured)
                        <p class="mt-1 flex items-center gap-1 text-xs font-medium text-emerald-600">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            {{ __('A secret key is saved — leave blank to keep it.') }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-gray-400">{{ __('Leave blank to keep the current secret.') }}</p>
                    @endif
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
