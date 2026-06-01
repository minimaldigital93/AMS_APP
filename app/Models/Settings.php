<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Settings extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'key',
        'value',
    ];

    /**
     * Get a setting value by key, scoped to the current account.
     *
     * Cache key and query are both keyed by account so accounts never read each
     * other's values. Unauthenticated requests (login page, console) resolve a
     * null account and fall back to the provided default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $accountId = current_account_id();

        $value = Cache::remember("setting.{$accountId}.{$key}", 3600, function () use ($key, $accountId) {
            $setting = static::withoutGlobalScope('account')
                ->where('key', $key)
                ->where('account_id', $accountId)
                ->first();

            return $setting ? $setting->value : null;
        });

        return $value ?? $default;
    }

    /**
     * Set a setting value by key for the current account.
     */
    public static function set(string $key, mixed $value): static
    {
        $accountId = current_account_id();

        Cache::forget("setting.{$accountId}.{$key}");

        return static::withoutGlobalScope('account')->updateOrCreate(
            ['account_id' => $accountId, 'key' => $key],
            ['value' => $value]
        );
    }
}
