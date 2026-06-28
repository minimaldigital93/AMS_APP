{{-- Progressive Web App: makes AMS installable on phone/tablet/desktop --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#3b82f6">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS / iPadOS home-screen support --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="AMS">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

{{-- Standalone (installed) tweaks so AMS behaves like a native app, not a web page.
     These rules ONLY apply when launched from the home screen (display-mode: standalone),
     so they never affect the app inside a normal browser tab. --}}
<style>
    @media (display-mode: standalone) {
        html { -webkit-text-size-adjust: 100%; }

        body {
            /* Kill the rubber-band bounce that exposes the underlying webview */
            overscroll-behavior-y: none;
            /* Remove the grey flash when tapping buttons/links */
            -webkit-tap-highlight-color: transparent;
        }

        /* Let the dark top bar bleed up into the notch area (0 on phones with the
           opaque status bar, protective on devices/modes that draw under it). */
        #topbarNav {
            padding-top: calc(0.5rem + env(safe-area-inset-top));
        }

        /* Keep scrollable page content clear of the home-indicator bar */
        main.overflow-auto {
            padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));
        }

        /* The off-canvas drawer's footer (Logout) must clear the home indicator too */
        .sidebar-container .border-t {
            padding-bottom: calc(1rem + env(safe-area-inset-bottom));
        }
    }

    /* Stop iOS from zooming in when a form field is focused (fields under 16px).
       Scoped to phones in standalone so desktop/tablet styling is untouched. */
    @media (display-mode: standalone) and (max-width: 767px) {
        input:not([type="checkbox"]):not([type="radio"]),
        select,
        textarea { font-size: 16px; }
    }
</style>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            // No explicit scope: the default scope is the directory the script is
            // served from, so it adapts to the root deployment and the proxied
            // sub-path deployment alike without hardcoding any prefix.
            navigator.serviceWorker.register('{{ asset('sw.js') }}')
                .catch(function (err) {
                    console.error('Service worker registration failed:', err);
                });
        });
    }
</script>
