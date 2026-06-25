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
        foreach ($this->themes() as $theme) {
            Theme::updateOrCreate(['slug' => $theme['slug']], $theme);
        }

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
                slug: 'executive-black',
                name: 'Executive Black',
                description: 'Ultra-premium matte black. Mercedes dashboard meets Apple Pro.',
                mode: 'dark',
                background: '#0F1115',
                sidebar: '#161A20',
                topbar: '#161A20',
                card: '#1D232B',
                textPrimary: '#E5E7EB',
                textSecondary: '#A3A3A3',
                accent: '#E5E7EB',
                accentContrast: '#0F1115',
                border: 'rgba(255,255,255,0.06)',
                hover: 'rgba(255,255,255,0.04)',
                active: 'rgba(255,255,255,0.08)',
                ring: 'rgba(229,231,235,0.25)',
                shadow: '0 10px 30px rgba(0,0,0,0.45)',
                sortOrder: 10,
            ),
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
                sortOrder: 20,
            ),
            $this->theme(
                slug: 'midnight-slate',
                name: 'Midnight Slate',
                description: 'Modern startup dark theme — Vercel, Linear Dark & Raycast.',
                mode: 'dark',
                background: '#0B1220',
                sidebar: '#121A29',
                topbar: '#121A29',
                card: '#182233',
                textPrimary: '#FFFFFF',
                textSecondary: '#94A3B8',
                accent: '#FFFFFF',
                accentContrast: '#0B1220',
                border: 'rgba(148,163,184,0.14)',
                hover: 'rgba(148,163,184,0.08)',
                active: 'rgba(148,163,184,0.16)',
                ring: 'rgba(148,163,184,0.30)',
                shadow: '0 12px 34px rgba(0,0,0,0.50)',
                sortOrder: 30,
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
                sortOrder: 40,
            ),
            $this->theme(
                slug: 'obsidian-black',
                name: 'Obsidian Black',
                description: 'Ultra-dark cinema UI. Lamborghini-grade luxury operating system.',
                mode: 'dark',
                background: '#050505',
                sidebar: '#0D0D0D',
                topbar: '#0D0D0D',
                card: '#151515',
                textPrimary: '#FFFFFF',
                textSecondary: '#8A8A8A',
                accent: '#FFFFFF',
                accentContrast: '#050505',
                border: 'rgba(255,255,255,0.07)',
                hover: 'rgba(255,255,255,0.04)',
                active: 'rgba(255,255,255,0.09)',
                ring: 'rgba(255,255,255,0.18)',
                shadow: '0 12px 34px rgba(0,0,0,0.65)',
                sortOrder: 50,
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
