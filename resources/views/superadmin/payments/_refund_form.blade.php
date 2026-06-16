<form method="POST" action="{{ route('superadmin.payments.refund', $payment) }}" class="mt-2 space-y-2 text-left max-w-xs">
    @csrf
    <input type="number" step="0.01" min="0.01" max="{{ number_format($payment->amount, 2, '.', '') }}"
           name="amount" value="{{ number_format($payment->amount, 2, '.', '') }}"
           class="w-full border border-gray-200 rounded px-2 py-1 text-sm" required>
    <input type="text" name="reason" placeholder="{{ __('Reason') }}"
           class="w-full border border-gray-200 rounded px-2 py-1 text-sm" required>
    <input type="text" name="provider_ref" placeholder="{{ __('Bank transfer ref (optional)') }}"
           class="w-full border border-gray-200 rounded px-2 py-1 text-sm">
    <label class="flex items-center gap-2 text-xs text-gray-600">
        <input type="checkbox" name="revoke_access" value="1"> {{ __('Revoke access immediately') }}
    </label>
    <button class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1.5 rounded">{{ __('Record refund') }}</button>
</form>
