{{--
    General: language and theme. Neither posts to the settings form — the
    language select submits to the global language.switch route and the theme
    has its own picker page — which is why this page carries no Save button.
--}}
@extends('layouts.admin')

@section('title', __('messages.general_settings'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.general_settings') }}</h1>
            <a href="{{ route('admin.settings.index') }}" class="ml-auto flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-600 transition" title="{{ __('messages.back') }}" aria-label="{{ __('messages.back') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </a>
        </div>

        @php
            // bg-none removes the forms-plugin "v" arrow; the iOS chevron is drawn separately
            $selectClasses = 'appearance-none bg-none bg-transparent border-0 p-0 pr-5 text-right text-[15px] text-gray-500 focus:text-gray-900 focus:ring-0 focus:outline-none cursor-pointer';
            $chevronIcon = 'M8 9l4-4 4 4m0 6l-4 4-4-4';
        @endphp

        <div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
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

    </div>
</div>
@endsection
