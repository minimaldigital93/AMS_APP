@extends('layouts.admin')

@section('title', __('messages.settings_title'))

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
    <!-- Header -->
        <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ __('messages.settings_title') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('messages.settings_subtitle') }}</p>
        </div>
        <button onclick="confirmReset()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
            </svg>
            {{ __('messages.reset_all') }}
        </button>
    </div>

    <!-- Flash Messages -->
    @if ($message = Session::get('success'))
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-gray-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </div>
    @endif

    @if ($message = Session::get('error'))
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-gray-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </div>
    @endif

    <!-- Language Settings Section (separate form) -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-gray-400 to-gray-600 text-white px-6 py-4">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                </svg>
                <h3 class="text-xl font-bold">{{ __('messages.language_settings') }}</h3>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                <div>
                    <label for="language_select" class="block text-sm font-semibold text-gray-700">{{ __('messages.language') }}</label>
                    <span class="text-xs text-gray-500">{{ __('messages.select_language') }}</span>
                </div>
                <div class="md:col-span-2">
                    <form method="POST" action="{{ route('language.switch') }}" id="languageForm">
                        @csrf
                        <select name="locale" id="language_select" onchange="document.getElementById('languageForm').submit()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                            <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>🇺🇸 English</option>
                            <option value="km" {{ app()->getLocale() == 'km' ? 'selected' : '' }}>🇰🇭 ភាសាខ្មែរ (Khmer)</option>
                        </select>
                        </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form method="POST" action="{{ route('admin.settings.updateBatch') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @php
            $categoryLabels = [
                'app' => ['title' => __('messages.application_settings'), 'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
                'company' => ['title' => __('messages.company_information'), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                'email' => ['title' => __('messages.email_configuration'), 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                'system' => ['title' => __('messages.system_preferences'), 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
                'fiscal' => ['title' => __('messages.fiscal_period_settings'), 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                'notification' => ['title' => __('messages.notification_settings'), 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
            ];
        @endphp

        @foreach($defaultSettings as $category => $categorySettings)
        <div class="bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden mb-6">
            <!-- Category Header -->
            <div class="bg-gradient-to-r from-gray-400 to-gray-600 text-white px-6 py-4">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $categoryLabels[$category]['icon'] ?? 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4' }}" />
                    </svg>
                    <h3 class="text-xl font-bold">{{ $categoryLabels[$category]['title'] ?? ucfirst($category) }}</h3>
                </div>
            </div>

            <!-- Settings Grid -->
            <div class="p-6 space-y-4">
                @foreach($categorySettings as $key => $defaultValue)
                @php
                    $currentValue = $settings->flatten()->firstWhere('key', $key)->value ?? $defaultValue;
                    $label = \Illuminate\Support\Facades\Lang::has('messages.' . $key)
                        ? __('messages.' . $key)
                        : ucwords(str_replace('_', ' ', substr($key, strlen($category) + 1)));
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center pb-4 border-b border-gray-100 last:border-b-0 last:pb-0">
                    <div>
                        <label for="{{ $key }}" class="block text-sm font-semibold text-gray-700">{{ $label }}</label>
                        <span class="text-xs text-gray-500">{{ $key }}</span>
                    </div>
                    <div class="md:col-span-2">
                        @if(in_array($key, ['company_address']))
                        <textarea name="settings[{{ $key }}]" id="{{ $key }}" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                            placeholder="{{ __('messages.enter') }} {{ $label }}">{{ $currentValue }}</textarea>
                        @elseif(str_contains($key, 'auto_close') || str_contains($key, 'reminder') || str_contains($key, 'expiry'))
                        <select name="settings[{{ $key }}]" id="{{ $key }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                            <option value="yes" {{ $currentValue == 'yes' ? 'selected' : '' }}>{{ __('messages.yes') }}</option>
                            <option value="no" {{ $currentValue == 'no' ? 'selected' : '' }}>{{ __('messages.no') }}</option>
                        </select>
                        @elseif($key == 'app_timezone')
                        <select name="settings[{{ $key }}]" id="{{ $key }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                            <option value="UTC" {{ $currentValue == 'UTC' ? 'selected' : '' }}>UTC</option>
                            <option value="America/New_York" {{ $currentValue == 'America/New_York' ? 'selected' : '' }}>America/New York</option>
                            <option value="America/Los_Angeles" {{ $currentValue == 'America/Los_Angeles' ? 'selected' : '' }}>America/Los Angeles</option>
                            <option value="Europe/London" {{ $currentValue == 'Europe/London' ? 'selected' : '' }}>Europe/London</option>
                            <option value="Asia/Tokyo" {{ $currentValue == 'Asia/Tokyo' ? 'selected' : '' }}>Asia/Tokyo</option>
                        </select>
                        @elseif($key == 'system_currency')
                        <select name="settings[{{ $key }}]" id="{{ $key }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent">
                            <option value="USD" {{ $currentValue == 'USD' ? 'selected' : '' }}>USD ($)</option>
                            <option value="EUR" {{ $currentValue == 'EUR' ? 'selected' : '' }}>EUR (€)</option>
                            <option value="GBP" {{ $currentValue == 'GBP' ? 'selected' : '' }}>GBP (£)</option>
                            <option value="JPY" {{ $currentValue == 'JPY' ? 'selected' : '' }}>JPY (¥)</option>
                        </select>
                        @else
                            <input type="text" name="settings[{{ $key }}]" id="{{ $key }}" value="{{ $currentValue }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-transparent"
                                placeholder="{{ __('messages.enter') }} {{ $label }}">
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        <!-- Save Button -->
        <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                </svg>
                {{ __('messages.save_all_settings') }}
            </button>
        </div>
    </form>
    </div>
</div>

<!-- Reset Confirmation Form -->
<form id="resetForm" method="POST" action="{{ route('admin.settings.reset') }}" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function confirmReset() {
    if (confirm('{{ __('messages.reset_confirm') }}')) {
        document.getElementById('resetForm').submit();
    }
}
</script>
@endsection
