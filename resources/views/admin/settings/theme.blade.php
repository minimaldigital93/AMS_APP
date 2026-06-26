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

        {{-- Live preview strip: a tiny slice of real UI that re-skins as you preview --}}
        <x-theme.card :hover="false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="ams-title text-base">{{ __('messages.theme_live_preview') }}</h3>
                <x-theme.badge type="accent" icon="bolt">{{ __('messages.theme_instant') }}</x-theme.badge>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <x-theme.stat-card
                    :label="__('messages.theme_demo_revenue')"
                    value="$24,580"
                    icon="payments"
                    trend="+12.4%"
                    trendType="success"
                    :hint="__('messages.theme_demo_vs_last')" />
                <x-theme.tenant-card
                    name="Sophea Chan"
                    unit="Room 304 · Sunrise Tower"
                    status="occupied"
                    statusType="success" />
                <x-theme.invoice-card
                    number="INV-2048"
                    title="Sophea Chan"
                    amount="$320.00"
                    date="25 Jun 2026"
                    status="paid"
                    statusType="success" />
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-4">
                <button type="button" class="ams-btn ams-btn-primary">{{ __('messages.theme_demo_primary') }}</button>
                <button type="button" class="ams-btn ams-btn-ghost">{{ __('messages.theme_demo_secondary') }}</button>
                <x-theme.badge type="success" icon="check_circle">{{ __('messages.theme_demo_active') }}</x-theme.badge>
                <x-theme.badge type="warning">{{ __('messages.theme_demo_pending') }}</x-theme.badge>
                <x-theme.badge type="danger">{{ __('messages.theme_demo_overdue') }}</x-theme.badge>
            </div>
        </x-theme.card>

        {{-- The picker --}}
        <x-theme.switcher
            :themes="$themes"
            :active="$active"
            :update-url="route('admin.settings.theme.update')" />
    </div>
</div>
@endsection
