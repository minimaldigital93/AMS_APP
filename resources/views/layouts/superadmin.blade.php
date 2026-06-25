<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ active_theme_slug() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Platform') }} · AMS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-provider')
    <style>[x-cloak]{display:none !important;}</style>
    @include('partials.pwa-head')
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <!-- Mobile top bar (brand only — navigation lives in the bottom bar) -->
    <div class="sticky top-0 z-40 flex items-center gap-3 bg-gray-900 px-4 py-3 text-gray-100 md:hidden">
        <span class="text-base font-bold tracking-tight">AMS <span class="text-indigo-400">Platform</span></span>
    </div>

    <div class="flex min-h-screen">
        <!-- Sidebar (desktop only — phones use the bottom bar) -->
        <aside class="hidden w-64 shrink-0 flex-col bg-gray-900 text-gray-100 md:flex">
            <div class="flex items-center justify-between px-6 py-5 text-lg font-bold tracking-tight">
                <span>AMS <span class="text-indigo-400">Platform</span></span>
            </div>
            <nav class="flex-1 space-y-1 px-3">
                @php($nav = [
                    'superadmin.dashboard' => ['Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    'superadmin.accounts.index' => ['Accounts', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8z'],
                    'superadmin.finance.index' => ['Finance', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'superadmin.payments.index' => ['Payments', 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    'superadmin.plans.index' => ['Plans', 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    'superadmin.settings.payment' => ['Payment Settings', 'M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ])
                @foreach ($nav as $route => $meta)
                    <a href="{{ route($route) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)) ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta[1] }}"/></svg>
                        {{ __($meta[0]) }}
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-gray-800 p-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="mt-1 w-full rounded-lg px-3 py-2 text-left text-sm text-gray-300 hover:bg-gray-800 hover:text-white">{{ __('Log out') }}</button>
                </form>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
            @include('partials.flash')
            @yield('content')
            {{-- Spacer so content clears the mobile bottom navigation --}}
            <div class="h-20 md:hidden" aria-hidden="true"></div>
        </main>
    </div>

    <!-- Mobile bottom navigation -->
    @include('layouts.superadmin-bottom-nav')

    @include('partials.responsive-tables')
    @include('partials.confirm-modal')
</body>
</html>
