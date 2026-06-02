<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak]{display:none !important;}</style>

        @include('partials.pwa-head')
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <!-- 3D Animated Background -->
        <div class="bg-3d-container">
            <!-- Floating Shapes -->
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            
            <!-- 3D Cubes -->
            <div class="cube-container">
                <div class="cube">
                    <div class="cube-face front"></div>
                    <div class="cube-face back"></div>
                    <div class="cube-face right"></div>
                    <div class="cube-face left"></div>
                    <div class="cube-face top"></div>
                    <div class="cube-face bottom"></div>
                </div>
            </div>
            <div class="cube-container">
                <div class="cube">
                    <div class="cube-face front"></div>
                    <div class="cube-face back"></div>
                    <div class="cube-face right"></div>
                    <div class="cube-face left"></div>
                    <div class="cube-face top"></div>
                    <div class="cube-face bottom"></div>
                </div>
            </div>
            
            <!-- Particles -->
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            
            <!-- Glowing Rings -->
            <div class="glow-ring"></div>
            <div class="glow-ring"></div>
            <div class="glow-ring"></div>
        </div>

        <div class="login-wrapper min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-white drop-shadow-lg" />
                </a>
            </div>

            <div class="login-card w-full sm:max-w-md mt-6 px-6 py-4 bg-white/10 shadow-2xl overflow-hidden sm:rounded-2xl">
                {{ $slot }}
            </div>
        </div>

        {{-- Overlays (subscribe button, pricing modal, etc.) — rendered outside the
             login-card so backdrop-filter doesn't trap fixed positioning. --}}
        @stack('overlays')

        {{-- iOS home-screen PWAs relaunch from a page snapshot (bfcache) rather than
             a fresh request. That snapshot carries a stale CSRF token while the
             session has since rotated/expired, causing a 419 on login submit.
             Force a fresh load whenever the page is restored from cache. --}}
        <script>
            window.addEventListener('pageshow', function (event) {
                var navType = (performance.getEntriesByType('navigation')[0] || {}).type;
                if (event.persisted || navType === 'back_forward') {
                    window.location.reload();
                }
            });
        </script>
    </body>
</html>
