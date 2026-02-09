<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\UtilityResource;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Models\Utilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class UtilityController extends Controller
{
    /**
     * Get the tenant record for the current user.
     */
    private function getTenant(): ?Tenants
    {
        return Tenants::where('user_id', Auth::id())->first();
    }

    /**
     * Display all utilities for the authenticated tenant.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $query = Utilities::where('tenant_id', $tenant->id);

        // Filter by utility type
        if ($request->has('utility_type')) {
            $query->where('utility_type', $request->get('utility_type'));
        }

        // Filter by paid status
        if ($request->has('paid_status')) {
            $query->where('paid_status', $request->boolean('paid_status'));
        }

        // Filter by billing period
        if ($request->has('billing_month')) {
            $query->where('billing_month', $request->get('billing_month'));
        }
        if ($request->has('billing_year')) {
            $query->where('billing_year', $request->get('billing_year'));
        }

        $sortBy = $request->get('sort_by', 'billing_year');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder)
              ->orderBy('billing_month', 'desc');

        $perPage = $request->get('per_page', 15);
        $utilities = $query->with('rental.apartment')->paginate($perPage);

        return UtilityResource::collection($utilities);
    }

    /**
     * Display the specified utility (only if owned by tenant).
     */
    public function show(Utilities $utility): UtilityResource|JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant || $utility->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new UtilityResource($utility->load('rental.apartment'));
    }

    /**
     * Get unpaid utilities for the tenant.
     */
    public function unpaid(): AnonymousResourceCollection|JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $utilities = Utilities::where('tenant_id', $tenant->id)
            ->where('paid_status', false)
            ->with('rental.apartment')
            ->orderBy('billing_year', 'desc')
            ->orderBy('billing_month', 'desc')
            ->get();

        return UtilityResource::collection($utilities);
    }

    /**
     * Get utility summary for the tenant.
     */
    public function summary(): JsonResponse
    {
        $tenant = $this->getTenant();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant profile not found'], 404);
        }

        $totalPaid = Utilities::where('tenant_id', $tenant->id)
            ->where('paid_status', true)
            ->sum('charge_amount');

        $totalUnpaid = Utilities::where('tenant_id', $tenant->id)
            ->where('paid_status', false)
            ->sum('charge_amount');

        $byType = Utilities::where('tenant_id', $tenant->id)
            ->selectRaw('utility_type, SUM(charge_amount) as total, SUM(CASE WHEN paid_status = false THEN charge_amount ELSE 0 END) as unpaid')
            ->groupBy('utility_type')
            ->get();

        return response()->json([
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalUnpaid,
            'by_type' => $byType,
        ]);
    }
}
