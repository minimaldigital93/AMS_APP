<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalResource;
use App\Models\Rentals;
use App\Models\Tenants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class RentalController extends Controller
{
    /**
     * Get the tenant record for the current user.
     */
    private function getTenant(): ?Tenants
    {
        return Tenants::where('user_id', Auth::id())->first();
    }

    /**
     * Display all rentals for the authenticated tenant.
     */
    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $rentals = Rentals::where('tenant_id', $tenant->id)
            ->with(['apartment.floor', 'payments', 'utilities'])
            ->orderBy('start_date', 'desc')
            ->get();

        return RentalResource::collection($rentals);
    }

    /**
     * Display the current active rental.
     */
    public function current(): RentalResource|JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $rental = Rentals::where('tenant_id', $tenant->id)
            ->where('start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            })
            ->with(['apartment.floor', 'payments', 'utilities'])
            ->first();

        if (!$rental) {
            return response()->json(['message' => 'No active rental found'], 404);
        }

        return new RentalResource($rental);
    }

    /**
     * Display the specified rental (only if owned by tenant).
     */
    public function show(Rentals $rental): RentalResource|JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant || $rental->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new RentalResource($rental->load(['apartment.floor', 'payments', 'utilities']));
    }
}
