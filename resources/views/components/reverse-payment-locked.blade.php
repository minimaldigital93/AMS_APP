@props(['reason', 'reopenUrl' => null, 'monthName' => null])
{{--
    Why this payment carries no undo button.

    PaymentReversalService refuses for four reasons, and until now every one of
    them just removed the button. On a bill collected across two visits that
    reads as the app disagreeing with itself: August's rent is handed over in
    August and its charges at the turn of September, so once August is closed
    the rent row loses its undo while the charges row — booked in September —
    keeps one. The rule is right (closed money is never restated); a missing
    button simply never said so, nor that reopening the month is the way
    through.

    Same shape as the in-use expense category's lock: the control stays, and
    explains itself through the shared dialog (partials/confirm-modal). When
    the viewer is an admin and a closed month is what blocks it, OK goes to
    that month's page — where Reopen lives. A supervisor gets the explanation
    and is told to ask the owner, the same split the month-close banner makes.
--}}
@php
    $lockedMessage = __('messages.flash_payment_reverse_blocked_'.$reason);
    if ($reason === \App\Services\RevenueExpense\PaymentReversalService::REASON_CLOSED_MONTH
        && ! $reopenUrl && $monthName) {
        $lockedMessage .= ' '.__('messages.reverse_payment_ask_owner', ['month' => $monthName]);
    }
@endphp
<button type="button"
        data-reversal-locked
        data-title="{{ __('messages.reverse_payment_locked') }}"
        data-message="{{ $lockedMessage }}"
        @if($reopenUrl)
            data-href="{{ $reopenUrl }}"
            data-ok="{{ __('messages.reverse_payment_reopen_month', ['month' => $monthName]) }}"
        @endif
        title="{{ __('messages.reverse_payment_locked') }}" aria-label="{{ __('messages.reverse_payment_locked') }}"
        class="shrink-0 inline-flex items-center justify-center h-7 w-7 rounded-lg text-slate-300 hover:text-slate-500 hover:bg-slate-100 transition">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
    </svg>
</button>
@once
<script>
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-reversal-locked]');
        if (!btn) return;

        var title = btn.getAttribute('data-title');
        var message = btn.getAttribute('data-message');
        var href = btn.getAttribute('data-href');

        if (!href) {
            window.amsAlert(message, { title: title });
            return;
        }

        window.confirmAction({
            title: title,
            message: message,
            okLabel: btn.getAttribute('data-ok')
        }).then(function (ok) {
            if (ok) { window.location.href = href; }
        });
    });
})();
</script>
@endonce
