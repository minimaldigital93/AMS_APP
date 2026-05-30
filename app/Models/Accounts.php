<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accounts extends Model
{
    // ── Account Types ────────────────────────────────────────────
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    // ── Income Categories ────────────────────────────────────────
    public const CAT_RENT_INCOME = 'rent_income';

    public const CAT_UTILITY_INCOME = 'utility_income';

    public const CAT_DEPOSIT_INCOME = 'deposit_income';

    public const CAT_LATE_FEE_INCOME = 'late_fee_income';

    public const CAT_OTHER_INCOME = 'other_income';

    // ── Expense Categories ───────────────────────────────────────
    public const CAT_UTILITIES_EXPENSE = 'utilities_expense';

    public const CAT_BUSINESS_FIXED = 'business_fixed';

    public const CAT_BUSINESS_VARIABLE = 'business_variable';

    public const CAT_MAINTENANCE = 'maintenance';

    public const CAT_MAINTENANCE_EXPENSE = 'maintenance';

    public const CAT_INSURANCE = 'insurance';

    public const CAT_PROPERTY_TAX = 'property_tax';

    public const CAT_MANAGEMENT = 'management';

    public const CAT_OTHER_EXPENSE = 'other_expense';

    public const CAT_DEPOSIT_EXPENSE = 'deposit_expense';

    /**
     * Map a payment_type (from Payments table) to an income category.
     */
    public const PAYMENT_TYPE_TO_CATEGORY = [
        'rent' => self::CAT_RENT_INCOME,
        'utilities' => self::CAT_UTILITY_INCOME,
        'deposit' => self::CAT_DEPOSIT_INCOME,
        'late_fee' => self::CAT_LATE_FEE_INCOME,
        'other' => self::CAT_OTHER_INCOME,
    ];

    protected $fillable = [
        'fiscal_period_id',
        'payment_id',
        'user_id',
        'account_type',
        'category',
        'description',
        'amount',
        'transaction_date',
        'reference_number',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'transaction_date' => 'date',
        ];
    }

    // ── Relationships ────────────────────────────────────────────

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriods::class, 'fiscal_period_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payments::class, 'payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Query Scopes (reusable building blocks) ──────────────────

    public function scopeIncome($query)
    {
        return $query->where('account_type', self::TYPE_INCOME);
    }

    public function scopeExpense($query)
    {
        return $query->where('account_type', self::TYPE_EXPENSE);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('fiscal_period_id', $periodId);
    }

    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('transaction_date', [
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay(),
        ]);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
