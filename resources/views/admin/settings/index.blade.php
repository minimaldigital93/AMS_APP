@extends('layouts.admin')

@section('title', __('messages.settings_title'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.settings_title') }}</h1>
        </div>

        @php
            // Minimal iOS-style line icon (SVG path) per setting key
            $rowIcons = [
                'company_name'    => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                'company_address' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
                'company_phone'   => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
                'company_email'   => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                'system_currency' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            ];
            $defaultRowIcon = 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z';
            $categoryLabels = [
                'company' => __('messages.company_information'),
                'system'  => __('messages.system_preferences'),
            ];

            $inputClasses = 'flex-1 bg-transparent border-0 p-0 text-right text-[15px] text-gray-500 focus:text-gray-900 placeholder-gray-400 focus:ring-0 focus:outline-none';
            // bg-none removes the forms-plugin "v" arrow; the iOS chevron is drawn separately
            $selectClasses = 'appearance-none bg-none bg-transparent border-0 p-0 pr-5 text-right text-[15px] text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none cursor-pointer';
            $chevronIcon = 'M8 9l4-4 4 4m0 6l-4 4-4-4';
        @endphp

        <!-- Language Group -->
        <div>
            <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.language_settings') }}</p>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <form method="POST" action="{{ route('language.switch') }}" id="languageForm">
                    @csrf
                    <div class="flex items-center gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129" />
                        </svg>
                        <label for="language_select" class="text-[15px] text-gray-900">{{ __('messages.language') }}</label>
                        <span class="relative ml-auto inline-flex items-center">
                            <select name="locale" id="language_select" onchange="document.getElementById('languageForm').submit()" class="{{ $selectClasses }}">
                                <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>English</option>
                                <option value="km" {{ app()->getLocale() == 'km' ? 'selected' : '' }}>ភាសាខ្មែរ (Khmer)</option>
                            </select>
                            <svg class="pointer-events-none absolute right-0 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevronIcon }}" />
                            </svg>
                        </span>
                    </div>
                </form>
            </div>
            <p class="px-4 mt-2 text-[13px] text-gray-500">{{ __('messages.select_language') }}</p>
        </div>

        <!-- Appearance / Theme -->
        <div>
            <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.appearance') }}</p>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <a href="{{ route('admin.settings.theme') }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                    <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                    </svg>
                    <div class="min-w-0">
                        <p class="text-[15px] text-gray-900">{{ __('messages.theme_settings_title') }}</p>
                        <p class="text-[13px] text-gray-500">{{ theme_service()->current()?->name }}</p>
                    </div>
                    <svg class="ml-auto flex-shrink-0 w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>

        <!-- Settings Form -->
        <form method="POST" action="{{ route('admin.settings.updateBatch') }}" class="space-y-8" enctype="multipart/form-data" x-data="logoUploader()">
            @csrf
            @method('PUT')

            <!-- Company Logo -->
            @php $companyLogo = settings('company_logo'); @endphp
            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.company_logo') }}</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex items-center gap-4 px-4 py-4">
                        <!-- Preview -->
                        <div class="flex-shrink-0">
                            <div x-show="hasLogo" class="w-16 h-16 rounded-xl border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">
                                <img :src="previewUrl" alt="{{ __('messages.company_logo') }}" class="w-full h-full object-contain">
                            </div>
                            <div x-show="!hasLogo" class="w-16 h-16 rounded-xl border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-gray-300">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                        </div>
                        <!-- Controls -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="$refs.logoInput.click()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                    <span x-text="hasLogo ? '{{ __('messages.change_logo') }}' : '{{ __('messages.upload_logo') }}'"></span>
                                </button>
                                <button type="button" x-show="hasLogo" @click="removeLogo()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    {{ __('messages.remove_logo') }}
                                </button>
                            </div>
                            <p class="mt-2 text-[13px] text-gray-500">{{ __('messages.logo_hint') }}</p>
                        </div>
                    </div>
                    <input type="file" name="company_logo" x-ref="logoInput" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onSelect($event)">
                    <input type="hidden" name="remove_company_logo" :value="removeFlag ? '1' : '0'">
                </div>
            </div>

            @foreach($defaultSettings as $category => $categorySettings)
            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ $categoryLabels[$category] ?? ucfirst($category) }}</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                    @foreach($categorySettings as $key => $defaultValue)
                    @php
                        $currentValue = $settings->flatten()->firstWhere('key', $key)->value ?? $defaultValue;
                        $label = \Illuminate\Support\Facades\Lang::has('messages.' . $key)
                            ? __('messages.' . $key)
                            : ucwords(str_replace('_', ' ', substr($key, strlen($category) + 1)));
                        $icon = $rowIcons[$key] ?? $defaultRowIcon;
                    @endphp
                    <div class="flex {{ $key === 'company_address' ? 'items-start' : 'items-center' }} gap-3 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400 {{ $key === 'company_address' ? 'mt-0.5' : '' }}" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                        </svg>
                        <label for="{{ $key }}" class="text-[15px] text-gray-900 whitespace-nowrap {{ $key === 'company_address' ? 'pt-0.5' : '' }}">{{ $label }}</label>
                        @if($key === 'company_address')
                        <textarea name="settings[{{ $key }}]" id="{{ $key }}" rows="2"
                            class="{{ $inputClasses }} resize-none"
                            placeholder="{{ __('messages.enter') }} {{ $label }}">{{ $currentValue }}</textarea>
                        @elseif($key === 'system_currency')
                        <span class="relative ml-auto inline-flex items-center">
                            <select name="settings[{{ $key }}]" id="{{ $key }}" class="{{ $selectClasses }}">
                                <option value="USD" {{ $currentValue == 'USD' ? 'selected' : '' }}>USD ($)</option>
                                <option value="KHR" {{ $currentValue == 'KHR' ? 'selected' : '' }}>KHR (៛)</option>
                            </select>
                            <svg class="pointer-events-none absolute right-0 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $chevronIcon }}" />
                            </svg>
                        </span>
                        @else
                        <input type="text" name="settings[{{ $key }}]" id="{{ $key }}" value="{{ $currentValue }}"
                            class="{{ $inputClasses }}"
                            placeholder="{{ __('messages.enter') }} {{ $label }}">
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white font-semibold py-2.5 px-8 rounded-full transition duration-200 flex items-center gap-2 shadow-sm" title="{{ __('messages.save_all_settings') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                    </svg></button>
            </div>
        </form>

        <!-- Reset Group (destructive row, iOS style) -->
        <div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <button type="button" onclick="confirmReset()"
                    class="w-full px-4 py-3 text-center text-[15px] font-medium text-red-600 hover:bg-red-50 active:bg-red-100 transition duration-150">
                    {{ __('messages.reset_all') }}
                </button>
            </div>
            <p class="px-4 mt-2 text-[13px] text-gray-500">{{ __('messages.reset_confirm') }}</p>
        </div>

    </div>
</div>

<!-- Reset Confirmation Form -->
<form id="resetForm" method="POST" action="{{ route('admin.settings.reset') }}" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function confirmReset() {
    window.confirmAction({ message: '{{ __('messages.reset_confirm') }}' }).then(function (ok) {
        if (ok) document.getElementById('resetForm').submit();
    });
}

function logoUploader() {
    return {
        hasLogo: @json((bool) $companyLogo),
        previewUrl: '{{ $companyLogo ? asset('storage/' . $companyLogo) : '' }}',
        removeFlag: false,
        onSelect(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.removeFlag = false;
            this.previewUrl = URL.createObjectURL(file);
            this.hasLogo = true;
        },
        removeLogo() {
            this.removeFlag = true;
            this.hasLogo = false;
            this.previewUrl = '';
            this.$refs.logoInput.value = '';
        },
    };
}
</script>
@endsection
