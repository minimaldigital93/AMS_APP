<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A platform-level operating expense recorded by the superadmin. The cost side
 * of the SaaS profit & loss (subscription payments are the revenue side).
 *
 * NOTE: intentionally NOT account-scoped (no BelongsToAccount). This is the
 * platform operator's own ledger, read only inside the superadmin panel.
 */
class PlatformExpense extends Model
{
    /** Recognised expense categories (label lookup in the UI). */
    public const CATEGORIES = [
        'hosting' => 'Hosting & Infrastructure',
        'salary' => 'Salaries & Payroll',
        'marketing' => 'Marketing & Ads',
        'software' => 'Software & Tools',
        'fees' => 'Payment & Bank Fees',
        'other' => 'Other',
    ];

    protected $fillable = [
        'category',
        'description',
        'amount',
        'currency',
        'spent_at',
        'is_recurring',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'date',
            'is_recurring' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}
