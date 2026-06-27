@extends('layouts.admin')

@section('title', __('messages.theme_settings_title'))

@section('content')
<div class="min-h-full py-8" style="background: var(--background)">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-8">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <span class="ams-stat-icon flex-shrink-0">
                    <span class="material-icons">palette</span>
                </span>
                <div class="min-w-0">
                    <h1 class="ams-title text-2xl sm:text-3xl">{{ __('messages.theme_settings_title') }}</h1>
                    <p class="ams-muted mt-1 text-sm max-w-xl">{{ __('messages.theme_settings_subtitle') }}</p>
                </div>
            </div>
            <a href="{{ route('admin.settings.index') }}"
               title="{{ __('messages.settings_title') }}"
               aria-label="{{ __('messages.settings_title') }}"
               class="inline-flex items-center justify-center p-2 rounded-token-sm border border-line text-muted hover:bg-hover hover:text-token transition flex-shrink-0">
                <span class="material-icons" style="font-size:1.4rem">arrow_back</span>
            </a>
        </div>

        {{-- The picker --}}
        <x-theme.switcher
            :themes="$themes"
            :active="$active"
            :update-url="route('admin.settings.theme.update')" />
    </div>
</div>
@endsection
