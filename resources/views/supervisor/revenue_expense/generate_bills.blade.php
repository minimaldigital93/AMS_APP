@extends('layouts.supervisor')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.generate_monthly_bills') }}</h1>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('supervisor.revenue_expense.fixed_expenses') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm" title="{{ __('messages.manage_apartment_costs') }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></a>
            <a href="{{ route('supervisor.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-sm" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </div>

    <!-- Messages -->
    @if($errors->any())
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if(count($billSummary) > 0)
    <form action="{{ route('supervisor.revenue_expense.process_bills') }}" method="POST" id="billForm">
        @csrf

        <!-- Billing Settings -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div>
                        <label for="billing_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.billing_date') }}</label>
                        <input type="date" name="billing_date" id="billing_date" required value="{{ date('Y-m-d') }}"
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white appearance-none h-10">
                    </div>
                    <div class="text-center">
                        <p class="text-sm text-gray-500">{{ __('messages.total_monthly_apt_costs') }}</p>
                        <p class="text-2xl font-bold text-red-600">{{ money($totalMonthlyExpenses) }}</p>
                    </div>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="selectAllBills" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500" checked>
                        <span class="text-sm font-medium text-gray-700">{{ __('messages.select_all_apartments') }}</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Per-Apartment Bill Cards -->
        <div class="space-y-4 mb-8">
            @foreach($billSummary as $billIndex => $bill)
            <div class="bg-white rounded-lg shadow-md overflow-hidden {{ $bill['has_unbilled'] ? '' : 'opacity-60' }}">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div class="flex items-center gap-4">
                        <input type="hidden" name="bills[{{ $billIndex }}][rental_id]" value="{{ $bill['rental']->id }}">
                        <input type="checkbox" name="bills[{{ $billIndex }}][selected]" value="1"
                            class="bill-checkbox w-5 h-5 text-blue-600 rounded focus:ring-blue-500 cursor-pointer"
                            {{ $bill['has_unbilled'] ? 'checked' : '' }}>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                {{ $bill['apartment']->apartment_number }}
                                <span class="text-sm font-normal text-gray-500">— {{ __('messages.floor') }} {{ $bill['apartment']->floor->floor_number ?? 'N/A' }}</span>
                            </h3>
                            <p class="text-sm text-gray-600">
                                {{ __('messages.tenant') }}: <span class="font-medium">{{ $bill['tenant_name'] }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">{{ __('messages.total_monthly_bill') }}</p>
                        <p class="text-xl font-bold text-red-600">{{ money($bill['total_bill']) }}</p>
                        <p class="text-xs text-gray-400">{{ __('messages.rent') }}: {{ money($bill['monthly_rent']) }} + {{ __('messages.costs') }}: {{ money($bill['total_fixed']) }}</p>
                    </div>
                </div>

                <!-- Expense Items -->
                <div class="p-4">
                    @if(count($bill['fixed_expenses']) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($bill['fixed_expenses'] as $expIndex => $expense)
                        <div class="border rounded-lg p-3 {{ $expense['is_billed'] ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
                            <input type="hidden" name="bills[{{ $billIndex }}][expenses][{{ $expIndex }}][expense_id]" value="{{ $expense['id'] }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="bills[{{ $billIndex }}][expenses][{{ $expIndex }}][selected]" value="1"
                                        class="expense-checkbox w-4 h-4 text-blue-600 rounded focus:ring-blue-500 cursor-pointer"
                                        {{ $expense['is_billed'] ? 'disabled' : 'checked' }}>
                                    @php
                                        $icons = ['parking' => '🚗', 'internet' => '📡', 'trash' => '🗑️', 'other' => '📋'];
                                    @endphp
                                    <span class="text-sm font-medium text-gray-800">
                                        {{ $icons[$expense['type']] ?? '📋' }} {{ $expense['name'] }}
                                    </span>
                                </div>
                                @if($expense['is_billed'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>{{ __('messages.billed') }}</span>
                                @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">{{ __('messages.pending') }}</span>
                                @endif
                            </div>
                            <div>
                                <input type="number" name="bills[{{ $billIndex }}][expenses][{{ $expIndex }}][amount]"
                                    step="0.01" min="0" value="{{ $expense['amount'] }}"
                                    class="w-full px-2 py-1 text-sm text-right border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-semibold text-red-600"
                                    {{ $expense['is_billed'] ? 'readonly' : '' }}>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-gray-400 text-center py-2">{{ __('messages.no_apt_costs_assigned') }} <a href="{{ route('supervisor.revenue_expense.fixed_expenses') }}" class="text-blue-600 underline">{{ __('messages.add_some') }}</a></p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        <!-- Generate Button -->
        <div class="flex items-center justify-between bg-white rounded-lg shadow-md p-6">
            <div>
                <p class="text-sm text-gray-500" id="billSelectedCount">{{ __('messages.apartments_selected', ['count' => count($billSummary)]) }}</p>
                <p class="text-xs text-gray-400">{{ __('messages.only_unbilled_note') }}</p>
            </div>
            <button type="submit" class="inline-flex items-center px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium shadow-md text-lg" title="{{ __('messages.generate_monthly_expenses') }}">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg></button>
        </div>
    </form>
    @else
    <div class="bg-white rounded-lg shadow-md p-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        <h3 class="text-xl font-bold text-gray-700 mb-2">{{ __('messages.no_active_rentals') }}</h3>
        <p class="text-gray-500 mb-4">{{ __('messages.no_active_rentals_desc') }}</p>
        <div class="flex justify-center gap-4">
            <a href="{{ route('supervisor.revenue_expense.fixed_expenses') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">{{ __('messages.setup_apt_costs') }}</a>
            <a href="{{ route('supervisor.apartments.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">{{ __('messages.manage_apartments') }}</a>
        </div>
    </div>
    @endif
</div>

<script>
    // Select All Bills
    const selectAllBills = document.getElementById('selectAllBills');
    const billCheckboxes = document.querySelectorAll('.bill-checkbox');

    if (selectAllBills) {
        selectAllBills.addEventListener('change', function () {
            billCheckboxes.forEach(cb => { cb.checked = this.checked; });
            updateBillCount();
        });

        billCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                selectAllBills.checked = [...billCheckboxes].every(c => c.checked);
                selectAllBills.indeterminate = !selectAllBills.checked && [...billCheckboxes].some(c => c.checked);
                updateBillCount();
            });
        });
    }

    function updateBillCount() {
        const count = [...billCheckboxes].filter(c => c.checked).length;
        document.getElementById('billSelectedCount').textContent = '{{ __('messages.apartments_selected', ['count' => '__N__']) }}'.replace('__N__', count);
    }
</script>
@endsection
