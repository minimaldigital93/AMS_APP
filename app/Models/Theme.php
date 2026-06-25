<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A selectable UI theme (design-token preset).
 *
 * Platform reference data — intentionally NOT BelongsToAccount-scoped, the same
 * way {@see Subscription} is read across accounts. Every account sees the same
 * catalog; the per-user choice lives on `users.theme`.
 */
class Theme extends Model
{
    /** Slug of the theme used when a user has not picked one. */
    public const DEFAULT_SLUG = 'carbon-gray';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'mode',
        'tokens',
        'preview',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'preview' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isDark(): bool
    {
        return $this->mode === 'dark';
    }

    /**
     * All active themes in display order, cached (the catalog rarely changes).
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function catalog(): \Illuminate\Support\Collection
    {
        return Cache::remember('themes.catalog', 3600, function () {
            return static::active()->orderBy('sort_order')->orderBy('name')->get();
        });
    }

    /**
     * Resolve a slug to a theme, falling back to the default then the first
     * available theme. Never returns null when any theme exists.
     */
    public static function resolve(?string $slug): ?self
    {
        $catalog = static::catalog();

        return $catalog->firstWhere('slug', $slug)
            ?? $catalog->firstWhere('slug', self::DEFAULT_SLUG)
            ?? $catalog->first();
    }

    public static function clearCache(): void
    {
        Cache::forget('themes.catalog');
        Cache::forget('themes.tokens_css');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::clearCache());
        static::deleted(fn () => static::clearCache());
    }
}
