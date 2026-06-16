<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\KhqrPayment;
use App\Models\PaymentWebhook;
use App\Models\Refund;
use App\Services\Payment\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Platform payments console (super admin): subscription payment transactions,
 * recent webhook deliveries, recorded refunds — and the action to record a
 * refund. Cross-account by design (the SaaS layer), so no account scoping.
 */
class PaymentsController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(): View
    {
        $payments = KhqrPayment::where('settlement_target', 'platform')
            ->with(['subscription.plan', 'subscription.account'])
            ->latest('id')
            ->paginate(30);

        $webhooks = PaymentWebhook::latest('id')->limit(20)->get();
        $recentRefunds = Refund::with('payment')->latest('id')->limit(20)->get();

        return view('superadmin.payments.index', [
            'payments' => $payments,
            'webhooks' => $webhooks,
            'recentRefunds' => $recentRefunds,
        ]);
    }

    public function refund(Request $request, KhqrPayment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'provider_ref' => ['nullable', 'string', 'max:255'],
            'revoke_access' => ['nullable', 'boolean'],
        ]);

        try {
            $this->refunds->record(
                payment: $payment,
                amount: (float) $validated['amount'],
                reason: $validated['reason'],
                providerRef: $validated['provider_ref'] ?? null,
                revokeAccess: $request->boolean('revoke_access'),
                actor: $request->user(),
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Refund recorded.'));
    }
}
