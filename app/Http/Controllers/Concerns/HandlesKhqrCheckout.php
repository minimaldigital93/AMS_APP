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
 * Tenant-rent KHQR checkout endpoints shared by Admin and Supervisor controllers.
 *
 * - khqrGenerate(): build a KHQR for the selected checkout items from the
 *   landlord's own Bakong account, and show it.
 * - khqrStatus():   reports a row's status and expiry. PURELY LOCAL — it
 *   contacts nobody, because there is nobody to contact: rent lands in the
 *   landlord's bank, which neither this app nor the platform's Bakong token
 *   can see. The checkout modal no longer polls it (nothing but this same
 *   browser can change the answer); it remains as the read endpoint, and it
 *   still lazily expires a row whose window has closed.
 * - khqrConfirm()/khqrReject(): the landlord resolves it after checking their
 *   banking app. This is the ONLY thing that settles a rent payment.
 *
 * The 'api' channel — a dynamic QR minted and auto-verified at khqr.cc with the
 * landlord's own profile — went with the provider in 2026-09. Rows that used it
 * are still readable; nothing creates a new one.
 *
 * The host controller supplies role context via HasFiscalPeriodScope
 * (getActiveFiscalPeriod / ledgerUserId) and the route prefix below.
 */
trait HandlesKhqrCheckout
{
    /** Route-name prefix, e.g. "admin.revenue_expense" / "supervisor.revenue_expense". */
    abstract protected function khqrRoutePrefix(): string;

    /**
     * Hook for hosts to restrict which rentals may be charged. No-op by default
     * (admin sees the whole account); the supervisor host overrides this to
     * enforce assigned-property scoping.
     */
    protected function authorizeRentalForCheckout(Rentals $rental): void {}

    public function khqrGenerate(Request $request, KhqrPaymentService $khqr): JsonResponse
    {
        $validated = $request->validate([
            'rental_id' => 'required|exists:rentals,id',
            'rent_amount' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'pay_rent' => 'nullable|boolean',
            'pay_utilities' => 'nullable|boolean',
            'payment_date' => ['required', 'date', new \App\Rules\NotInClosedMonth, new \App\Rules\WithinActivePeriod],
            'billing_month' => 'nullable|integer|between:1,12',
            'billing_year' => 'nullable|integer|between:2000,2100',
            'note' => 'nullable|string|max:1000',
        ]);

        $activePeriod = $this->getActiveFiscalPeriod();
        if (! $activePeriod) {
            return response()->json(['message' => __('messages.no_fiscal_period') ?? 'No active fiscal period.'], 422);
        }

        $rental = Rentals::with(['apartment.floor', 'tenant'])->findOrFail($validated['rental_id']);
        $this->authorizeRentalForCheckout($rental);

        $payRent = ! empty($validated['pay_rent']);
        $payUtilities = ! empty($validated['pay_utilities']);
        $lateFee = (float) ($validated['late_fee'] ?? 0);

        // The bill month being settled — sent by the record-income page's month
        // navigation so a bill viewed for March settles MARCH's charges, not
        // whatever month the server clock happens to be in. Falls back to the
        // payment date's month.
        $paymentMonth = \Carbon\Carbon::parse($validated['payment_date']);
        $billingMonth = (int) ($validated['billing_month'] ?? $paymentMonth->month);
        $billingYear = (int) ($validated['billing_year'] ?? $paymentMonth->year);

        // Recompute the payable amount server-side so the QR matches exactly what
        // IncomeRecordingService::checkout() will book (rent + late fee + unpaid
        // utilities for the billing month). Never trust a client-supplied total.
        $amount = 0.0;
        if ($payRent) {
            $amount += (float) $validated['rent_amount'] + $lateFee;
        }
        if ($payUtilities) {
            $amount += (float) Utilities::where('rental_id', $rental->id)
                ->forMonth($billingMonth, $billingYear)
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
            'billing_month' => $billingMonth,
            'billing_year' => $billingYear,
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

        $settings = MerchantPaymentSetting::forAccount($rental->account_id);

        return response()->json([
            'transaction_id' => $row->transaction_id,
            'amount' => number_format($row->amount, 2, '.', ''),
            // A data URI built from the row's own stored payload, so the
            // tenant's browser makes no request for the thing it is about to
            // pay; falls back to the landlord's uploaded static image.
            'qr_url' => $khqr->qrImage($row) ?: $row->qr_url,
            'channel' => $row->channel,
            'status_url' => route($this->khqrRoutePrefix().'.khqr_status', $row->transaction_id),
            'expires_at' => $row->expires_at?->toIso8601String(),
            'confirm_url' => route($this->khqrRoutePrefix().'.khqr_confirm', $row->transaction_id),
            'reject_url' => route($this->khqrRoutePrefix().'.khqr_reject', $row->transaction_id),
            'bank' => [
                'bank_name' => $settings?->bank_name,
                'account_name' => $settings?->bank_account_name,
                'account_number' => $settings?->bank_account_number,
            ],
        ]);
    }

    public function khqrStatus(string $transactionId, KhqrPaymentService $khqr): JsonResponse
    {
        $row = $khqr->pollAndAdvance($this->ownKhqrPayment($transactionId));

        return response()->json([
            'status' => $row->status,
            'paid' => $row->isPaid(),
            // Always false. Kept so the three checkout pages keep one response
            // shape: the rent channel asks no gateway, so no gateway can refuse
            // it. Only the direct Bakong subscription poll can answer true.
            'gateway_error' => $khqr->lastPollRefused(),
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

        $rental = Rentals::with('apartment.floor')->findOrFail($row->rental_id);
        $this->authorizeRentalForCheckout($rental);

        return $row;
    }
}
