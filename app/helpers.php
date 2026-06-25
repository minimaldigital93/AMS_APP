<?php

/**
 * Settings Helper Functions
 *
 * These functions provide easy access to system settings throughout the application.
 */
if (! function_exists('settings')) {
    /**
     * Get or set a setting value
     *
     * @param  string|array|null  $key
     * @param  mixed  $default
     * @return mixed|\App\Models\Settings
     */
    function settings($key = null, $default = null)
    {
        if (is_null($key)) {
            return app(\App\Models\Settings::class);
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                \App\Models\Settings::set($k, $v);
            }

            return true;
        }

        return \App\Models\Settings::get($key, $default);
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * The currency symbol for the current account, from the `system_currency`
     * setting. Only two currencies are supported: USD ($) and KHR (៛).
     * Falls back to '$' for any unset/unknown value.
     */
    function currency_symbol(): string
    {
        return match (settings('system_currency', 'USD')) {
            'KHR' => '៛',
            default => '$',
        };
    }
}

if (! function_exists('status_label')) {
    /**
     * Translate a model status value (e.g. 'occupied', 'qr_generated') into a
     * localized, human-readable label for the current locale.
     *
     * Looks the value up under the `messages.status_labels.*` group so it follows
     * the active locale. Unknown values fall back to a humanized version of the
     * raw value (underscores → spaces, first letter capitalized) so nothing ever
     * renders blank. This is the drop-in replacement for `ucfirst($x->status)`.
     */
    function status_label(?string $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        $key = strtolower($status);
        $translation = __('messages.status_labels.'.$key);

        // __() returns the key path unchanged when no translation exists.
        if ($translation === 'messages.status_labels.'.$key) {
            return ucfirst(str_replace('_', ' ', $status));
        }

        return $translation;
    }
}

if (! function_exists('current_account_id')) {
    /**
     * The id of the account (owning admin user) the current request acts within.
     *
     * - admin/superadmin → their own user id
     * - supervisor/tenant → the admin they belong to (users.account_id)
     * - unauthenticated (login, signup, console/seeders) → null (no scoping)
     *
     * The BelongsToAccount global scope keys off this to isolate each customer's
     * data. Returning null when there is no authenticated user is deliberate so
     * the login lookup, registration, and seeders are never accidentally scoped.
     */
    function current_account_id(): ?int
    {
        $user = \Illuminate\Support\Facades\Auth::user();

        if ($user === null) {
            return null;
        }

        return $user->account_id ?? $user->getKey();
    }
}

if (! function_exists('theme_service')) {
    /**
     * The shared ThemeService instance (resolved from the container).
     */
    function theme_service(): \App\Services\Theme\ThemeService
    {
        return app(\App\Services\Theme\ThemeService::class);
    }
}

if (! function_exists('active_theme_slug')) {
    /**
     * The slug of the theme active for the current request. Drop straight into
     * a layout's <html> tag: <html data-theme="{{ active_theme_slug() }}">.
     */
    function active_theme_slug(): string
    {
        return theme_service()->currentSlug();
    }
}
