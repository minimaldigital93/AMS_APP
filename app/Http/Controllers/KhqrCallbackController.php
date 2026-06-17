<?php

namespace App\Http\Controllers;

use App\Services\Payment\WebhookIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound webhook from KHQRPay (khqr.cc). Unauthenticated + CSRF-exempt — the
 * payload is authenticated by its signature. All persistence, dedupe, freshness
 * and signature handling lives in WebhookIngestService; this stays a one-liner.
 *
 * A duplicate/replayed delivery is acked with 200 (so the provider stops
 * retrying); an unknown transaction or bad signature returns 403 with the same
 * body either way, so the endpoint never reveals which transaction ids exist.
 */
class KhqrCallbackController extends Controller
{
    public function __invoke(Request $request, WebhookIngestService $webhooks): JsonResponse
    {
        // Arrival breadcrumb so a live test can confirm the gateway is actually
        // hitting this endpoint (tail: `grep "KHQRPay webhook" storage/logs/laravel.log`).
        // No secret is logged — only the fields the gateway sends in the clear.
        Log::info('KHQRPay webhook received', [
            'transaction' => $request->input('transaction_id'),
            'status' => $request->input('status'),
            'amount' => $request->input('amount'),
            'ip' => $request->ip(),
        ]);

        $result = $webhooks->ingest($request->all());

        Log::info('KHQRPay webhook handled', [
            'transaction' => $request->input('transaction_id'),
            'http_status' => $result['status'],
        ]);

        return response()->json($result['body'], $result['status']);
    }
}
