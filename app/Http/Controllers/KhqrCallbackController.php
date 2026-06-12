<?php

namespace App\Http\Controllers;

use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound webhook from KHQRPay (khqr.cc). Unauthenticated + CSRF-exempt — the
 * payload is authenticated by its signature. The row is looked up FIRST so the
 * signature can be checked against the right secret (platform for subscription
 * payments, the landlord's own for rent payments) and the amount/currency can
 * be matched against what the QR was minted for. On a valid callback we
 * finalize the matching KhqrPayment (idempotent with the status poll).
 */
class KhqrCallbackController extends Controller
{
    public function __invoke(Request $request, KhqrPaymentService $khqr): JsonResponse
    {
        $payload = $request->all();

        $transactionId = $payload['transaction_id'] ?? null;
        $row = $transactionId ? KhqrPayment::where('transaction_id', $transactionId)->first() : null;

        if (! $row) {
            // Same response as a bad signature — don't leak which IDs exist.
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        if (! $khqr->isValidCallbackFor($row, $payload)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $khqr->finalize($row);

        return response()->json(['ok' => true]);
    }
}
