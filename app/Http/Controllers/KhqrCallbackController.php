<?php

namespace App\Http\Controllers;

use App\Services\Payment\WebhookIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $result = $webhooks->ingest($request->all());

        return response()->json($result['body'], $result['status']);
    }
}
