<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Multi-tenant isolation for an owned model.
 *
 * Adds a global scope that constrains every query to the current account
 * (current_account_id()) and a `creating` hook that stamps account_id on new
 * rows. When there is no authenticated user (login, signup, console, seeders),
 * current_account_id() returns null and the scope is a no-op — so global lookups
 * still work.
 *
 * Rows with a NULL account_id are treated as unowned/legacy and stay visible to
 * everyone — this keeps pre-multitenancy fixtures/data working. Real customer
 * data always carries a non-null account_id (stamped on create + backfilled),
 * so it never leaks across accounts.
 *
 * The superadmin platform panel reads across accounts: use
 * `Model::withoutAccountScope()` (or `withoutGlobalScope('account')`) there.
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $builder) {
            $accountId = current_account_id();

            if ($accountId !== null) {
                $column = $builder->getModel()->getTable().'.account_id';
                $builder->where(function ($q) use ($column, $accountId) {
                    $q->where($column, $accountId)->orWhereNull($column);
                });
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('account_id') === null) {
                $model->setAttribute('account_id', current_account_id());
            }
        });
    }

    /**
     * Drop the per-account constraint — for the superadmin platform panel and
     * cross-account reporting.
     */
    public function scopeWithoutAccountScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('account');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }
}
