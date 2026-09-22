{{--
    One iOS-style settings row: line icon, label, and whichever control the key
    needs. Shared by the settings index (System Preferences) and every settings
    sub-page, so a field keeps the same control wherever it is rendered — the
    cards moved to pages of their own, the rows did not change.

    @param string $key    The setting key; also the input id.
    @param mixed  $value  The value to show, already old()-resolved by the caller.
--}}
@php
    // Minimal iOS-style line icon (SVG path) per setting key
    $rowIcons = [
        'company_name'    => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'company_address' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
        'company_phone'   => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
        'company_email'   => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'owner_name'      => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'owner_gender'    => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1',
        'owner_id_card'   => 'M3 5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5zM7 8h4M7 12h10M7 16h10',
        'owner_phone'     => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
        'owner_address'   => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
        'late_fee_percent'     => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'billing_cycle_day'    => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'billing_overdue_days' => 'M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z',
        'system_currency' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    $defaultRowIcon = 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z';

    $icon = $rowIcons[$key] ?? $defaultRowIcon;
    $multiline = in_array($key, ['company_address', 'owner_address'], true);
    $label = \Illuminate\Support\Facades\Lang::has('messages.'.$key)
        ? __('messages.'.$key)
        : ucwords(str_replace('_', ' ', $key));

    $inputClasses = 'flex-1 bg-transparent border-0 p-0 text-right text-[15px] text-gray-500 focus:text-gray-900 placeholder-gray-400 focus:ring-0 focus:outline-none';
    // bg-none removes the forms-plugin "v" arrow; the iOS chevron is drawn separately
    $selectClasses = 'appearance-none bg-none bg-transparent border-0 p-0 pr-5 text-right text-[15px] text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none cursor-pointer';
    $chevronIcon = 'M8 9l4-4 4 4m0 6l-4 4-4-4';
@endphp
<div class="flex {{ $multiline ? 'items-start' : 'items-center' }} gap-3 px-4 py-3"@if($key === 'khr_exchange_rate') x-show="currency === 'KHR'" x-cloak @endif>
    <svg class="flex-shrink-0 w-5 h-5 text-gray-400 {{ $multiline ? 'mt-0.5' : '' }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
    </svg>
    <label for="{{ $key }}" class="text-[15px] text-gray-900 whitespace-nowrap {{ $multiline ? 'pt-0.5' : '' }}">{{ $label }}</label>
    @if($multiline)
    <textarea name="settings[{{ $key }}]" id="{{ $key }}" rows="2"
        class="{{ $inputClasses }} resize-none"
        placeholder="{{ __('messages.enter') }} {{ $label }}">{{ $value }}</textarea>
    @elseif($key === 'system_currency')
    <span class="relative ml-auto inline-flex items-center">
        <select name="settings[{{ $key }}]" id="{{ $key }}" x-model="currency" class="{{ $selectClasses }}">
            <option value="USD" {{ $value == 'USD' ? 'selected' : '' }}>USD ($)</option>
            <option value="KHR" {{ $value == 'KHR' ? 'selected' : '' }}>KHR (៛)</option>
        </select>
        <svg class="pointer-events-none absolute right-0 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevronIcon }}" />
        </svg>
    </span>
    @elseif($key === 'owner_gender')
    <span class="relative ml-auto inline-flex items-center">
        <select name="settings[{{ $key }}]" id="{{ $key }}" class="{{ $selectClasses }}">
            <option value="">{{ __('messages.not_specified') }}</option>
            @foreach(['male', 'female', 'other'] as $g)
                <option value="{{ $g }}" {{ $value === $g ? 'selected' : '' }}>{{ __('messages.'.$g) }}</option>
            @endforeach
        </select>
        <svg class="pointer-events-none absolute right-0 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevronIcon }}" />
        </svg>
    </span>
    @elseif($key === 'late_fee_percent')
    <span class="ml-auto inline-flex items-center gap-1 text-[15px] text-gray-400">
        <input type="number" min="0" max="100" step="any" name="settings[{{ $key }}]" id="{{ $key }}"
            value="{{ $value }}"
            class="w-20 bg-transparent border-0 p-0 text-right text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none"
            placeholder="0">
        <span>%</span>
    </span>
    @elseif($key === 'billing_cycle_day')
    {{-- 1–28 only: the 29th–31st don't exist in every month, so they can't
         anchor a monthly cycle. Blank = keep billing each tenant on their own
         move-in day. --}}
    <span class="relative ml-auto inline-flex items-center">
        <select name="settings[{{ $key }}]" id="{{ $key }}" class="{{ $selectClasses }}">
            <option value="">{{ __('messages.billing_cycle_day_none') }}</option>
            @for($d = 1; $d <= \App\Services\Billing\BillingCycleService::MAX_COLLECTION_DAY; $d++)
                <option value="{{ $d }}" {{ (int) $value === $d ? 'selected' : '' }}>{{ __('messages.day_of_month', ['day' => $d]) }}</option>
            @endfor
        </select>
        <svg class="pointer-events-none absolute right-0 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevronIcon }}" />
        </svg>
    </span>
    @elseif($key === 'billing_overdue_days')
    <span class="ml-auto inline-flex items-center gap-1 text-[15px] text-gray-400">
        <input type="number" min="0" max="31" step="1" name="settings[{{ $key }}]" id="{{ $key }}"
            value="{{ $value }}"
            class="w-20 bg-transparent border-0 p-0 text-right text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none"
            placeholder="{{ \App\Services\Billing\BillingCycleService::DEFAULT_OVERDUE_DAYS }}">
        <span>{{ __('messages.days_word') }}</span>
    </span>
    @elseif($key === 'khr_exchange_rate')
    <span class="ml-auto inline-flex items-center gap-1 text-[15px] text-gray-400">
        <span>1 $ =</span>
        <input type="number" min="1" step="any" name="settings[{{ $key }}]" id="{{ $key }}"
            value="{{ $value }}"
            class="w-24 bg-transparent border-0 p-0 text-right text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none"
            placeholder="4100">
        <span>៛</span>
    </span>
    @else
    <input type="text" name="settings[{{ $key }}]" id="{{ $key }}" value="{{ $value }}"
        class="{{ $inputClasses }}"
        placeholder="{{ __('messages.enter') }} {{ $label }}">
    @endif
</div>
