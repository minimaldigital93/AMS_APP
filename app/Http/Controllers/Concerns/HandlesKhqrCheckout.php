<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KhqrPayment;
use App\Models\MerchantPaymentSetting;
use App\Models\Rentals;
use App\Models\Utilities;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KHQR checkout endpoints shared by Admin and Supervisor controllers.
 *
 * - khqrGenerate(): create a payment for the selected checkout items. Channel
 *   depends on the landlord's payment settings: 'api' (KHQRPay dynamic QR,
 *   auto-verified) or 'manual' (static KHQR / bank details, landlord confirms).
 * - khqrStatus():   polled by the modal; verifies + finalizes once Bakong pays.
 * - khqrConfirm()/khqrReject(): manual-channel resolution by the landlord.
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
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $response = [
            'transaction_id' => $row->transaction_id,
            'amount' => number_format($row->amount, 2, '.', ''),
            'qr_url' => $row->qr_url,
            'channel' => $row->channel,
            'status_url' => route($this->khqrRoutePrefix().'.khqr_status', $row->transaction_id),
        ];

        if ($row->channel === 'manual') {
            $settings = MerchantPaymentSetting::forAccount($rental->account_id);
            $response['confirm_url'] = route($this->khqrRoutePrefix().'.khqr_confirm', $row->transaction_id);
            $response['reject_url'] = route($this->khqrRoutePrefix().'.khqr_reject', $row->transaction_id);
            $response['bank'] = [
                'bank_name' => $settings?->bank_name,
                'account_name' => $settings?->bank_account_name,
                'account_number' => $settings?->bank_account_number,
            ];
        }

        return response()->json($response);
    }

    public function khqrStatus(string $transactionId, KhqrPaymentService $khqr): JsonResponse
    {
        $row = $khqr->pollAndAdvance($this->ownKhqrPayment($transactionId));

        return response()->json([
            'status' => $row->status,
            'paid' => $row->isPaid(),
            'expires_at' => $row->expires_at?->toIso8601String(),
        ]);
    }

    /** Landlord confirms a manual-channel payment after checking their bank app. */
    public function khqrConfirm(string $transactionId, KhqrPaymentService $khqr): JsonResponse
    {
        $row = $this->ownKhqrPayment($transactionId);

        $khqr->confirmManual($row);
        $row->refresh();

        return response()->json([
            'status' => $row->status,
            'paid' => $row->isPaid(),
        ]);
    }

    /** Landlord rejects a manual-channel payment (money never arrived). */
    public function khqrReject(string $transactionId, KhqrPaymentService $khqr): JsonResponse
    {
        $row = $this->ownKhqrPayment($transactionId);

        $khqr->rejectManual($row);
        $row->refresh();

        return response()->json(['status' => $row->status, 'paid' => false]);
    }

    /**
     * Resolve a rent KhqrPayment and assert it belongs to the current account —
     * the rental lookup runs under the account global scope, so a foreign
     * account's transaction 404s instead of leaking status.
     */
    private function ownKhqrPayment(string $transactionId): KhqrPayment
    {
        $row = KhqrPayment::where('transaction_id', $transactionId)
            ->whereNotNull('rental_id')
            ->firstOrFail();

        Rentals::findOrFail($row->rental_id);

        return $row;
    }
}
