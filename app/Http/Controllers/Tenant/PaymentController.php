<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\FiscalPeriods;
use App\Models\KhqrPayment;
use App\Models\Payments;
use App\Models\Rentals;
use App\Models\Tenants;
use App\Services\Bakong\TenantPaymentVerifier;
use App\Services\RevenueExpense\KhqrPaymentService;
use App\Services\Tenants\TenantObligationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The tenant's own view of what they owe, and the entry point to paying it.
 *
 * ISOLATION. Nothing here takes a tenant id, rental id or amount from the
 * request. The tenancy is resolved from the session user every time
 * (`resolveRental()`), and the money is re-derived server-side from that
 * rental — so there is no identifier for a tenant to swap and no figure for
 * them to edit. The month IS taken from the URL, which is why it is validated
 * against the tenancy rather than trusted: a month outside it resolves to an
 * obligation with nothing outstanding, not to another tenant's bill.
 *
 * Account scope comes for free: a tenant User carries the landlord's
 * account_id, so BelongsToAccount resolves every query inside that account.
 * That isolates ACCOUNTS from each other, not tenants within one account —
 * hence the explicit ownership resolution here.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly TenantObligationService $obligations) {}

    /** The obligations list: what is due now, what is still owed from before. */
    public function index(): View
    {
        $tenant = $this->resolveTenant();
        $rental = $tenant ? $this->resolveRental($tenant) : null;

        $obligations = $rental ? $this->obligations->outstandingFor($rental) : [];

        $totalOutstanding = collect($obligations)->sum('total_outstanding');

        $history = $rental
            ? Payments::where('rental_id', $rental->id)
                ->where('payment_status', 'paid')
                ->orderByDesc('paid_at')
                ->limit(24)
                ->get()
            : collect();

        return view('tenant.payments.index', [
            'tenant' => $tenant,
            'rental' => $rental,
            'obligations' => $obligations,
            'totalOutstanding' => round((float) $totalOutstanding, 2),
            'history' => $history,
        ]);
    }

    /**
     * One side of one month's bill, spelled out before any QR exists.
     *
     * $side is 'rent' or 'charges' — the two sides a bill settles on, matching
     * what checkout() already books. The tenant confirms what they are paying
     * here; nothing is minted and nobody is contacted by loading this page.
     */
    public function show(string $side, int $year, int $month): View
    {
        abort_unless(in_array($side, ['rent', 'charges'], true), 404);
        abort_unless($month >= 1 && $month <= 12, 404);

        $tenant = $this->resolveTenant();
        $rental = $tenant ? $this->resolveRental($tenant) : null;

        if (! $rental) {
            throw new NotFoundHttpException;
        }

        $obligation = $this->obligations->forRental($rental, $month, $year);

        return view('tenant.payments.show', [
            'tenant' => $tenant,
            'rental' => $rental,
            'side' => $side,
            'obligation' => $obligation,
            'amount' => $this->amountFor($obligation, $side),
            'payable' => $this->isPayable($obligation, $side),
        ]);
    }

    /**
     * What this side is worth, derived — never read from the request.
     *
     * The rent side deliberately excludes the suggested late fee: that figure
     * is the landlord's to set at collection (it is editable on their checkout
     * form), so quoting it to a tenant as owed would state a number nobody has
     * decided yet.
     */
    private function amountFor(array $obligation, string $side): float
    {
        return $side === 'rent'
            ? (float) $obligation['rent_outstanding']
            : (float) $obligation['unpaid_charge_total'];
    }

    /**
     * A side is payable only while it is genuinely open. An upcoming tenancy
     * has nothing incurred, a settled side has nothing left, and a zero amount
     * is not a payment — each of those must refuse rather than mint a QR for
     * nothing.
     */
    private function isPayable(array $obligation, string $side): bool
    {
        if ($obligation['is_upcoming']) {
            return false;
        }

        return $this->amountFor($obligation, $side) > 0;
    }

    /**
     * Mint (or re-use) the KHQR session for one side of one month.
     *
     * Everything that decides money is derived here: the amount comes from
     * TenantObligationService, the billed month from the URL segment that was
     * validated against the tenancy, and the payment date from the server
     * clock. The request body contributes nothing at all — there is deliberately
     * no amount, rental or tenant field to tamper with.
     *
     * Creating the QR contacts nobody and costs no Bakong allowance: the rent
     * channel builds its EMV payload locally and the landlord confirms it.
     */
    public function pay(string $side, int $year, int $month, KhqrPaymentService $khqr): RedirectResponse
    {
        abort_unless(in_array($side, ['rent', 'charges'], true), 404);
        abort_unless($month >= 1 && $month <= 12, 404);

        $tenant = $this->resolveTenant();
        $rental = $tenant ? $this->resolveRental($tenant) : null;

        if (! $rental) {
            throw new NotFoundHttpException;
        }

        $obligation = $this->obligations->forRental($rental, $month, $year);
        $amount = $this->amountFor($obligation, $side);

        if (! $this->isPayable($obligation, $side)) {
            return redirect()
                ->route('tenant.payments.index')
                ->with('warning', __('messages.tenant_pay_nothing_due'));
        }

        // Already in flight: hand back the SAME session rather than minting a
        // second QR for the same debt. Two live QRs for one obligation is how a
        // tenant pays twice.
        $existing = $this->openSessionFor($rental, $side, $month, $year);
        if ($existing) {
            return redirect()->route('tenant.payments.qr', $existing->transaction_id);
        }

        $period = FiscalPeriods::where('user_id', current_account_id())
            ->where('status', 'open')
            ->orderByDesc('opening_date')
            ->first();

        if (! $period) {
            return redirect()
                ->route('tenant.payments.index')
                ->with('warning', __('messages.tenant_pay_unavailable'));
        }

        try {
            $row = $khqr->createQr($rental, $period, current_account_id(), $amount, [
                'pay_rent' => $side === 'rent',
                'pay_utilities' => $side === 'charges',
                'rent_amount' => $side === 'rent' ? $amount : 0,
                // The late fee is the landlord's to set at collection, so a
                // tenant-initiated payment never carries one.
                'late_fee' => 0,
                'payment_date' => now()->toDateString(),
                'billing_month' => $month,
                'billing_year' => $year,
                'note' => __('messages.tenant_initiated_payment'),
            ]);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('tenant.payments.index')
                ->with('warning', __('messages.tenant_pay_unavailable'));
        }

        // Stamped after minting because createQr() owns the row's creation.
        // This is what puts the session in the landlord's confirmation queue —
        // a payment nobody can see is a payment nobody will settle.
        $row->forceFill(['initiated_by_user_id' => Auth::id()])->save();

        return redirect()->route('tenant.payments.qr', $row->transaction_id);
    }

    /** The QR itself, always captioned with what it is for. */
    public function qr(string $transaction, KhqrPaymentService $khqr, TenantPaymentVerifier $verifier): View
    {
        $row = $this->ownedSession($transaction);
        $khqr->expireIfElapsed($row);

        $payload = $row->checkout_payload ?? [];
        $side = ($payload['pay_rent'] ?? false) ? 'rent' : 'charges';

        return view('tenant.payments.qr', [
            'payment' => $row,
            'side' => $side,
            // Whether this page is actually watching for the money, or whether
            // only the landlord can confirm it. The page says one or the other
            // and must not guess.
            'auto' => $verifier->isAutomatic($row),
            'qrImage' => $khqr->qrImage($row),
            'monthLabel' => Carbon::create(
                (int) ($payload['billing_year'] ?? now()->year),
                (int) ($payload['billing_month'] ?? now()->month),
                1
            )->format('F Y'),
            'rental' => $row->rental,
        ]);
    }

    /**
     * Local status only. This endpoint contacts nobody — the rent channel is
     * confirmed by the landlord, so polling it costs nothing and can be done as
     * often as the page likes.
     */
    public function status(
        string $transaction,
        KhqrPaymentService $khqr,
        TenantPaymentVerifier $verifier,
    ): JsonResponse {
        $row = $this->ownedSession($transaction);
        $khqr->expireIfElapsed($row);

        // Ask the landlord's bank, but only when the landlord has switched
        // that on and given us a credential of their own. With it off this
        // stays exactly what it was — a local read that contacts nobody.
        $row = $verifier->attempt($row);

        return response()->json([
            'status' => $row->status,
            'paid' => $row->status === PaymentStatus::Paid->value,
            'auto' => $verifier->isAutomatic($row),
            'gateway_error' => $verifier->lastRefused(),
            'expires_at' => $row->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * An open KHQR session for this exact obligation, if one is already live.
     *
     * The side and month live in checkout_payload rather than in columns, so
     * the match is made in PHP over the rental's open rows — there are only
     * ever a handful, and a JSON predicate would not be portable across the
     * MySQL/SQLite split this project runs on.
     */
    private function openSessionFor(Rentals $rental, string $side, int $month, int $year): ?KhqrPayment
    {
        return KhqrPayment::where('rental_id', $rental->id)
            ->whereIn('status', PaymentStatus::openValues())
            ->orderByDesc('id')
            ->get()
            ->first(function (KhqrPayment $row) use ($side, $month, $year) {
                $p = $row->checkout_payload ?? [];

                return (int) ($p['billing_month'] ?? 0) === $month
                    && (int) ($p['billing_year'] ?? 0) === $year
                    && (bool) ($p['pay_rent'] ?? false) === ($side === 'rent');
            });
    }

    /**
     * A KHQR row this tenant is actually entitled to see.
     *
     * KhqrPayment carries no account scope and no tenant id, so this is the
     * ONLY thing standing between a tenant and another tenant's payment page:
     * the row's rental must resolve, under the account scope, to a tenancy
     * belonging to the signed-in tenant. A miss is a 404, not a 403 — a tenant
     * has no business learning that someone else's transaction id exists.
     */
    private function ownedSession(string $transaction): KhqrPayment
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            throw new NotFoundHttpException;
        }

        $row = KhqrPayment::where('transaction_id', $transaction)
            ->whereNotNull('rental_id')
            ->first();

        if (! $row) {
            throw new NotFoundHttpException;
        }

        $rental = Rentals::where('id', $row->rental_id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $rental) {
            throw new NotFoundHttpException;
        }

        $row->setRelation('rental', $rental);

        return $row;
    }

    private function resolveTenant(): ?Tenants
    {
        return Tenants::where('user_id', Auth::id())
            ->whereIn('status', ['active', 'pending'])
            ->with(['apartment.floor.property'])
            ->first();
    }

    /**
     * The tenancy this tenant is actually living in. Newest tenancy that has
     * begun, else the earliest future one — the same "one rental per room"
     * rule the collection page applies, so the two agree on which tenancy a
     * turnover month belongs to.
     */
    private function resolveRental(Tenants $tenant): ?Rentals
    {
        $rentals = Rentals::where('tenant_id', $tenant->id)
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now()->startOfMonth());
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return $rentals->first(fn ($r) => ! $r->start_date || $r->start_date->lte(now()))
            ?? $rentals->last();
    }
}
