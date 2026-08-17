@props(['paymentId', 'role', 'confirm'])
{{--
    Undo a payment recorded by mistake.

    Every rent/charge status in the app is derived from the Payments row and
    utilities.paid_status, so this one action walks the statuses back: dropping
    the charges payment takes a "Paid" bill to "Rent Paid" (still pending),
    dropping the rent payment takes it to pending/overdue.

    A DELETE form, so partials/confirm-modal intercepts it — $confirm is the
    message it shows. The server re-checks reversibility (a month can close
    between render and submit); the caller only decides whether to offer it.
--}}
<form method="POST" action="{{ route($role.'.revenue_expense.reverse_payment', $paymentId) }}"
      data-confirm="{{ $confirm }}"
      data-confirm-title="{{ __('messages.reverse_payment') }}"
      data-confirm-ok="{{ __('messages.reverse_payment_ok') }}"
      class="shrink-0">
    @csrf
    @method('DELETE')
    <button type="submit" title="{{ __('messages.reverse_payment') }}" aria-label="{{ __('messages.reverse_payment') }}"
            class="inline-flex items-center justify-center h-7 w-7 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
        </svg>
    </button>
</form>
