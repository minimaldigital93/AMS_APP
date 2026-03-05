<?php

/**
 * Settings Helper Functions
 * 
 * These functions provide easy access to system settings throughout the application.
 */

if (!function_exists('settings')) {
    /**
     * Get or set a setting value
     * 
     * @param string|array|null $key
     * @param mixed $default
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

if (!function_exists('setting')) {
    /**
     * Alias for settings()
     * 
     * @param string|array|null $key
     * @param mixed $default
     * @return mixed
     */
    function setting($key = null, $default = null)
    {
        return settings($key, $default);
    }
}
