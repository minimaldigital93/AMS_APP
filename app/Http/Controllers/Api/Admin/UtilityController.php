<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UtilityResource;
use App\Models\Utilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UtilityController extends Controller
{
    /**
     * Display a listing of utilities.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Utilities::query();

        // Filter by tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->get('tenant_id'));
        }

        // Filter by rental
        if ($request->has('rental_id')) {
            $query->where('rental_id', $request->get('rental_id'));
        }

        // Filter by utility type
        if ($request->has('utility_type')) {
            $query->where('utility_type', $request->get('utility_type'));
        }

        // Filter by paid status
        if ($request->has('paid_status')) {
            $query->where('paid_status', $request->boolean('paid_status'));
        }

        // Filter by billing month/year
        if ($request->has('billing_month')) {
            $query->where('billing_month', $request->get('billing_month'));
        }
        if ($request->has('billing_year')) {
            $query->where('billing_year', $request->get('billing_year'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $utilities = $query->with(['tenant', 'rental'])->paginate($perPage);

        return UtilityResource::collection($utilities);
    }

    /**
     * Store a newly created utility.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'rental_id' => ['required', 'exists:rentals,id'],
            'utility_type' => ['required', Rule::in(['electricity', 'water', 'gas', 'internet', 'other'])],
            'meter_number' => ['nullable', 'string', 'max:100'],
            'meter_reading_in' => ['nullable', 'numeric', 'min:0'],
            'meter_reading_out' => ['nullable', 'numeric', 'min:0'],
            'charge_amount' => ['required', 'numeric', 'min:0'],
            'billing_month' => ['required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['required', 'integer', 'min:2000'],
            'paid_status' => ['required', 'boolean'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $utility = Utilities::create($validated);

        return response()->json([
            'message' => 'Utility record created successfully',
            'data' => new UtilityResource($utility->load(['tenant', 'rental'])),
        ], 201);
    }

    /**
     * Display the specified utility.
     */
    public function show(Utilities $utility): UtilityResource
    {
        return new UtilityResource($utility->load(['tenant', 'rental']));
    }

    /**
     * Update the specified utility.
     */
    public function update(Request $request, Utilities $utility): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['sometimes', 'required', 'exists:tenants,id'],
            'rental_id' => ['sometimes', 'required', 'exists:rentals,id'],
            'utility_type' => ['sometimes', 'required', Rule::in(['electricity', 'water', 'gas', 'internet', 'other'])],
            'meter_number' => ['nullable', 'string', 'max:100'],
            'meter_reading_in' => ['nullable', 'numeric', 'min:0'],
            'meter_reading_out' => ['nullable', 'numeric', 'min:0'],
            'charge_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'billing_month' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'billing_year' => ['sometimes', 'required', 'integer', 'min:2000'],
            'paid_status' => ['sometimes', 'required', 'boolean'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $utility->update($validated);

        return response()->json([
            'message' => 'Utility record updated successfully',
            'data' => new UtilityResource($utility->load(['tenant', 'rental'])),
        ]);
    }

    /**
     * Remove the specified utility.
     */
    public function destroy(Utilities $utility): JsonResponse
    {
        $utility->delete();

        return response()->json([
            'message' => 'Utility record deleted successfully',
        ]);
    }

    /**
     * Mark utility as paid.
     */
    public function markPaid(Utilities $utility): JsonResponse
    {
        $utility->update([
            'paid_status' => true,
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Utility marked as paid',
            'data' => new UtilityResource($utility),
        ]);
    }

    /**
     * Get unpaid utilities.
     */
    public function unpaid(Request $request): AnonymousResourceCollection
    {
        $query = Utilities::where('paid_status', false);

        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->get('tenant_id'));
        }

        $perPage = $request->get('per_page', 15);
        $utilities = $query->with(['tenant', 'rental'])->paginate($perPage);

        return UtilityResource::collection($utilities);
    }
}
