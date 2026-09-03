{{--
    Why there is no close button here.

    Closing a month freezes ACCOUNT-WIDE totals and carries an account-wide
    balance forward, so it is offered only in the consolidated view — a
    single-property page shows a per-property running total (see
    FiscalPeriodController::monthBalances), and closing from there would freeze
    figures the operator was not looking at.

    That gate is right; hiding it in silence was not, and the dashboard banner
    now sends people here asking for the close by name. So say it, and offer the
    one click that fixes it — property.switch redirects back, so the button
    appears on this same page.
--}}
<div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div class="flex items-start gap-2.5">
        <svg class="w-4 h-4 shrink-0 mt-0.5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10A8 8 0 112 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <span>{{ __('messages.month_close_needs_all_properties') }}</span>
    </div>
    <form method="POST" action="{{ route('property.switch') }}" class="shrink-0">
        @csrf
        <input type="hidden" name="property_id" value="{{ \App\Services\Property\PropertyContext::ALL_PROPERTIES }}">
        <button type="submit"
                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white bg-slate-700 hover:bg-slate-800 transition">
            {{ __('messages.switch_to_all_properties') }}
        </button>
    </form>
</div>
