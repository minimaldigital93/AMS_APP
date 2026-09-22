{{--
    System Settings — a list of links, one per card of the old single form.
    Every section lives on its own page now (the shape Expense Categories and
    Default Utility Prices already had); each of those pages posts its own
    fields back to the same updateBatch() route, so a save does what it always
    did. System Preferences stays inline: currency is two rows, not a page.

    Payment Settings is a row here too. It had its own sidebar entry beside
    System Settings until then, which made "where rent lands" the one setting
    you could not find by opening Settings — and left the nav needing a
    not-payment exclusion on every other settings page to stop both entries
    lighting up at once.

    Billing & Subscription followed it in, for the same reason and into a card
    of its own: the rows above are how THIS BUSINESS bills its tenants, and the
    account's own plan is a different question that only happened to share the
    word. It is the one row that leaves the settings routes (it opens
    admin.billing.index, which is exempt from the subscription gate — see
    EnsureSubscriptionActive), so its page keeps its back arrow pointing here.
--}}
@extends('layouts.admin')

@section('title', __('messages.settings_title'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.settings_title') }}</h1>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm" role="alert">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            // Each row: the page it opens and its line icon. Split into two
            // cards the way the form was grouped — who the business is, then
            // how it bills.
            $linkGroups = [
                [
                    [
                        'route' => 'admin.settings.general',
                        'label' => __('messages.general_settings'),
                        'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                    ],
                    [
                        'route' => 'admin.settings.company',
                        'label' => __('messages.company_information'),
                        'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
                    ],
                    [
                        'route' => 'admin.settings.owner',
                        'label' => __('messages.owner_information'),
                        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                    ],
                ],
                [
                    [
                        'route' => 'admin.settings.billing',
                        'label' => __('messages.billing_late_fee_settings'),
                        'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    ],
                    [
                        'route' => 'admin.settings.expense_categories',
                        'label' => __('messages.expense_categories'),
                        'icon' => 'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z',
                    ],
                    [
                        'route' => 'admin.settings.utility_prices',
                        'label' => __('messages.default_utility_prices'),
                        'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                    ],
                    [
                        'route' => 'admin.settings.payment',
                        'label' => __('messages.payment_settings'),
                        'icon' => 'M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    ],
                    [
                        'route' => 'admin.settings.payment_qr',
                        'label' => __('messages.payment_qr_code'),
                        'icon' => 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Zm0 9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm9.75-9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm0 9.75h3m3 0h-.008v.008H19.5v-.008Zm-3 3h.008v.008H16.5v-.008Zm3 3h.008v.008H19.5v-.008Zm-3 0h.008v.008H16.5v-.008Zm-3-3h.008v.008H13.5v-.008Zm0 3h.008v.008H13.5v-.008Z',
                    ],
                ],
                [
                    [
                        'route' => 'admin.billing.index',
                        'label' => __('messages.billing_subscription'),
                        'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                    ],
                ],
            ];
        @endphp

        @foreach($linkGroups as $rows)
        <div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                @foreach($rows as $row)
                <a href="{{ route($row['route']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                    <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $row['icon'] }}" />
                    </svg>
                    <p class="min-w-0 text-[15px] text-gray-900">{{ $row['label'] }}</p>
                    <svg class="ml-auto flex-shrink-0 w-4 h-4 text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- System Preferences is two rows, so it stays on the page rather than
             becoming a link to a page holding a single select. --}}
        <form method="POST" action="{{ route('admin.settings.updateBatch') }}" class="space-y-8">
            @csrf
            @method('PUT')

            <div>
                <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.system_preferences') }}</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100" x-data="{ currency: @js(settings('system_currency', 'USD')) }">
                    @foreach($defaultSettings['system'] as $key => $defaultValue)
                        @include('admin.settings.partials.field-row', [
                            'key' => $key,
                            'value' => old("settings.$key", $settings->flatten()->firstWhere('key', $key)->value ?? $defaultValue),
                        ])
                    @endforeach
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white font-semibold py-2.5 px-8 rounded-full transition duration-200 flex items-center gap-2 shadow-sm" title="{{ __('messages.save_all_settings') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                    </svg></button>
            </div>
        </form>

        <!-- Reset Group (destructive row, iOS style) -->
        <div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <button type="button" onclick="confirmReset()"
                    class="w-full px-4 py-3 text-center text-[15px] font-medium text-red-600 hover:bg-red-50 active:bg-red-100 transition duration-150">
                    {{ __('messages.reset_all') }}
                </button>
            </div>
        </div>

    </div>
</div>

<!-- Reset Confirmation Form -->
<form id="resetForm" method="POST" action="{{ route('admin.settings.reset') }}" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function confirmReset() {
    window.confirmAction({ message: '{{ __('messages.reset_confirm') }}' }).then(function (ok) {
        if (ok) document.getElementById('resetForm').submit();
    });
}
</script>
@endsection
