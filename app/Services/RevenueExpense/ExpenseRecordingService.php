<?php

namespace App\Services\RevenueExpense;

use App\Models\Accounts;
use App\Models\ApartmentFixedExpense;
use App\Models\BusinessExpense;
use App\Models\FiscalPeriods;
use App\Models\Rentals;
use App\Models\Utilities;
use App\Services\Attachments\AttachmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Write-side service for expense recording — utility, other, business, and
 * apartment-fixed expenses. Shared by admin/supervisor controllers.
 *
 * Authorization (admin checks Accounts.user_id, supervisor checks
 * fiscal_period_id) stays in the controller; this service only mutates.
 */
class ExpenseRecordingService
{
    public function __construct(
        private int $userId,
        private ?FiscalPeriods $period,
        private ?int $propertyId = null,
        private ?AttachmentService $attachments = null,
    ) {
        $this->attachments ??= new AttachmentService;
    }

    /**
     * Per-apartment utility/maintenance expense. Writes both a Utilities row
     * (operational tracking) and an Accounts row (ledger entry).
     */
    public function recordUtilityExpense(Rentals $rental, array $data): Accounts
    {
        $transactionDate = Carbon::parse($data['transaction_date']);

        // The operational Utilities row and its mirror ledger entry must land
        // together — a half-write would leave a utility charge with no matching
        // expense in the books (or vice-versa).
        return DB::transaction(function () use ($rental, $data, $transactionDate) {
            Utilities::create([
                'tenant_id' => $rental->tenant_id,
                'rental_id' => $rental->id,
                'utility_type' => $data['utility_type'],
                'meter_reading_in' => $data['meter_reading_in'] ?? 0,
                'meter_reading_out' => $data['meter_reading_out'] ?? 0,
                'charge_amount' => $data['charge_amount'],
                'billing_month' => $transactionDate->month,
                'billing_year' => $transactionDate->year,
                'paid_status' => true,
                'paid_at' => $data['transaction_date'],
            ]);

            return Accounts::create([
                'fiscal_period_id' => $this->period->id,
                'property_id' => $rental->apartment?->floor?->property_id ?? $this->propertyId,
                'payment_id' => null,
                'user_id' => $this->userId,
                'account_type' => Accounts::TYPE_EXPENSE,
                'category' => Accounts::CAT_UTILITIES_EXPENSE,
                'description' => '[Apt '.($rental->apartment?->apartment_number ?? 'N/A').'] '.ucfirst($data['utility_type']),
                'amount' => $data['charge_amount'],
                'transaction_date' => $data['transaction_date'],
                'note' => $data['note'] ?? null,
            ]);
        });
    }

    /**
     * Misc one-off expense (maintenance, repairs, taxes, etc.). Ledger only.
     */
    public function recordOtherExpense(array $data): Accounts
    {
        return Accounts::create([
            'fiscal_period_id' => $this->period->id,
            'property_id' => $this->propertyId,
            'payment_id' => null,
            'user_id' => $this->userId,
            'account_type' => Accounts::TYPE_EXPENSE,
            'category' => $data['category'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * Delete an "other" expense. Returns the description for the flash message.
     */
    public function deleteOtherExpense(Accounts $expense): string
    {
        $desc = $expense->description;
        $expense->delete();

        return $desc;
    }

    /**
     * Recurring business overhead (insurance, salaries, etc.). Writes the
     * BusinessExpense row AND a matching Accounts entry so the dashboard
     * picks it up.
     */
    public function recordBusinessExpense(array $data): BusinessExpense
    {
        $expenseDate = Carbon::parse($data['expense_date']);

        // The BusinessExpense and its mirror ledger row are hard-linked via
        // ledger_entry_id; create both atomically so the link can never dangle.
        return DB::transaction(function () use ($data, $expenseDate) {
            $ledgerEntry = Accounts::create([
                'fiscal_period_id' => $this->period->id,
                'property_id' => $this->propertyId,
                'payment_id' => null,
                'user_id' => $this->userId,
                'account_type' => Accounts::TYPE_EXPENSE,
                'category' => Accounts::CAT_BUSINESS_VARIABLE,
                'description' => '[Business] '.$data['expense_name'],
                'amount' => $data['amount'],
                'transaction_date' => $data['expense_date'],
                'note' => $data['note'] ?? null,
            ]);

            return BusinessExpense::create([
                'user_id' => $this->userId,
                'fiscal_period_id' => $this->period->id,
                'property_id' => $this->propertyId,
                'expense_name' => $data['expense_name'],
                'category' => $data['category'],
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'billing_month' => $expenseDate->month,
                'billing_year' => $expenseDate->year,
                'is_recurring' => (bool) ($data['is_recurring'] ?? false),
                'note' => $data['note'] ?? null,
                // Hard link to the mirror ledger row so deletion removes exactly
                // this expense's entry (not a look-alike twin's).
                'ledger_entry_id' => $ledgerEntry->id,
            ]);
        });
    }

    /**
     * Delete a business expense, its mirror Accounts row, and its attachment.
     * Returns the expense name for the flash message.
     */
    public function deleteBusinessExpense(BusinessExpense $businessExpense): string
    {
        $name = $businessExpense->expense_name;

        // Delete the expense and its mirror ledger row together; file cleanup
        // runs only after the DB rows are confirmed gone (files can't roll back).
        DB::transaction(function () use ($businessExpense) {
            if ($businessExpense->ledger_entry_id !== null) {
                Accounts::whereKey($businessExpense->ledger_entry_id)->delete();
            } else {
                // Legacy rows (created before ledger_entry_id existed): best-effort
                // match on the mirror row's identifying fields.
                Accounts::where('user_id', $this->userId)
                    ->where('fiscal_period_id', $businessExpense->fiscal_period_id)
                    ->where('account_type', Accounts::TYPE_EXPENSE)
                    ->where('category', Accounts::CAT_BUSINESS_VARIABLE)
                    ->where('amount', $businessExpense->amount)
                    ->where('transaction_date', $businessExpense->expense_date)
                    ->where('description', '[Business] '.$businessExpense->expense_name)
                    ->limit(1)
                    ->delete();
            }

            $businessExpense->delete();
        });

        $this->attachments->deleteAllFor($businessExpense);

        return $name;
    }

    /**
     * Per-apartment recurring expense template (consumed later by bill
     * generation). Not a ledger entry by itself.
     */
    public function recordFixedExpense(array $data): ApartmentFixedExpense
    {
        return ApartmentFixedExpense::create([
            'apartment_id' => $data['apartment_id'],
            'expense_name' => $data['expense_name'],
            'expense_type' => $data['expense_type'],
            'amount' => $data['amount'],
            'is_active' => true,
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * Flip a fixed expense on/off. Returns the new is_active state.
     */
    public function toggleFixedExpense(ApartmentFixedExpense $fixedExpense): bool
    {
        $fixedExpense->update(['is_active' => ! $fixedExpense->is_active]);

        return $fixedExpense->is_active;
    }

    /**
     * Delete a fixed expense. Returns its name for the flash message.
     */
    public function deleteFixedExpense(ApartmentFixedExpense $fixedExpense): string
    {
        $name = $fixedExpense->expense_name;
        $fixedExpense->delete();

        return $name;
    }
}
