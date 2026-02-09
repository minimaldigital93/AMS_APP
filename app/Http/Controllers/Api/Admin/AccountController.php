<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use App\Models\Accounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Accounts::query();

        // Filter by fiscal period
        if ($request->has('fiscal_period_id')) {
            $query->where('fiscal_period_id', $request->get('fiscal_period_id'));
        }

        // Filter by account type
        if ($request->has('account_type')) {
            $query->where('account_type', $request->get('account_type'));
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('transaction_date', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('transaction_date', '<=', $request->get('to_date'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'transaction_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $accounts = $query->with(['fiscalPeriod', 'payment', 'user'])->paginate($perPage);

        return AccountResource::collection($accounts);
    }

    /**
     * Store a newly created account entry.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_period_id' => ['required', 'exists:fiscal_periods,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'account_type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        // Set the user_id to the authenticated user if not provided
        if (!isset($validated['user_id'])) {
            $validated['user_id'] = $request->user()->id;
        }

        $account = Accounts::create($validated);

        return response()->json([
            'message' => 'Account entry created successfully',
            'data' => new AccountResource($account->load(['fiscalPeriod', 'payment', 'user'])),
        ], 201);
    }

    /**
     * Display the specified account.
     */
    public function show(Accounts $account): AccountResource
    {
        return new AccountResource($account->load(['fiscalPeriod', 'payment', 'user']));
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, Accounts $account): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_period_id' => ['sometimes', 'required', 'exists:fiscal_periods,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'account_type' => ['sometimes', 'required', Rule::in(['income', 'expense'])],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'required', 'string'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
        ]);

        $account->update($validated);

        return response()->json([
            'message' => 'Account entry updated successfully',
            'data' => new AccountResource($account->load(['fiscalPeriod', 'payment', 'user'])),
        ]);
    }

    /**
     * Remove the specified account.
     */
    public function destroy(Accounts $account): JsonResponse
    {
        $account->delete();

        return response()->json([
            'message' => 'Account entry deleted successfully',
        ]);
    }

    /**
     * Get account summary by type.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = Accounts::query();

        // Filter by fiscal period
        if ($request->has('fiscal_period_id')) {
            $query->where('fiscal_period_id', $request->get('fiscal_period_id'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('transaction_date', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('transaction_date', '<=', $request->get('to_date'));
        }

        $totalIncome = (clone $query)->where('account_type', 'income')->sum('amount');
        $totalExpense = (clone $query)->where('account_type', 'expense')->sum('amount');
        $netBalance = $totalIncome - $totalExpense;

        // Get category breakdown
        $incomeByCategory = (clone $query)->where('account_type', 'income')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        $expenseByCategory = (clone $query)->where('account_type', 'expense')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_balance' => $netBalance,
            'income_by_category' => $incomeByCategory,
            'expense_by_category' => $expenseByCategory,
        ]);
    }
}
