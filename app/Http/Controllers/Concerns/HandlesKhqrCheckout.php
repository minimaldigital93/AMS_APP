<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KhqrPayment;
use App\Models\Rentals;
use App\Models\Utilities;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KHQRPay checkout endpoints shared by Admin and Supervisor controllers.
 *
 * - khqrGenerate(): mint a dynamic KHQR for the selected checkout items.
 * - khqrStatus():   polled by the modal; verifies + finalizes once Bakong pays.
 *
 * The host controller supplies role context via HasFiscalPeriodScope
 * (getActiveFiscalPeriod / ledgerUserId) and the route prefix below.
 */
trait HandlesKhqrCheckout
{
    /** Route-name prefix, e.g. "admin.revenue_expense" / "supervisor.revenue_expense". */
    abstract protected function khqrRoutePrefix(): string;

    public function khqrGenerate(Request $request, KhqrPaymentService $khqr): JsonResponse
    {
        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'rent_amount' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'pay_rent' => 'nullable|boolean',
            'pay_utilities' => 'nullable|boolean',
            'payment_date' => 'required|date',
            'note' => 'nullable|string|max:1000',
        ]);

        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return response()->json(['message' => __('messages.no_fiscal_period') ?? 'No active fiscal period.'], 422);
        }

        $rental = Rentals::with(['apartment', 'tenant'])->findOrFail($validated['rental_id']);

        $payRent = ! empty($validated['pay_rent']);
        $payUtilities = ! empty($validated['pay_utilities']);
        $lateFee = (float) ($validated['late_fee'] ?? 0);

        // Recompute the payable amount server-side so the QR matches exactly what
        // IncomeRecordingService::checkout() will book (rent + late fee + unpaid
        // utilities for the current month). Never trust a client-supplied total.
        $amount = 0.0;
        if ($payRent) {
            $amount += (float) $validated['rent_amount'] + $lateFee;
        }
        if ($payUtilities) {
            $amount += (float) Utilities::where('rental_id', $rental->id)
                ->forMonth(now()->month, now()->year)
                ->unpaid()
                ->sum('charge_amount');
        }

        if ($amount <= 0) {
            return response()->json(['message' => 'No payable items selected.'], 422);
        }

        $payload = [
            'pay_rent' => $payRent,
            'pay_utilities' => $payUtilities,
            'rent_amount' => (float) $validated['rent_amount'],
            'late_fee' => $lateFee,
            'payment_date' => $validated['payment_date'],
            'note' => $validated['note'] ?? null,
        ];

        try {
            $row = $khqr->createQr(
                rental: $rental,
                period: $activePeriod,
                userId: $this->ledgerUserId(),
                amount: $amount,
                payload: $payload,
                successUrl: route($this->khqrRoutePrefix().'.record_income'),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json([
            'transaction_id' => $row->transaction_id,
            'amount' => number_format($row->amount, 2, '.', ''),
            'qr_url' => $row->qr_url,
            'status_url' => route($this->khqrRoutePrefix().'.khqr_status', $row->transaction_id),
        ]);
    }

    public function khqrStatus(string $transactionId, KhqrPaymentService $khqr): JsonResponse
    {
        $row = KhqrPayment::where('transaction_id', $transactionId)->firstOrFail();

        if (! $row->isPaid() && $khqr->verify($row)) {
            $khqr->finalize($row);
            $row->refresh();
        }

        return response()->json([
            'status' => $row->status,
            'paid' => $row->isPaid(),
        ]);
    }
}
