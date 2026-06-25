<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * Seeds the five premium AMS themes.
 *
 * Each theme is a complete design-token map. Tokens are intentionally
 * monochrome/grayscale (luxury SaaS aesthetic — Apple / Linear / Vercel /
 * Stripe / Mercedes), with the only color appearing in the semantic
 * success / warning / danger states. Re-runnable: themes are upserted by slug.
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
                sortOrder: 30,
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
                sortOrder: 40,
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
                'background' => $background,
                'sidebar' => $sidebar,
                'card' => $card,
                'primary' => $textPrimary,
                'accent' => $textSecondary,
            ],
            'tokens' => [
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
            ],
        ];
    }
}
