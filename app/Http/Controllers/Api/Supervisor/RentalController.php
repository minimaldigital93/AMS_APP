<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalResource;
use App\Models\Apartments;
use App\Models\Rentals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class RentalController extends Controller
{
    /**
     * Get apartment IDs managed by the current supervisor.
     */
    private function getManagedApartmentIds(): array
    {
        return Apartments::where('supervisor_id', Auth::id())->pluck('id')->toArray();
    }

    /**
     * Display rentals for apartments managed by the supervisor.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $apartmentIds = $this->getManagedApartmentIds();

        $query = Rentals::whereIn('apartment_id', $apartmentIds);

        // Filter by apartment
        if ($request->has('apartment_id')) {
            $query->where('apartment_id', $request->get('apartment_id'));
        }

        // Filter by tenant
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->get('tenant_id'));
        }

        // Active rentals only
        if ($request->boolean('active_only')) {
            $query->where('start_date', '<=', now())
                  ->where(function ($q) {
                      $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                  });
        }

        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $rentals = $query->with(['apartment', 'tenant', 'payments'])->paginate($perPage);

        return RentalResource::collection($rentals);
    }

    /**
     * Store a newly created rental.
     */
    public function store(Request $request): JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        $validated = $request->validate([
            'apartment_id' => ['required', 'exists:apartments,id', function ($attribute, $value, $fail) use ($apartmentIds) {
                if (!in_array($value, $apartmentIds)) {
                    $fail('You can only create rentals for apartments you manage.');
                }
            }],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rental = Rentals::create($validated);

        // Update apartment status to occupied
        Apartments::where('id', $validated['apartment_id'])->update(['status' => 'occupied']);

        return response()->json([
            'message' => 'Rental created successfully',
            'data' => new RentalResource($rental->load(['apartment', 'tenant'])),
        ], 201);
    }

    /**
     * Display the specified rental.
     */
    public function show(Rentals $rental): RentalResource|JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        if (!in_array($rental->apartment_id, $apartmentIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new RentalResource($rental->load(['apartment', 'tenant', 'payments', 'utilities']));
    }

    /**
     * Update the specified rental.
     */
    public function update(Request $request, Rentals $rental): JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        if (!in_array($rental->apartment_id, $apartmentIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rental->update($validated);

        return response()->json([
            'message' => 'Rental updated successfully',
            'data' => new RentalResource($rental->load(['apartment', 'tenant'])),
        ]);
    }

    /**
     * End a rental (set end date).
     */
    public function endRental(Request $request, Rentals $rental): JsonResponse
    {
        $apartmentIds = $this->getManagedApartmentIds();

        if (!in_array($rental->apartment_id, $apartmentIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:' . $rental->start_date->format('Y-m-d')],
        ]);

        $rental->update(['end_date' => $validated['end_date']]);

        // Update apartment status to available
        Apartments::where('id', $rental->apartment_id)->update(['status' => 'available']);

        return response()->json([
            'message' => 'Rental ended successfully',
            'data' => new RentalResource($rental),
        ]);
    }

    /**
     * Get active rentals for managed apartments.
     */
    public function active(): AnonymousResourceCollection
    {
        $apartmentIds = $this->getManagedApartmentIds();

        $rentals = Rentals::whereIn('apartment_id', $apartmentIds)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->with(['apartment', 'tenant'])
            ->get();

        return RentalResource::collection($rentals);
    }
}
