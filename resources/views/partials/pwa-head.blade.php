{{-- Progressive Web App: makes AMS installable on phone/tablet/desktop --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#3b82f6">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS / iPadOS home-screen support --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="AMS">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' })
                .catch(function (err) {
                    console.error('Service worker registration failed:', err);
                });
        });
    }
</script>
