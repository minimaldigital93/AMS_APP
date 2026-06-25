<?php

namespace App\Services\Theme;

use App\Models\Theme;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;

/**
 * Resolves the active UI theme for the current request and renders the
 * design-token CSS that drives the whole interface.
 *
 * Single source of truth = the `themes` table. The token CSS for every theme
 * is emitted once into the page <head>; switching themes is then just a matter
 * of changing the `data-theme` attribute on <html> — no extra request, no FOUC.
 */
class ThemeService
{
    /** Cookie that mirrors the chosen theme so guests/login keep the look. */
    public const COOKIE = 'ams_theme';

    /** @return Collection<int, Theme> */
    public function catalog(): Collection
    {
        return Theme::catalog();
    }

    public function default(): ?Theme
    {
        return Theme::resolve(Theme::DEFAULT_SLUG);
    }

    /**
     * The slug active for this request:
     *   authenticated  → users.theme (validated against the catalog)
     *   guest          → the ams_theme cookie (last used on this device)
     *   fallback       → the platform default
     */
    public function currentSlug(): string
    {
        $candidate = Auth::check()
            ? Auth::user()->theme
            : Request::cookie(self::COOKIE);

        return optional(Theme::resolve($candidate))->slug ?? Theme::DEFAULT_SLUG;
    }

    public function current(): ?Theme
    {
        return Theme::resolve($this->currentSlug());
    }

    /**
     * Persist a user's theme choice. Returns the resolved theme (so callers can
     * also queue the mirror cookie). Invalid slugs fall back to the default.
     */
    public function setForUser(User $user, ?string $slug): Theme
    {
        $theme = Theme::resolve($slug) ?? $this->default();

        $user->forceFill(['theme' => $theme->slug])->save();

        return $theme;
    }

    /**
     * A long-lived cookie mirroring the chosen theme so the login screen and
     * any guest page render in the same look the user last selected.
     */
    public function mirrorCookie(string $slug): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(self::COOKIE, $slug, 60 * 24 * 365);
    }

    /**
     * The full token stylesheet: a `:root` default plus one `[data-theme="…"]`
     * block per theme, built straight from the DB tokens. Cached because the
     * catalog rarely changes; busted by Theme model events.
     */
    public function tokensCss(): string
    {
        return Cache::remember('themes.tokens_css', 3600, function () {
            $themes = $this->catalog();

            if ($themes->isEmpty()) {
                return '';
            }

            $default = $themes->firstWhere('slug', Theme::DEFAULT_SLUG) ?? $themes->first();

            $css = ':root{'.$this->declarations($default->tokens).'}';

            foreach ($themes as $theme) {
                $css .= '[data-theme="'.$theme->slug.'"]{'
                    .$this->declarations($theme->tokens)
                    .'color-scheme:'.($theme->isDark() ? 'dark' : 'light').';}';
            }

            return $css;
        });
    }

    /** Turn a token map into `--key:value;` declarations. */
    private function declarations(array $tokens): string
    {
        $out = '';
        foreach ($tokens as $key => $value) {
            $out .= $key.':'.$value.';';
        }

        return $out;
    }
}
