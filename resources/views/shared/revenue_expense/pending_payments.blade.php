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

    {{-- Not shown when it's simply "not set up yet" — an empty queue full of
         manual rows already says that. It speaks up only when auto-confirm is
         active (so the rows below need a second explanation) or when it's
         SUPPOSED to be active but a platform-level switch is quietly stopping
         it, which nothing else on this page would ever reveal. --}}
    @unless($bakongDiagnosis['reason'] === 'not_configured')
        <div @class([
                'rounded-lg border px-4 py-3 text-xs flex flex-wrap items-center justify-between gap-2',
                'bg-emerald-50 border-emerald-200 text-emerald-700' => $bakongDiagnosis['active'],
                'bg-amber-50 border-amber-200 text-amber-700' => ! $bakongDiagnosis['active'],
            ])>
            <span>
                @if($bakongDiagnosis['active'])
                    {{ __('messages.bakong_diag_active') }}
                @else
                    {{ __('messages.pending_row_needs_setup') }} {{ __('messages.bakong_diag_'.$bakongDiagnosis['reason']) }}
                @endif
            </span>
            @if(! $bakongDiagnosis['active'])
                @if($bakongSettingsUrl)
                    <a href="{{ $bakongSettingsUrl }}" class="font-medium underline shrink-0">{{ __('messages.go_to_payment_settings') }}</a>
                @else
                    <span class="shrink-0">{{ __('messages.pending_row_ask_owner') }}</span>
                @endif
            @endif
        </div>
    @endunless

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
                            @if($row->channel === 'api')
                                <span class="block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                    {{ __('messages.pending_row_auto_checking') }}
                                </span>
                            @elseif($bakongDiagnosis['active'])
                                {{-- channel was decided once, at mint time — a row started
                                     before auto-confirm was switched on stays manual forever,
                                     even though new sessions now confirm themselves. --}}
                                <p class="mt-1 text-xs text-slate-400 max-w-[14rem]">
                                    {{ __('messages.pending_row_started_before_auto') }}
                                </p>
                            @endif
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
