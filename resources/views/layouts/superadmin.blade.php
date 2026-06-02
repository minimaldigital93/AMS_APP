<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Platform') }} · AMS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important;}</style>
    @include('partials.pwa-head')
</head>
<body class="min-h-screen bg-gray-100 text-gray-900">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="hidden w-64 shrink-0 flex-col bg-gray-900 text-gray-100 md:flex">
            <div class="px-6 py-5 text-lg font-bold tracking-tight">AMS <span class="text-indigo-400">Platform</span></div>
            <nav class="flex-1 space-y-1 px-3">
                @php($nav = [
                    'superadmin.dashboard' => ['Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    'superadmin.accounts.index' => ['Accounts', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8z'],
                    'superadmin.subscriptions.index' => ['Subscriptions', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    'superadmin.plans.index' => ['Plans', 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
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
                <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-300 hover:bg-gray-800 hover:text-white">{{ __('Switch to property view') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="mt-1 w-full rounded-lg px-3 py-2 text-left text-sm text-gray-300 hover:bg-gray-800 hover:text-white">{{ __('Log out') }}</button>
                </form>
            </div>
        </aside>

        <!-- Main -->
        <main class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
            @php($flashStyles = [
                'success' => 'border-green-300 bg-green-50 text-green-800',
                'error' => 'border-red-300 bg-red-50 text-red-800',
                'warning' => 'border-yellow-300 bg-yellow-50 text-yellow-800',
            ])
            @foreach ($flashStyles as $flash => $classes)
                @if (session($flash))
                    <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($flash) }}</div>
                @endif
            @endforeach
            @yield('content')
        </main>
    </div>
</body>
</html>
