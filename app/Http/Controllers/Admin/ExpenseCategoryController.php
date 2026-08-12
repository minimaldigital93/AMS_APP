<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;


class ExpenseCategoryController extends Controller
{
    /**
     * The category list, with the booked-expense count that gates deletion.
     */
    public function index(): View
    {
        // First visit materialises the starter set, so the page is never empty.
        ExpenseCategory::ensureDefaults();

        $categories = ExpenseCategory::ordered()->get();

        // A category on booked expenses can be renamed and retired but not
        // deleted. This count is what the "can't delete" dialog reports.
        $usage = ExpenseCategory::usageByKey();

        return view('admin.settings.expense_categories', compact('categories', 'usage'));
    }

    /**
     * Add a category. Its key is derived from the name once and never changes.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        ExpenseCategory::ensureDefaults();

        $name = trim($validated['name']);

        // The name is what the operator reads in the dropdown; two identical
        // ones are indistinguishable there.
        if (ExpenseCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['name' => __('messages.expense_category_duplicate')]);
        }

        ExpenseCategory::create([
            'key' => ExpenseCategory::makeKey($name),
            'name' => $name,
            'is_active' => true,
            'sort_order' => (int) ExpenseCategory::query()->max('sort_order') + 10,
        ]);

        ExpenseCategory::flushLabelMemo();

        return redirect()->route('admin.settings.expense_categories')
            ->with('success', __('messages.expense_category_added', ['name' => $name]));
    }

    /**
     * Rename and/or activate-deactivate. The key is never touched — booked
     * expenses reference it, and the income statement maps some keys onto their
     * own lines.
     */
    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $name = trim($validated['name']);

        if (ExpenseCategory::query()
            ->whereKeyNot($expenseCategory->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists()
        ) {
            return back()
                ->withInput()
                ->withErrors(['name' => __('messages.expense_category_duplicate')]);
        }

        $isActive = $request->boolean('is_active');

        // Refuse to retire the last selectable category: an empty dropdown makes
        // the expense form unusable.
        if (! $isActive && ExpenseCategory::query()->active()->whereKeyNot($expenseCategory->id)->doesntExist()) {
            return back()->withErrors(['is_active' => __('messages.expense_category_last_active')]);
        }

        $expenseCategory->update([
            'name' => $name,
            'is_active' => $isActive,
        ]);

        ExpenseCategory::flushLabelMemo();

        return redirect()->route('admin.settings.expense_categories')
            ->with('success', __('messages.expense_category_updated', ['name' => $name]));
    }

    /**
     * The page hides delete behind a dialog for an in-use category, but a stale
     * tab (or anything posting the route directly) still lands here — the guard
     * is the server's, the dialog is only the explanation.
     */
    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $used = $expenseCategory->usageCount();

        if ($used > 0) {
            return back()->withErrors([
                'delete' => __('messages.expense_category_in_use_count', [
                    'name' => $expenseCategory->name,
                    'count' => $used,
                ]),
            ]);
        }

        $name = $expenseCategory->name;
        $expenseCategory->delete();

        ExpenseCategory::flushLabelMemo();

        return redirect()->route('admin.settings.expense_categories')
            ->with('success', __('messages.expense_category_deleted', ['name' => $name]));
    }

    /**
     * Restore the starter vocabulary — adds back any default that was deleted,
     * leaving custom categories and renames alone.
     */
    public function restoreDefaults(): RedirectResponse
    {
        $existing = ExpenseCategory::query()->pluck('key')->all();
        $sort = (int) ExpenseCategory::query()->max('sort_order');
        $added = 0;

        foreach (ExpenseCategory::DEFAULTS as $key => $name) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            ExpenseCategory::create([
                'key' => $key,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $sort += 10,
            ]);
            $added++;
        }

        ExpenseCategory::flushLabelMemo();

        return redirect()->route('admin.settings.expense_categories')
            ->with('success', __('messages.expense_categories_defaults_restored', ['count' => $added]));
    }
}
