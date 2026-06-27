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

if (! function_exists('exchange_rate')) {
    /**
     * The number of Khmer Riel per 1 USD, from the `khr_exchange_rate` setting.
     *
     * USD is the base currency every amount is stored in. When the account's
     * `system_currency` is KHR, amounts are multiplied by this rate for display
     * and divided by it to convert riel input back to the stored USD base.
     *
     * Falls back to a sane default and never returns <= 0 (a zero/negative rate
     * would blow up conversions), so callers can divide by it safely.
     */
    function exchange_rate(): float
    {
        $rate = (float) settings('khr_exchange_rate', 4100);

        return $rate > 0 ? $rate : 4100;
    }
}

if (! function_exists('currency_is_khr')) {
    /**
     * Whether the current account displays amounts in Khmer Riel.
     */
    function currency_is_khr(): bool
    {
        return settings('system_currency', 'USD') === 'KHR';
    }
}

if (! function_exists('to_display_amount')) {
    /**
     * Convert a stored USD (base-currency) amount into the active display
     * currency as a raw number (no symbol, no formatting). Use for charts,
     * JS data, and pre-filled input values. USD passes through unchanged.
     */
    function to_display_amount(float|int|string|null $usd): float
    {
        $value = (float) ($usd ?? 0);

        return currency_is_khr() ? $value * exchange_rate() : $value;
    }
}

if (! function_exists('to_base_amount')) {
    /**
     * Convert an amount entered in the active display currency back into the
     * stored USD base currency. Use in controllers before persisting any money
     * the user typed. USD input passes through unchanged.
     */
    function to_base_amount(float|int|string|null $amount): float
    {
        $value = (float) ($amount ?? 0);

        return currency_is_khr() ? $value / exchange_rate() : $value;
    }
}

if (! function_exists('convert_money_input')) {
    /**
     * Convert the given money fields of a validated/input array from the active
     * display currency back into the stored USD base currency. No-op when the
     * account is in USD. Field paths support `*` wildcards for nested arrays
     * (e.g. 'apartments.*.amount', 'bills.*.expenses.*.amount').
     *
     * Only money fields should be listed here — never meter readings or counts.
     */
    function convert_money_input(array $data, array $keys): array
    {
        if (! currency_is_khr()) {
            return $data;
        }

        $apply = function (&$node, array $segments) use (&$apply) {
            $seg = array_shift($segments);

            if ($seg === '*') {
                if (is_array($node)) {
                    foreach ($node as &$child) {
                        if ($segments === []) {
                            if (is_numeric($child)) {
                                $child = to_base_amount($child);
                            }
                        } else {
                            $apply($child, $segments);
                        }
                    }
                }

                return;
            }

            if (! is_array($node) || ! array_key_exists($seg, $node)) {
                return;
            }

            if ($segments === []) {
                if (is_numeric($node[$seg])) {
                    $node[$seg] = to_base_amount($node[$seg]);
                }
            } else {
                $apply($node[$seg], $segments);
            }
        };

        foreach ($keys as $path) {
            $apply($data, explode('.', $path));
        }

        return $data;
    }
}

if (! function_exists('money_number')) {
    /**
     * Format a stored USD amount in the active display currency WITHOUT the
     * currency symbol (converted + thousand-separated). KHR is shown as whole
     * riel by default; USD keeps 2 decimals. Use where the markup supplies its
     * own symbol/sign, or for input value="" attributes.
     */
    function money_number(float|int|string|null $usd, ?int $decimals = null): string
    {
        $decimals ??= currency_is_khr() ? 0 : 2;

        return number_format(to_display_amount($usd), $decimals);
    }
}

if (! function_exists('money_input')) {
    /**
     * A stored USD amount converted into the active display currency for use as
     * an <input type="number"> value: no currency symbol and NO thousand
     * separators (those are invalid in a number input). KHR is rounded to whole
     * riel; USD keeps 2 decimals. The submitted value is converted back to USD
     * by the controllers via convert_money_input().
     */
    function money_input(float|int|string|null $usd): string
    {
        $decimals = currency_is_khr() ? 0 : 2;

        return number_format(to_display_amount($usd), $decimals, '.', '');
    }
}

if (! function_exists('money')) {
    /**
     * Format a stored USD amount for display in the active currency: converts to
     * KHR when selected, prefixes the currency symbol, and formats (whole riel
     * for KHR, 2 decimals for USD). Drop-in replacement for the old
     * `currency_symbol() . number_format($x, 2)` pattern.
     */
    function money(float|int|string|null $usd, ?int $decimals = null): string
    {
        return currency_symbol().money_number($usd, $decimals);
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

if (! function_exists('current_property_id')) {
    /**
     * The id of the property the current request is scoped to (the global
     * "active property" chosen in the top-bar selector), or null when the user
     * has no accessible property.
     *
     * The FiltersByProperty model scope and the dashboard services key off this
     * to show one building's data at a time. Like current_account_id(), it
     * returns null when there is no active property so unscoped/global lookups
     * (and the account-wide null-property convention) keep working.
     */
    function current_property_id(): ?int
    {
        return app(\App\Services\Property\PropertyContext::class)->activePropertyId();
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
