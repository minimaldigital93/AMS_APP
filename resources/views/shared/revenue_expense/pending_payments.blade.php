@extends('layouts.'.$panel)

{{--
    The landlord's confirmation queue.

    Rent settles into the landlord's own bank account, which neither this app
    nor NBC's token can see — so a tenant-initiated KHQR session can only be
    closed by a human who has checked their statement. This page is where that
    happens. It contacts nobody: every row here was built locally and costs no
    Bakong allowance to display, however long it sits.
--}}

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">{{ __('messages.pending_tenant_payments') }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ __('messages.pending_tenant_payments_hint') }}</p>
        </div>
        <a href="{{ route($panel.'.revenue_expense.record_income') }}"
           class="text-sm text-indigo-600 hover:text-indigo-800">
            {{ __('messages.back') }}
        </a>
    </div>

    @if($pending->isEmpty())
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-10 text-center">
            <p class="text-slate-500">{{ __('messages.no_pending_tenant_payments') }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($pending as $row)
                @php
                    $payload = $row->checkout_payload ?? [];
                    $isRent = (bool) ($payload['pay_rent'] ?? false);
                    $monthLabel = \Carbon\Carbon::create(
                        (int) ($payload['billing_year'] ?? now()->year),
                        (int) ($payload['billing_month'] ?? now()->month), 1
                    )->format('F Y');
                @endphp

                <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">
                                {{ $row->rental?->tenant?->name ?? __('messages.unknown') }}
                            </p>
                            <p class="text-sm text-slate-500">
                                {{ $row->rental?->apartment?->floor?->property?->name }}
                                @if($row->rental?->apartment)
                                    · {{ __('messages.room') }} {{ $row->rental->apartment->apartment_number }}
                                @endif
                            </p>
                            <p class="text-sm text-slate-600 mt-1">
                                <span class="font-medium">{{ $isRent ? __('messages.monthly_rent') : __('messages.other_charges') }}</span>
                                · {{ $monthLabel }}
                            </p>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ __('messages.started_at') }}: {{ $row->created_at?->format('M j, Y g:i A') }}
                                · <span class="font-mono">{{ $row->transaction_id }}</span>
                            </p>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-2xl font-bold text-slate-900">{{ money($row->amount) }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                {{ __('messages.awaiting_confirmation') }}
                            </span>
                        </div>
                    </div>

                    {{-- Confirming BOOKS the money. The wording has to make the
                         precondition explicit: the landlord is asserting they
                         have seen it arrive, not that the tenant says so. --}}
                    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-2 justify-end">
                        <form method="POST"
                              action="{{ route($panel.'.revenue_expense.pending_reject', $row->transaction_id) }}"
                              onsubmit="return confirm(@js(__('messages.confirm_reject_tenant_payment')))">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2.5 rounded-lg border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50">
                                {{ __('messages.reject') }}
                            </button>
                        </form>
                        <form method="POST"
                              action="{{ route($panel.'.revenue_expense.pending_confirm', $row->transaction_id) }}"
                              onsubmit="return confirm(@js(__('messages.confirm_tenant_payment_received')))">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
                                {{ __('messages.confirm_received') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
