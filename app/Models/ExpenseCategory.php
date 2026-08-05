<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The account's expense category vocabulary — the dropdown on the record-expense
 * page and the label printed beside every booked expense.
 *
 * Two columns carry the whole design:
 *
 * - `key` is the stable identifier stored in `business_expenses.category`. It is
 *   assigned once, from the name, and never changes: the income statement maps
 *   specific keys (`electricity`, `water`, `internet`, `security`, `tax`,
 *   `property_tax`) onto their own lines, and every other key falls into "Other
 *   Expenses". Renaming a category must not restate booked history, so the name
 *   is editable and the key is not.
 * - `is_active` hides a category from the dropdown without erasing it from the
 *   expenses that already reference it — that is what "retire a category" means
 *   here, and it is why deleting an in-use category is refused outright.
 *
 * The defaults are seeded lazily (see ensureDefaults) rather than by a data
 * migration, so accounts created after this shipped get them too.
 */
class ExpenseCategory extends Model
{
    use BelongsToAccount;

    /**
     * Starter vocabulary, seeded per account on first use. The first six keys
     * are the ones `incomeStatement()` maps onto their own statement lines —
     * renaming them here would silently move money into "Other Expenses".
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'electricity' => 'Electricity',
        'water' => 'Water',
        'internet' => 'Internet',
        'security' => 'Security',
        'tax' => 'Tax',
        'property_tax' => 'Property Tax',
        'trash' => 'Trash',
        'maintenance' => 'Maintenance & Repairs',
        'salaries' => 'Salaries & Wages',
        'legal' => 'Legal & Professional Fees',
        'insurance' => 'Insurance',
        'loan_payment' => 'Loan Payment',
        'other' => 'Other',
    ];

    protected $fillable = [
        'account_id',
        'key',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Request-local label map, keyed by account so a queue worker processing two
     * accounts never prints one's labels against the other's expenses.
     *
     * @var array<int|string, array<string, string>>
     */
    protected static array $labelMemo = [];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Display order: the owner's ordering first, then alphabetical.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Seed the starter vocabulary for the current account the first time it is
     * needed. An account with no categories at all cannot record an expense —
     * the dropdown would be empty — so an empty list always refills, which also
     * covers accounts that predate this table.
     */
    public static function ensureDefaults(): void
    {
        if (current_account_id() === null) {
            return;
        }

        if (static::query()->exists()) {
            return;
        }

        $sort = 0;
        foreach (self::DEFAULTS as $key => $name) {
            static::create([
                'key' => $key,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sort += 10,
            ]);
        }

        static::flushLabelMemo();
    }

    /**
     * Selectable categories for the expense form: key => name.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return static::query()->active()->ordered()->pluck('name', 'key')->all();
    }

    /**
     * Every category incl. retired ones, for labelling booked expenses.
     *
     * @return array<string, string>
     */
    public static function labelMap(): array
    {
        $accountId = current_account_id() ?? 'null';

        return static::$labelMemo[$accountId]
            ??= static::query()->pluck('name', 'key')->all();
    }

    /**
     * Human label for a stored category key. Falls back to the humanized key so
     * expenses booked under a category that was later deleted — or under the
     * hard-coded "other expense" vocabulary — still read correctly.
     */
    public static function labelFor(?string $key): string
    {
        if ($key === null || $key === '') {
            return '';
        }

        return static::labelMap()[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function flushLabelMemo(): void
    {
        static::$labelMemo = [];
    }

    /**
     * Derive a URL/DB-safe key from a name, unique within the account.
     * Keys are assigned once at creation and never change — see the class docs.
     */
    public static function makeKey(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'category';
        $base = Str::limit($base, 60, '');

        $key = $base;
        $i = 2;
        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }

    /**
     * Is this category already stamped on booked money? Such a row may be
     * deactivated but never deleted — the expense stores the key as a string,
     * so deleting it would leave the booked row with no label. Both sides are
     * checked: the business expense and the ledger row `recordOtherExpense()`
     * writes, whose hard-coded vocabulary overlaps these keys.
     */
    public function isInUse(): bool
    {
        return $this->usageCount() > 0;
    }

    /**
     * How many booked rows reference this category. Same two sources as
     * isInUse(); the settings page prints it in the "can't delete this" dialog
     * so the owner can see what is holding the category open.
     */
    public function usageCount(): int
    {
        return BusinessExpense::query()->where('category', $this->key)->count()
            + Accounts::expense()->where('category', $this->key)->count();
    }

    /**
     * Booked-row counts for the whole account vocabulary, key => count. Two
     * aggregates for the settings list instead of two queries per category.
     *
     * @return array<string, int>
     */
    public static function usageByKey(): array
    {
        $counts = [];

        // Eloquent builders, so both the account scope and the expense-type
        // scope still apply — a sibling account's bookings must never hold this
        // account's category open.
        foreach ([BusinessExpense::query(), Accounts::expense()] as $query) {
            $tallies = $query->selectRaw('category, COUNT(*) as aggregate')
                ->groupBy('category')
                ->pluck('aggregate', 'category');

            foreach ($tallies as $key => $count) {
                $counts[$key] = ($counts[$key] ?? 0) + (int) $count;
            }
        }

        return $counts;
    }
}
