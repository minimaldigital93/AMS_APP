<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Settings extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : null;
        });

        return $value ?? $default;
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, mixed $value): static
    {
        Cache::forget("setting.{$key}");

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
