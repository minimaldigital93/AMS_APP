@extends('layouts.admin')

@section('title', __('messages.default_utility_prices'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.default_utility_prices') }}</h1>
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
            // Same minimal line icons the settings rows use.
            $rowIcons = [
                'utility_electricity_price' => 'M13 10V3L4 14h7v7l9-11h-7z',
                'utility_water_price'       => 'M12 3s6 6.5 6 10.5a6 6 0 11-12 0C6 9.5 12 3 12 3z',
                'utility_parking_fee'       => 'M5 17a2 2 0 104 0m6 0a2 2 0 104 0M3 13l2-6a2 2 0 011.9-1.4h10.2A2 2 0 0119 7l2 6v4a1 1 0 01-1 1H4a1 1 0 01-1-1v-4z',
                'utility_internet_fee'      => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
                'utility_garbage_fee'       => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                'utility_meter_auto_calc'   => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            ];
            $priceKeys = \App\Http\Controllers\Admin\UtilityPriceController::PRICE_KEYS;
        @endphp

        <form method="POST" action="{{ route('admin.settings.utility_prices.update') }}" class="space-y-8">
            @csrf
            @method('PUT')

            <!-- Monthly prices -->
            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.utilities') }}</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                    @foreach($priceKeys as $key)
                    @php $currentValue = old("settings.$key", $values[$key]); @endphp
                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons[$key] }}" />
                        </svg>
                        <label for="{{ $key }}" class="text-[15px] text-gray-900 whitespace-nowrap">{{ __('messages.'.$key) }}</label>
                        <span class="ml-auto inline-flex items-center gap-1 text-[15px] text-gray-400">
                            <span>{{ currency_symbol() }}</span>
                            <input type="number" min="0" step="any" name="settings[{{ $key }}]" id="{{ $key }}"
                                value="{{ filled($currentValue) ? money_input($currentValue) : '' }}"
                                class="w-28 bg-transparent border-0 p-0 text-right text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none"
                                placeholder="0">
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Metered charges -->
            @php $meterOn = (string) old('settings.utility_meter_auto_calc', $values['utility_meter_auto_calc']) === '1'; @endphp
            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.meter_readings') }}</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rowIcons['utility_meter_auto_calc'] }}" />
                        </svg>
                        <label for="utility_meter_auto_calc" class="text-[15px] text-gray-900">{{ __('messages.utility_meter_auto_calc') }}</label>
                        {{-- Unchecked checkboxes don't POST, so ship an explicit 0 first. --}}
                        <input type="hidden" name="settings[utility_meter_auto_calc]" value="0">
                        <label class="ml-auto relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="settings[utility_meter_auto_calc]" id="utility_meter_auto_calc" value="1"
                                class="sr-only peer" {{ $meterOn ? 'checked' : '' }}>
                            <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-500 transition-colors"></span>
                            <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white font-semibold py-2.5 px-8 rounded-full transition duration-200 flex items-center gap-2 shadow-sm" title="{{ __('messages.save_all_settings') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                    </svg></button>
            </div>
        </form>

    </div>
</div>
@endsection
