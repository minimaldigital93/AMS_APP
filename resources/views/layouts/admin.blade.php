<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'AMS Admin'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 text-gray-900">
    <div class="min-h-screen flex flex-col">
        {{-- Topbar --}}
        <header class="bg-white border-b border-gray-200 shadow-sm">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <button type="button" class="lg:hidden text-gray-600 hover:text-gray-900" x-data="{}" @click="$dispatch('toggle-sidebar')">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-900">
                        {{ config('app.name', 'AMS') }}
                    </a>
                </div>

                <div class="flex items-center gap-4">
                    @auth
                        <div class="text-sm text-gray-700 hidden sm:block">
                            {{ auth()->user()->name ?? 'User' }}
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                Logout
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </header>

        <div class="flex flex-1" x-data="{ mobileSidebarOpen: false, desktopSidebarOpen: true }" @toggle-sidebar.window="if (window.innerWidth < 1024) { mobileSidebarOpen = !mobileSidebarOpen } else { desktopSidebarOpen = !desktopSidebarOpen }">
            {{-- Sidebar (mobile overlay) --}}
            <div class="fixed inset-0 z-30 flex lg:hidden" x-show="mobileSidebarOpen" x-cloak>
                <div class="fixed inset-0 bg-black bg-opacity-25" @click="mobileSidebarOpen = false"></div>
                <div class="relative flex w-64 flex-col bg-white shadow-xl">
                    <div class="flex items-center justify-between h-16 px-4 border-b border-gray-200">
                        <span class="font-semibold text-gray-900">Menu</span>
                        <button type="button" class="text-gray-500 hover:text-gray-700" @click="mobileSidebarOpen = false">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    <nav class="flex-1 overflow-y-auto py-4">
                        @include('layouts.sidebar')
                    </nav>
                </div>
            </div>

            {{-- Sidebar (desktop) --}}
            <aside class="hidden lg:flex lg:w-64 lg:flex-col bg-white border-r border-gray-200" x-show="desktopSidebarOpen" x-transition>
                <div class="flex-1 overflow-y-auto py-4">
                    @include('layouts.sidebar')
                </div>
            </aside>

            {{-- Main content --}}
            <main class="flex-1">
                <div class="mx-auto max-w-7xl py-6 px-4 sm:px-6 lg:px-8">
                    @yield('breadcrumbs')

                    @if(session('status'))
                        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800 border border-green-200">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>