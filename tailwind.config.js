import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            /*
             * Semantic, theme-token-backed colors. These map Tailwind utilities
             * (e.g. `bg-card`, `text-token`, `border-line`, `text-accent`)
             * onto the CSS variables defined by the active theme, so utility
             * classes stay theme-aware. Names are intentionally distinct from
             * Tailwind's gray/slate/blue scales to avoid clobbering them.
             */
            colors: {
                app: 'var(--background)',
                surface: 'var(--card-bg)',
                sidebar: 'var(--sidebar-bg)',
                topbar: 'var(--topbar-bg)',
                card: 'var(--card-bg)',
                token: 'var(--text-primary)',
                muted: 'var(--text-secondary)',
                accent: {
                    DEFAULT: 'var(--accent-color)',
                    contrast: 'var(--accent-contrast)',
                },
                line: 'var(--border-color)',
                state: {
                    success: 'var(--success-color)',
                    warning: 'var(--warning-color)',
                    danger: 'var(--danger-color)',
                },
            },

            textColor: {
                token: 'var(--text-primary)',
                muted: 'var(--text-secondary)',
                accent: 'var(--accent-color)',
            },

            backgroundColor: {
                app: 'var(--background)',
                surface: 'var(--card-bg)',
                sidebar: 'var(--sidebar-bg)',
                topbar: 'var(--topbar-bg)',
                hover: 'var(--hover-bg)',
                active: 'var(--active-bg)',
            },

            borderColor: {
                line: 'var(--border-color)',
            },

            ringColor: {
                token: 'var(--ring-color)',
            },

            borderRadius: {
                token: 'var(--radius)',
                'token-sm': 'var(--radius-sm)',
                'token-lg': 'var(--radius-lg)',
            },

            boxShadow: {
                token: 'var(--shadow)',
                'token-sm': 'var(--shadow-sm)',
                'token-lg': 'var(--shadow-lg)',
            },

            transitionTimingFunction: {
                premium: 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
        },
    },

    plugins: [forms],
};
