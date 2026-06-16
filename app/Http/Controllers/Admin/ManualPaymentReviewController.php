<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\KhqrPayment;
use App\Models\Rentals;
use Illuminate\Contracts\View\View;

/**
 * Manual-channel KHQR payments left unresolved at checkout (e.g. the modal was
 * closed before confirming). The landlord cross-checks their banking app and
 * confirms or rejects each row — the actions reuse the checkout endpoints
 * (admin.revenue_expense.khqr_confirm / khqr_reject).
 */
class ManualPaymentReviewController extends Controller
{
    public function index(): View
    {
        // KhqrPayment carries no account_id — scope through the account's rentals.
        $rentalIds = Rentals::pluck('id');

        $pending = KhqrPayment::where('channel', 'manual')
            ->whereIn('status', PaymentStatus::openValues())
            ->whereIn('rental_id', $rentalIds)
            ->latest('id')
            ->with(['rental.apartment', 'rental.tenant'])
            ->paginate(25);

        return view('admin.payments.manual_pending', ['pending' => $pending]);
    }
}
