<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FiscalPeriodResource;
use App\Models\FiscalPeriods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class FiscalPeriodController extends Controller
{
    /**
     * Display a listing of fiscal periods.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FiscalPeriods::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'opening_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $fiscalPeriods = $query->with(['user', 'accounts'])->paginate($perPage);

        return FiscalPeriodResource::collection($fiscalPeriods);
    }

    /**
     * Store a newly created fiscal period.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'opening_date' => ['required', 'date'],
            'closing_date' => ['nullable', 'date', 'after:opening_date'],
            'opening_balance' => ['required', 'numeric'],
            'closing_balance' => ['nullable', 'numeric'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        // Set the user_id to the authenticated user
        $validated['user_id'] = $request->user()->id;

        $fiscalPeriod = FiscalPeriods::create($validated);

        return response()->json([
            'message' => 'Fiscal period created successfully',
            'data' => new FiscalPeriodResource($fiscalPeriod->load('user')),
        ], 201);
    }

    /**
     * Display the specified fiscal period.
     */
    public function show(FiscalPeriods $fiscalPeriod): FiscalPeriodResource
    {
        return new FiscalPeriodResource($fiscalPeriod->load(['user', 'accounts', 'balanceSheets']));
    }

    /**
     * Update the specified fiscal period.
     */
    public function update(Request $request, FiscalPeriods $fiscalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'opening_date' => ['sometimes', 'required', 'date'],
            'closing_date' => ['nullable', 'date', 'after:opening_date'],
            'opening_balance' => ['sometimes', 'required', 'numeric'],
            'closing_balance' => ['nullable', 'numeric'],
            'status' => ['sometimes', 'required', Rule::in(['open', 'closed'])],
        ]);

        $fiscalPeriod->update($validated);

        return response()->json([
            'message' => 'Fiscal period updated successfully',
            'data' => new FiscalPeriodResource($fiscalPeriod->load('user')),
        ]);
    }

    /**
     * Remove the specified fiscal period.
     */
    public function destroy(FiscalPeriods $fiscalPeriod): JsonResponse
    {
        $fiscalPeriod->delete();

        return response()->json([
            'message' => 'Fiscal period deleted successfully',
        ]);
    }

    /**
     * Close a fiscal period.
     */
    public function close(Request $request, FiscalPeriods $fiscalPeriod): JsonResponse
    {
        $validated = $request->validate([
            'closing_balance' => ['required', 'numeric'],
        ]);

        $fiscalPeriod->update([
            'status' => 'closed',
            'closing_date' => now(),
            'closing_balance' => $validated['closing_balance'],
        ]);

        return response()->json([
            'message' => 'Fiscal period closed successfully',
            'data' => new FiscalPeriodResource($fiscalPeriod),
        ]);
    }

    /**
     * Get current active fiscal period.
     */
    public function current(): JsonResponse
    {
        $currentPeriod = FiscalPeriods::where('status', 'open')
            ->orderBy('opening_date', 'desc')
            ->first();

        if (!$currentPeriod) {
            return response()->json([
                'message' => 'No active fiscal period found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new FiscalPeriodResource($currentPeriod->load(['user', 'accounts'])),
        ]);
    }
}
