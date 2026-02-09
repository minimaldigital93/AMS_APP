<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * Get the tenant record for the current user.
     */
    private function getTenant(): ?Tenants
    {
        return Tenants::where('user_id', Auth::id())->first();
    }

    /**
     * Get rental IDs for the current tenant.
     */
    private function getRentalIds(): array
    {
        $tenant = $this->getTenant();
        return $tenant ? Rentals::where('tenant_id', $tenant->id)->pluck('id')->toArray() : [];
    }

    /**
     * Display all payments for the authenticated tenant.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $rentalIds = $this->getRentalIds();

        if (empty($rentalIds)) {
            return response()->json(['message' => 'No rentals found'], 404);
        }

        $query = Payments::whereIn('rental_id', $rentalIds);

        // Filter by status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Filter by type
        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->get('payment_type'));
        }

        $sortBy = $request->get('sort_by', 'due_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $payments = $query->with('rental.apartment')->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Display the specified payment (only if owned by tenant).
     */
    public function show(Payments $payment): PaymentResource|JsonResponse
    {
        $rentalIds = $this->getRentalIds();

        if (!in_array($payment->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new PaymentResource($payment->load('rental.apartment'));
    }

    /**
     * Get pending payments for the tenant.
     */
    public function pending(): AnonymousResourceCollection|JsonResponse
    {
        $rentalIds = $this->getRentalIds();

        if (empty($rentalIds)) {
            return response()->json(['message' => 'No rentals found'], 404);
        }

        $payments = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'pending')
            ->with('rental.apartment')
            ->orderBy('due_date', 'asc')
            ->get();

        return PaymentResource::collection($payments);
    }

    /**
     * Get overdue payments for the tenant.
     */
    public function overdue(): AnonymousResourceCollection|JsonResponse
    {
        $rentalIds = $this->getRentalIds();

        if (empty($rentalIds)) {
            return response()->json(['message' => 'No rentals found'], 404);
        }

        $payments = Payments::whereIn('rental_id', $rentalIds)
            ->where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled')
            ->with('rental.apartment')
            ->orderBy('due_date', 'asc')
            ->get();

        return PaymentResource::collection($payments);
    }

    /**
     * Get payment history (paid payments).
     */
    public function history(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $rentalIds = $this->getRentalIds();

        if (empty($rentalIds)) {
            return response()->json(['message' => 'No rentals found'], 404);
        }

        $query = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'paid')
            ->orderBy('paid_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $payments = $query->with('rental.apartment')->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Get payment summary for the tenant.
     */
    public function summary(): JsonResponse
    {
        $rentalIds = $this->getRentalIds();

        if (empty($rentalIds)) {
            return response()->json(['message' => 'No rentals found'], 404);
        }

        $totalPaid = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $totalPending = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'pending')
            ->sum('amount');

        $totalOverdue = Payments::whereIn('rental_id', $rentalIds)
            ->where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled')
            ->sum('amount');

        $nextPayment = Payments::whereIn('rental_id', $rentalIds)
            ->where('payment_status', 'pending')
            ->orderBy('due_date', 'asc')
            ->first();

        return response()->json([
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_overdue' => $totalOverdue,
            'next_payment' => $nextPayment ? new PaymentResource($nextPayment) : null,
        ]);
    }
}
