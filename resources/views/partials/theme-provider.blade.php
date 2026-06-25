{{--
    Theme Provider
    ──────────────
    Injects the design-token stylesheet for every theme into the page <head>,
    straight from the `themes` table (single source of truth). The active theme
    is selected by the `data-theme` attribute on the <html> tag — set this with
    `active_theme_slug()` in the layout, e.g.:

        <html data-theme="{{ active_theme_slug() }}">

    Because the server renders the authoritative `data-theme` inline AND ships
    every token set up-front, the correct theme paints on first frame (no FOUC),
    and the switcher can change themes instantly by rewriting that one attribute
    — no page reload, no extra request. Placed AFTER @vite so these tokens win
    over the app.css :root fallback.
--}}
<style id="ams-theme-tokens">{!! theme_service()->tokensCss() !!}</style>
