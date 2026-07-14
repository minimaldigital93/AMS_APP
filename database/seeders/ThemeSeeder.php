<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * Seeds the premium AMS theme catalog.
 *
 * Each theme is a complete design-token map. The color tokens are intentionally
 * monochrome/grayscale (luxury SaaS aesthetic — Apple / Linear / Vercel /
 * Stripe / Mercedes), with the only color appearing in the semantic
 * success / warning / danger states.
 *
 * Themes 3-8 ("style" themes — skeuomorphism, neomorphism, glassmorphism,
 * minimal, brutalism, bento) additionally override *structural* tokens
 * (`--radius*`, `--shadow*`, `--transition`) and optional sidebar tokens via the
 * `$extra` map, so the same `.ams-*` component library renders in a completely
 * different physical style without any extra markup. Tokens not present in a
 * theme fall back to the CSS defaults in theme.css, so the original themes are
 * untouched. Re-runnable: themes are upserted by slug.
 */
class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        $themes = $this->themes();

        foreach ($themes as $theme) {
            Theme::updateOrCreate(['slug' => $theme['slug']], $theme);
        }

        // Prune themes that are no longer offered (e.g. the retired dark themes).
        // Users still pointing at a removed slug fall back to the default at
        // resolve()-time, so no user record needs touching here.
        Theme::whereNotIn('slug', array_column($themes, 'slug'))->delete();

        Theme::clearCache();
    }

    /**
     * Shared semantic state colors. Brightened a touch for dark themes so they
     * read well on near-black surfaces; deepened for light themes.
     */
    private function states(bool $dark): array
    {
        return $dark
            ? ['success' => '#34D399', 'warning' => '#FBBF24', 'danger' => '#F87171']
            : ['success' => '#059669', 'warning' => '#D97706', 'danger' => '#DC2626'];
    }

    private function themes(): array
    {
        return [
            $this->theme(
                slug: 'carbon-gray',
                name: 'Carbon Gray',
                description: 'Clean corporate light theme in the spirit of Notion, Stripe & Linear.',
                mode: 'light',
                background: '#F5F6F8',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#111827',
                textSecondary: '#6B7280',
                accent: '#111827',
                accentContrast: '#FFFFFF',
                border: 'rgba(17,24,39,0.08)',
                hover: 'rgba(17,24,39,0.04)',
                active: 'rgba(17,24,39,0.06)',
                ring: 'rgba(17,24,39,0.16)',
                shadow: '0 6px 24px rgba(17,24,39,0.08)',
                sortOrder: 10,
            ),
            $this->theme(
                slug: 'platinum-silver',
                name: 'Platinum Silver',
                description: 'Elegant bright luxury — Apple, Tesla & modern banking.',
                mode: 'light',
                background: '#F8F9FB',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#2B2B2B',
                textSecondary: '#8D8D8D',
                accent: '#2B2B2B',
                accentContrast: '#FFFFFF',
                border: 'rgba(43,43,43,0.08)',
                hover: 'rgba(43,43,43,0.04)',
                active: 'rgba(43,43,43,0.06)',
                ring: 'rgba(43,43,43,0.16)',
                shadow: '0 6px 24px rgba(43,43,43,0.07)',
                sortOrder: 20,
            ),
            $this->theme(
                slug: 'light-blue',
                name: 'Light Blue',
                description: 'Calm, airy light theme with a soft blue accent.',
                mode: 'light',
                background: '#EFF4FF',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#0F1B2D',
                textSecondary: '#5B6B82',
                accent: '#2563EB',
                accentContrast: '#FFFFFF',
                border: 'rgba(37,99,235,0.12)',
                hover: 'rgba(37,99,235,0.06)',
                active: 'rgba(37,99,235,0.10)',
                ring: 'rgba(37,99,235,0.25)',
                shadow: '0 6px 24px rgba(37,99,235,0.10)',
                sortOrder: 90,
            ),
            $this->theme(
                slug: 'light-green',
                name: 'Light Green',
                description: 'Fresh, natural light theme with a soft green accent.',
                mode: 'light',
                background: '#EFFBF3',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#0F241A',
                textSecondary: '#5C7468',
                accent: '#16A34A',
                accentContrast: '#FFFFFF',
                border: 'rgba(22,163,74,0.12)',
                hover: 'rgba(22,163,74,0.06)',
                active: 'rgba(22,163,74,0.10)',
                ring: 'rgba(22,163,74,0.25)',
                shadow: '0 6px 24px rgba(22,163,74,0.10)',
                sortOrder: 100,
            ),

            // ============================================================
            //  THEME 3 — SKEUOMORPHISM
            //  Luxury executive desktop software: brushed metal, raised
            //  controls, layered shadows, inner highlights, dark carbon
            //  sidebar + brushed-platinum top bar.
            // ============================================================
            $this->theme(
                slug: 'skeuomorphism',
                name: 'Skeuomorphism',
                description: 'Luxury executive hardware — brushed metal, raised controls and layered shadows.',
                mode: 'light',
                background: '#E6E7EB',
                sidebar: '#1F2024',
                topbar: '#ECEDF0',
                card: '#F3F4F6',
                textPrimary: '#20242B',
                textSecondary: '#6A7080',
                accent: '#2B2B2B',
                accentContrast: '#FFFFFF',
                border: 'rgba(20,24,33,0.16)',
                hover: 'rgba(20,24,33,0.05)',
                active: 'rgba(20,24,33,0.09)',
                ring: 'rgba(20,24,33,0.22)',
                shadow: '0 2px 4px rgba(20,24,33,0.14), 0 10px 26px rgba(20,24,33,0.16)',
                sortOrder: 30,
                extra: [
                    '--radius' => '18px',
                    '--radius-sm' => '12px',
                    '--radius-lg' => '22px',
                    '--shadow-sm' => '0 1px 2px rgba(20,24,33,0.20)',
                    '--shadow-lg' => '0 10px 22px rgba(20,24,33,0.22), 0 22px 48px rgba(20,24,33,0.20)',
                    // Dark carbon sidebar needs light text/dividers + a bright
                    // active indicator (the dark accent would vanish on carbon).
                    '--sidebar-text' => '#D7DAE0',
                    '--sidebar-text-muted' => '#9AA0AC',
                    '--sidebar-hover' => 'rgba(255,255,255,0.06)',
                    '--sidebar-active' => 'rgba(255,255,255,0.10)',
                    '--sidebar-border' => 'rgba(255,255,255,0.08)',
                    '--sidebar-accent' => '#CBD0D8',
                ],
            ),

            // ============================================================
            //  THEME 4 — NEOMORPHISM
            //  Soft monochrome UI: panels extrude from a single tinted
            //  surface using paired light/dark shadows; inputs are inset.
            // ============================================================
            $this->theme(
                slug: 'neomorphism',
                name: 'Neomorphism',
                description: 'Soft, tactile monochrome — panels extrude from one surface with paired shadows.',
                mode: 'light',
                background: '#E0E5EC',
                sidebar: '#E0E5EC',
                topbar: '#E0E5EC',
                card: '#E0E5EC',
                textPrimary: '#37404F',
                textSecondary: '#7E8AA0',
                accent: '#31384A',
                accentContrast: '#FFFFFF',
                border: 'rgba(55,64,79,0.06)',
                hover: 'rgba(55,64,79,0.04)',
                active: 'rgba(55,64,79,0.07)',
                ring: 'rgba(55,64,79,0.18)',
                shadow: '8px 8px 18px rgba(163,177,198,0.55), -8px -8px 18px rgba(255,255,255,0.90)',
                sortOrder: 40,
                extra: [
                    '--radius' => '20px',
                    '--radius-sm' => '14px',
                    '--radius-lg' => '26px',
                    '--shadow-sm' => '4px 4px 8px rgba(163,177,198,0.50), -4px -4px 8px rgba(255,255,255,0.85)',
                    '--shadow-lg' => '12px 12px 24px rgba(163,177,198,0.60), -12px -12px 24px rgba(255,255,255,0.95)',
                ],
            ),

            // ============================================================
            //  THEME 5 — GLASSMORPHISM
            //  Frosted translucent panels over an aurora gradient; thin
            //  white borders, backdrop blur, layered depth.
            // ============================================================
            $this->theme(
                slug: 'glassmorphism',
                name: 'Glassmorphism',
                description: 'Frosted glass panels over an aurora backdrop — blur, translucency and thin light borders.',
                mode: 'light',
                background: 'linear-gradient(135deg, #E7ECFF 0%, #F0E9FF 45%, #E7FbFF 100%)',
                sidebar: 'rgba(255,255,255,0.55)',
                topbar: 'rgba(255,255,255,0.50)',
                card: 'rgba(255,255,255,0.55)',
                textPrimary: '#1B2030',
                textSecondary: '#566072',
                accent: '#2B2B2B',
                accentContrast: '#FFFFFF',
                border: 'rgba(255,255,255,0.60)',
                hover: 'rgba(255,255,255,0.40)',
                active: 'rgba(255,255,255,0.58)',
                ring: 'rgba(43,43,43,0.18)',
                shadow: '0 8px 32px rgba(31,38,135,0.18)',
                sortOrder: 50,
                extra: [
                    '--radius' => '18px',
                    '--radius-sm' => '12px',
                    '--radius-lg' => '24px',
                    '--shadow-lg' => '0 16px 48px rgba(31,38,135,0.24)',
                    '--sidebar-border' => 'rgba(255,255,255,0.50)',
                    // Solid swatch for the picker thumbnail (token is a gradient).
                    '__preview_background' => '#EAEDFF',
                ],
            ),

            // ============================================================
            //  THEME 6 — MINIMAL
            //  Apple / Stripe / Linear / Notion: flat surfaces, hairline
            //  borders, generous whitespace, near-invisible shadows.
            // ============================================================
            $this->theme(
                slug: 'minimal',
                name: 'Minimal',
                description: 'Quiet and editorial — flat cards, hairline borders and generous whitespace.',
                mode: 'light',
                background: '#FFFFFF',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#18181B',
                textSecondary: '#71717A',
                accent: '#18181B',
                accentContrast: '#FFFFFF',
                border: 'rgba(0,0,0,0.08)',
                hover: 'rgba(0,0,0,0.03)',
                active: 'rgba(0,0,0,0.05)',
                ring: 'rgba(0,0,0,0.12)',
                shadow: '0 1px 2px rgba(0,0,0,0.04)',
                sortOrder: 60,
                extra: [
                    '--radius' => '10px',
                    '--radius-sm' => '8px',
                    '--radius-lg' => '14px',
                    '--shadow-sm' => '0 1px 1px rgba(0,0,0,0.03)',
                    '--shadow-lg' => '0 4px 16px rgba(0,0,0,0.07)',
                ],
            ),

            // ============================================================
            //  THEME 7 — BRUTALISM
            //  Square corners, heavy black outlines, hard offset shadows,
            //  high contrast, snappy transitions, no blur/gradients.
            // ============================================================
            $this->theme(
                slug: 'brutalism',
                name: 'Brutalism',
                description: 'Bold and structural — square corners, heavy black outlines and hard offset shadows.',
                mode: 'light',
                background: '#FAFAF4',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#0A0A0A',
                textSecondary: '#3A3A3A',
                accent: '#0A0A0A',
                accentContrast: '#FFFFFF',
                border: '#0A0A0A',
                hover: 'rgba(10,10,10,0.06)',
                active: 'rgba(10,10,10,0.10)',
                ring: 'rgba(10,10,10,0.85)',
                shadow: '4px 4px 0 0 #0A0A0A',
                sortOrder: 70,
                extra: [
                    '--radius' => '0px',
                    '--radius-sm' => '0px',
                    '--radius-lg' => '0px',
                    '--radius-pill' => '0px',
                    '--shadow-sm' => '2px 2px 0 0 #0A0A0A',
                    '--shadow-lg' => '8px 8px 0 0 #0A0A0A',
                    '--transition' => '120ms cubic-bezier(0.2, 0, 0, 1)',
                ],
            ),

            // ============================================================
            //  THEME 8 — BENTO
            //  Generous rounded "bento box" surfaces, soft tints and airy
            //  spacing — the skin behind the bento dashboard layout.
            // ============================================================
            // ============================================================
            //  THEME 9 — MIDNIGHT (dark mode)
            //  Modern dark dashboard (Linear / Vercel): near-black canvas,
            //  elevated graphite cards, bright indigo accent. The utility
            //  remaps that make hardcoded light Tailwind classes readable
            //  on dark surfaces live in theme.css under [data-theme=midnight].
            // ============================================================
            $this->theme(
                slug: 'midnight',
                name: 'Midnight',
                description: 'Dark mode — near-black canvas, graphite cards and a bright indigo accent.',
                mode: 'dark',
                background: '#0F1115',
                sidebar: '#15181E',
                topbar: '#15181E',
                card: '#1A1E26',
                textPrimary: '#E5E7EB',
                textSecondary: '#98A1B3',
                accent: '#818CF8',
                accentContrast: '#0F1115',
                border: 'rgba(255,255,255,0.09)',
                hover: 'rgba(255,255,255,0.05)',
                active: 'rgba(255,255,255,0.09)',
                ring: 'rgba(129,140,248,0.35)',
                shadow: '0 6px 24px rgba(0,0,0,0.45)',
                sortOrder: 85,
                extra: [
                    '--shadow-lg' => '0 18px 50px -12px rgba(0,0,0,0.65)',
                ],
            ),

            $this->theme(
                slug: 'bento',
                name: 'Bento Grid',
                description: 'Soft rounded bento surfaces with airy spacing — calm, modular and modern.',
                mode: 'light',
                background: '#EEF0F4',
                sidebar: '#FFFFFF',
                topbar: '#FFFFFF',
                card: '#FFFFFF',
                textPrimary: '#1A1D24',
                textSecondary: '#6B7280',
                accent: '#1A1D24',
                accentContrast: '#FFFFFF',
                border: 'rgba(26,29,36,0.07)',
                hover: 'rgba(26,29,36,0.04)',
                active: 'rgba(26,29,36,0.06)',
                ring: 'rgba(26,29,36,0.16)',
                shadow: '0 2px 10px rgba(26,29,36,0.06)',
                sortOrder: 80,
                extra: [
                    '--radius' => '24px',
                    '--radius-sm' => '16px',
                    '--radius-lg' => '30px',
                    '--shadow-lg' => '0 18px 50px -12px rgba(26,29,36,0.20)',
                ],
            ),
        ];
    }

    private function theme(
        string $slug,
        string $name,
        string $description,
        string $mode,
        string $background,
        string $sidebar,
        string $topbar,
        string $card,
        string $textPrimary,
        string $textSecondary,
        string $accent,
        string $accentContrast,
        string $border,
        string $hover,
        string $active,
        string $ring,
        string $shadow,
        int $sortOrder,
        array $extra = [],
    ): array {
        $states = $this->states($mode === 'dark');

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'mode' => $mode,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'preview' => [
                'background' => $extra['__preview_background'] ?? $background,
                'sidebar' => $sidebar,
                'card' => $card,
                'primary' => $textPrimary,
                'accent' => $textSecondary,
            ],
            // Color tokens first, then any structural / sidebar overrides from
            // $extra (radius, shadows, transition, --sidebar-* …). Keys prefixed
            // with "__" are seeder-only hints and never reach the token map.
            'tokens' => array_merge([
                '--background' => $background,
                '--sidebar-bg' => $sidebar,
                '--topbar-bg' => $topbar,
                '--card-bg' => $card,
                '--text-primary' => $textPrimary,
                '--text-secondary' => $textSecondary,
                '--accent-color' => $accent,
                '--accent-contrast' => $accentContrast,
                '--border-color' => $border,
                '--hover-bg' => $hover,
                '--active-bg' => $active,
                '--ring-color' => $ring,
                '--success-color' => $states['success'],
                '--warning-color' => $states['warning'],
                '--danger-color' => $states['danger'],
                '--shadow' => $shadow,
            ], array_filter(
                $extra,
                fn ($key) => ! str_starts_with($key, '__'),
                ARRAY_FILTER_USE_KEY,
            )),
        ];
    }
}
