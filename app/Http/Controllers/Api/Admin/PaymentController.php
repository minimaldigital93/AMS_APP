<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payments::query();

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

        // Filter by method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('due_date', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('due_date', '<=', $request->get('to_date'));
        }

        // Overdue payments
        if ($request->boolean('overdue_only')) {
            $query->where('due_date', '<', now())
                  ->whereNull('paid_at');
        }

        // Search by reference
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('transaction_reference', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'due_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $payments = $query->with(['rental.tenant', 'rental.apartment'])->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rental_id' => ['required', 'exists:rentals,id'],
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
    public function show(Payments $payment): PaymentResource
    {
        return new PaymentResource($payment->load(['rental.tenant', 'rental.apartment', 'accounts']));
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payments $payment): JsonResponse
    {
        $validated = $request->validate([
            'rental_id' => ['sometimes', 'required', 'exists:rentals,id'],
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
     * Remove the specified payment.
     */
    public function destroy(Payments $payment): JsonResponse
    {
        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }

    /**
     * Mark payment as paid.
     */
    public function markPaid(Request $request, Payments $payment): JsonResponse
    {
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
     * Get overdue payments.
     */
    public function overdue(Request $request): AnonymousResourceCollection
    {
        $query = Payments::where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled');

        $perPage = $request->get('per_page', 15);
        $payments = $query->with(['rental.tenant', 'rental.apartment'])->paginate($perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * Get payment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = Payments::query();

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('due_date', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('due_date', '<=', $request->get('to_date'));
        }

        $totalPaid = (clone $query)->where('payment_status', 'paid')->sum('amount');
        $totalPending = (clone $query)->where('payment_status', 'pending')->sum('amount');
        $totalOverdue = (clone $query)->where('due_date', '<', now())
            ->whereNull('paid_at')
            ->where('payment_status', '!=', 'cancelled')
            ->sum('amount');
        $totalLateFees = (clone $query)->sum('late_fee');

        return response()->json([
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'total_overdue' => $totalOverdue,
            'total_late_fees' => $totalLateFees,
        ]);
    }
}
