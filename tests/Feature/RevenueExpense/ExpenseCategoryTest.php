<?php

use App\Models\BusinessExpense;
use App\Models\ExpenseCategory;

/**
 * The account's expense vocabulary — Settings → Expense Categories, and the
 * dropdown the record-expense form reads from it.
 *
 * Three rules the feature rests on:
 *  - the form and its validation read the SAME list (they used to be two
 *    hard-coded lists that had drifted: "Legal Fee"/"Salary" were offered by
 *    the dropdown and rejected by the request);
 *  - `key` is immutable, so renaming never restates booked expenses;
 *  - a category stamped on booked money can be retired but never deleted.
 */
beforeEach(function () {
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
    $this->period = makeFiscalPeriod($this->admin);
});

function expensePayload(array $overrides = []): array
{
    return array_merge([
        'expense_name' => 'Test expense',
        'category' => 'electricity',
        'amount' => 120,
        'expense_date' => now()->toDateString(),
    ], $overrides);
}

it('seeds the default categories on first visit to the settings page', function () {
    expect(ExpenseCategory::count())->toBe(0);

    $this->get(route('admin.settings.expense_categories'))->assertOk();

    expect(ExpenseCategory::count())->toBe(count(ExpenseCategory::DEFAULTS));
    expect(ExpenseCategory::pluck('key')->all())
        ->toEqualCanonicalizing(array_keys(ExpenseCategory::DEFAULTS));
});

it('seeds the defaults for the record-expense page too, scoped to the account', function () {
    $this->get(route('admin.revenue_expense.record_expense'))->assertOk();

    expect(ExpenseCategory::count())->toBe(count(ExpenseCategory::DEFAULTS));
    expect(ExpenseCategory::pluck('account_id')->unique()->all())->toBe([$this->admin->id]);
});

it('does not re-seed once the account has categories', function () {
    $this->get(route('admin.settings.expense_categories'))->assertOk();
    ExpenseCategory::where('key', 'trash')->delete();

    $this->get(route('admin.settings.expense_categories'))->assertOk();

    expect(ExpenseCategory::where('key', 'trash')->exists())->toBeFalse();
});

it('adds a category and derives a stable key from its name', function () {
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Cleaning Services'])
        ->assertRedirect(route('admin.settings.expense_categories'));

    $category = ExpenseCategory::where('name', 'Cleaning Services')->first();
    expect($category)->not->toBeNull()
        ->and($category->key)->toBe('cleaning_services')
        ->and($category->is_active)->toBeTrue();
});

it('rejects a duplicate category name', function () {
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Cleaning']);
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'cleaning'])
        ->assertSessionHasErrors('name');

    expect(ExpenseCategory::where('key', 'like', 'cleaning%')->count())->toBe(1);
});

it('renames a category without changing its key or restating booked expenses', function () {
    $this->get(route('admin.settings.expense_categories'));
    $category = ExpenseCategory::where('key', 'electricity')->first();

    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload());
    expect(BusinessExpense::first()->category)->toBe('electricity');

    $this->put(route('admin.settings.expense_categories.update', $category), [
        'name' => 'Power Bill',
        'is_active' => '1',
    ])->assertRedirect(route('admin.settings.expense_categories'));

    expect($category->fresh()->key)->toBe('electricity')
        ->and($category->fresh()->name)->toBe('Power Bill')
        // The booked expense still points at the same key, and now reads the
        // new label wherever it is printed.
        ->and(BusinessExpense::first()->category)->toBe('electricity');

    ExpenseCategory::flushLabelMemo();
    expect(ExpenseCategory::labelFor('electricity'))->toBe('Power Bill');
});

it('hides a deactivated category from the form but keeps labelling its history', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload());

    $category = ExpenseCategory::where('key', 'electricity')->first();
    $this->put(route('admin.settings.expense_categories.update', $category), ['name' => 'Electricity']);

    ExpenseCategory::flushLabelMemo();
    expect(ExpenseCategory::options())->not->toHaveKey('electricity')
        ->and(ExpenseCategory::labelFor('electricity'))->toBe('Electricity');

    // …and it can no longer be submitted.
    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload())
        ->assertSessionHasErrors('category');
    expect(BusinessExpense::count())->toBe(1);
});

it('refuses to deactivate the last active category', function () {
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Only One']);
    ExpenseCategory::where('key', '!=', 'only_one')->delete();

    $category = ExpenseCategory::where('key', 'only_one')->first();
    $this->put(route('admin.settings.expense_categories.update', $category), ['name' => 'Only One'])
        ->assertSessionHasErrors('is_active');

    expect($category->fresh()->is_active)->toBeTrue();
});

it('deletes an unused category', function () {
    $this->get(route('admin.settings.expense_categories'));
    $category = ExpenseCategory::where('key', 'trash')->first();

    $this->delete(route('admin.settings.expense_categories.destroy', $category))
        ->assertRedirect(route('admin.settings.expense_categories'));

    expect(ExpenseCategory::where('key', 'trash')->exists())->toBeFalse();
});

it('refuses to delete a category that booked expenses reference', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload());

    $category = ExpenseCategory::where('key', 'electricity')->first();
    $this->delete(route('admin.settings.expense_categories.destroy', $category))
        ->assertSessionHasErrors('delete');

    expect(ExpenseCategory::where('key', 'electricity')->exists())->toBeTrue();
});

