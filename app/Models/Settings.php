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
     * Request-local memo. Helpers like currency_symbol() call get() once per
     * table row, and the cache store is `database` — so without this, every
     * call is a DB round-trip (a cache HIT still queries the cache table, and
     * Cache::remember never stores null, so unset keys re-query every call).
     * Values here may be null (missing setting) — hence array_key_exists.
     */
    protected static array $memo = [];

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
        $memoKey = "{$accountId}.{$key}";

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey] ?? $default;
        }

        // The '__missing' sentinel makes null cacheable: Cache::remember treats
        // a null hit as absent and would re-run the query on every call.
        $value = Cache::remember("setting.{$accountId}.{$key}", 3600, function () use ($key, $accountId) {
            $setting = static::withoutGlobalScope('account')
                ->where('key', $key)
                ->where('account_id', $accountId)
                ->first();

            return $setting ? $setting->value : '__missing';
        });

        $value = $value === '__missing' ? null : $value;
        static::$memo[$memoKey] = $value;

        return $value ?? $default;
    }

    /**
     * Set a setting value by key for the current account.
     */
    public static function set(string $key, mixed $value): static
    {
        $accountId = current_account_id();

        static::forgetCached($key, $accountId);

        return static::withoutGlobalScope('account')->updateOrCreate(
            ['account_id' => $accountId, 'key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Drop a key from both cache layers. Any code path that writes/deletes
     * settings rows directly (not via set()) must call this.
     */
    public static function forgetCached(string $key, ?int $accountId = null): void
    {
        $accountId ??= current_account_id();

        Cache::forget("setting.{$accountId}.{$key}");
        unset(static::$memo["{$accountId}.{$key}"]);
    }

    /**
     * Clear the whole request memo (bulk deletes / tests).
     */
    public static function flushMemo(): void
    {
        static::$memo = [];
    }
}
