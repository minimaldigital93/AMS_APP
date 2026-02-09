<?php

namespace App\Http\Controllers\Api\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Apartments;
use App\Models\Payments;
use App\Models\Rentals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
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
     * Display payments for managed apartments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rentalIds = $this->getManagedRentalIds();

        $query = Payments::whereIn('rental_id', $rentalIds);

        // Filter by rental
        if ($request->has('rental_id')) {
            $query->where('rental_id', $request->get('rental_id'));
        }

        // Filter by status
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->get('payment_status'));
        }

        // Filter by type
        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->get('payment_type'));
        }

        // Overdue payments
        if ($request->boolean('overdue_only')) {
            $query->where('due_date', '<', now())
                  ->whereNull('paid_at');
        }

        $sortBy = $request->get('sort_by', 'due_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $payments = $query->with(['rental.tenant', 'rental.apartment'])->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        $validated = $request->validate([
            'rental_id' => ['required', 'exists:rentals,id', Rule::in($rentalIds)],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'check', 'mobile_payment', 'other'])],
            'payment_status' => ['required', Rule::in(['pending', 'paid', 'partial', 'overdue', 'cancelled'])],
            'payment_type' => ['required', Rule::in(['rent', 'deposit', 'utility', 'late_fee', 'other'])],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $payment = Payments::create($validated);

        return response()->json([
            'message' => 'Payment created successfully',
            'data' => new PaymentResource($payment->load('rental')),
        ], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(Payments $payment): PaymentResource|JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        if (!in_array($payment->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new PaymentResource($payment->load(['rental.tenant', 'rental.apartment']));
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payments $payment): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        if (!in_array($payment->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'due_date' => ['sometimes', 'required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'check', 'mobile_payment', 'other'])],
            'payment_status' => ['sometimes', 'required', Rule::in(['pending', 'paid', 'partial', 'overdue', 'cancelled'])],
            'payment_type' => ['sometimes', 'required', Rule::in(['rent', 'deposit', 'utility', 'late_fee', 'other'])],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'late_fee' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $payment->update($validated);

        return response()->json([
            'message' => 'Payment updated successfully',
            'data' => new PaymentResource($payment->load('rental')),
        ]);
    }

    /**
     * Mark payment as paid.
     */
    public function markPaid(Request $request, Payments $payment): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

        if (!in_array($payment->rental_id, $rentalIds)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'check', 'mobile_payment', 'other'])],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $payment->update([
            'paid_at' => now(),
            'payment_status' => 'paid',
            'payment_method' => $validated['payment_method'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'note' => $validated['note'] ?? $payment->note,
        ]);

        return response()->json([
            'message' => 'Payment marked as paid',
            'data' => new PaymentResource($payment->load('rental')),
        ]);
    }

    /**
     * Get overdue payments for managed apartments.
     */
    public function overdue(): AnonymousResourceCollection
    {
        $rentalIds = $this->getManagedRentalIds();

        $payments = Payments::whereIn('rental_id', $rentalIds)
            ->where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled')
            ->with(['rental.tenant', 'rental.apartment'])
            ->get();

        return PaymentResource::collection($payments);
    }

    /**
     * Get payment statistics for managed apartments.
     */
    public function statistics(): JsonResponse
    {
        $rentalIds = $this->getManagedRentalIds();

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

        return response()->json([
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_overdue' => $totalOverdue,
        ]);
    }
}