it('counts every booked row that references a category', function () {
    $this->get(route('admin.settings.expense_categories'));
    $category = ExpenseCategory::where('key', 'maintenance')->first();

    expect($category->usageCount())->toBe(0)
        ->and($category->isInUse())->toBeFalse();

    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload(['category' => 'maintenance']));
    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload(['category' => 'maintenance']));
    // …and the separate "other expense" vocabulary writes the ledger directly,
    // where some of the same keys land.
    $this->post(route('admin.revenue_expense.store_other_expense'), [
        'category' => 'maintenance',
        'description' => 'Roof patch',
        'amount' => 40,
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($category->usageCount())->toBe(3)
        ->and(ExpenseCategory::usageByKey()['maintenance'] ?? 0)->toBe(3);
});

it('does not let another account booking hold a category open', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload());

    // Same key, different account: the delete must go through on the account
    // whose own books are empty.
    $other = makeAdmin(['name' => 'Other Admin']);
    $this->actingAs($other);
    $this->get(route('admin.settings.expense_categories'));

    $category = ExpenseCategory::where('key', 'electricity')->first();
    expect($category->usageCount())->toBe(0);

    $this->delete(route('admin.settings.expense_categories.destroy', $category))
        ->assertSessionHasNoErrors();

    expect(ExpenseCategory::where('key', 'electricity')->exists())->toBeFalse();
});

it('offers deactivation instead of deletion on an in-use category', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload());
    $category = ExpenseCategory::where('key', 'electricity')->first();

    // The page swaps the delete form for the locked button that opens the
    // "can't delete" dialog, wired to the deactivate form it submits.
    $this->get(route('admin.settings.expense_categories'))
        ->assertOk()
        ->assertSee('data-category-locked', false)
        ->assertSee('deactivate-category-'.$category->id, false)
        ->assertSee(__('messages.expense_category_locked_title'))
        // …and the row no longer carries a delete confirmation at all (the
        // destroy URI is the update URI under another verb, so assert on this).
        ->assertDontSee(__('messages.expense_category_delete_confirm', ['name' => $category->name]));

    // …and that form is a plain update, so it lands on the guarded route.
    $this->put(route('admin.settings.expense_categories.update', $category), [
        'name' => $category->name,
        'is_active' => '0',
    ])->assertSessionHasNoErrors();

    expect($category->fresh()->is_active)->toBeFalse();
});

it('restores deleted defaults while leaving custom categories alone', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Cleaning Services']);
    ExpenseCategory::whereIn('key', ['trash', 'insurance'])->delete();

    $this->post(route('admin.settings.expense_categories.restore'))
        ->assertRedirect(route('admin.settings.expense_categories'));

    expect(ExpenseCategory::pluck('key')->all())
        ->toContain('trash', 'insurance', 'cleaning_services')
        ->and(ExpenseCategory::count())->toBe(count(ExpenseCategory::DEFAULTS) + 1);
});

it('accepts every category the form offers', function () {
    $this->get(route('admin.revenue_expense.record_expense'))->assertOk();

    foreach (array_keys(ExpenseCategory::options()) as $key) {
        $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload([
            'expense_name' => 'Expense '.$key,
            'category' => $key,
        ]))->assertSessionHasNoErrors();
    }

    expect(BusinessExpense::count())->toBe(count(ExpenseCategory::DEFAULTS));
});

it('rejects a category that is not in the account vocabulary', function () {
    $this->get(route('admin.revenue_expense.record_expense'));

    $this->post(route('admin.revenue_expense.store_business_expense'), expensePayload([
        'category' => 'not_a_category',
    ]))->assertSessionHasErrors('category');

    expect(BusinessExpense::count())->toBe(0);
});

it('keeps each account vocabulary to itself', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Cleaning Services']);
    $mine = ExpenseCategory::where('key', 'cleaning_services')->first();

    $other = makeAdmin(['name' => 'Other Admin']);
    $this->actingAs($other);

    expect(ExpenseCategory::count())->toBe(0);

    // Route binding is account-scoped, so the other account's row is invisible.
    $this->put(route('admin.settings.expense_categories.update', $mine), ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($mine->fresh()->name)->toBe('Cleaning Services');
});

it('lets a supervisor record against the admin account vocabulary', function () {
    $this->get(route('admin.settings.expense_categories'));
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Cleaning Services']);

    $supervisor = makeSupervisor();
    $supervisor->forceFill(['account_id' => $this->admin->id])->save();
    $this->actingAs($supervisor);

    $this->post(route('supervisor.revenue_expense.store_business_expense'), expensePayload([
        'category' => 'cleaning_services',
    ]))->assertSessionHasNoErrors();

    expect(BusinessExpense::withoutGlobalScope('account')->where('category', 'cleaning_services')->exists())
        ->toBeTrue();
    // The supervisor recorded, but wrote no vocabulary of their own.
    expect(ExpenseCategory::withoutGlobalScope('account')->where('account_id', $supervisor->id)->count())->toBe(0);
});

it('is purged with the account', function () {
    $this->get(route('admin.settings.expense_categories'));
    expect(ExpenseCategory::count())->toBeGreaterThan(0);

    app(\App\Services\Platform\AccountPurgeService::class)->purge($this->admin);

    expect(ExpenseCategory::withoutGlobalScope('account')->where('account_id', $this->admin->id)->count())
        ->toBe(0);
});

it('does not let a supervisor manage the vocabulary', function () {
    $supervisor = makeSupervisor();
    $supervisor->forceFill(['account_id' => $this->admin->id])->save();
    $this->actingAs($supervisor);

    $this->get(route('admin.settings.expense_categories'))->assertForbidden();
    $this->post(route('admin.settings.expense_categories.store'), ['name' => 'Sneaky'])->assertForbidden();
});
