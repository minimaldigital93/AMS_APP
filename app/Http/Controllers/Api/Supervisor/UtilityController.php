<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Resources\UtilityResource;
use App\Models\Apartments;
use App\Models\Rentals;
use App\Models\Utilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UtilityController extends Controller
{
    /**
     * Get rental IDs for apartments managed by the current supervisor.
     */
    private function getManagedRentalIds(): array
    {
        $apartmentIds = Apartments::where('supervisor_id', Auth::id())->pluck('id')->toArray();
        return Rentals::whereIn('apartment_id', $apartmentIds)->pluck('id')->toArray();
    }

    /**
     * Display utilities for managed apartments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rentalIds = $this->getManagedRentalIds();

        $query = Utilities::whereIn('rental_id', $rentalIds);

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

        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $utilities = $query->with(['tenant', 'rental.apartment'])->paginate($perPage);

        return UtilityResource::collection($utilities);
    }

    /**
     * Store a newly created utility.
     */
    public function store(Request $request): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'rental_id' => ['required', 'exists:rentals,id', Rule::in($rentalIds)],
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
    public function show(Utilities $utility): UtilityResource|JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        if (!in_array($utility->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new UtilityResource($utility->load(['tenant', 'rental']));
    }

    /**
     * Update the specified utility.
     */
    public function update(Request $request, Utilities $utility): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        if (!in_array($utility->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
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
     * Mark utility as paid.
     */
    public function markPaid(Utilities $utility): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        if (!in_array($utility->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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
     * Get unpaid utilities for managed apartments.
     */
    public function unpaid(): AnonymousResourceCollection
    {
        $rentalIds = $this->getManagedRentalIds();

        $utilities = Utilities::whereIn('rental_id', $rentalIds)
            ->where('paid_status', false)
            ->with(['tenant', 'rental.apartment'])
            ->get();

        return UtilityResource::collection($utilities);
    }
}
