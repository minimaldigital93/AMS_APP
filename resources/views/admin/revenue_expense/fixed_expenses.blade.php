@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ __('messages.apartment_cost_management') }}</h1>
            <p class="text-gray-600 mt-2">{{ __('messages.assign_recurring_subtitle') }}</p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>{{ __('messages.back') }}</a>
    </div>

    <!-- Messages -->
    @if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Apartments with Costs -->
        <div class="lg:col-span-2 space-y-6">
            @forelse($apartments as $apartment)
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4 pb-3 border-b-2 border-blue-500">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">
                            {{ $apartment->apartment_number }}
                            <span class="text-sm font-normal text-gray-500 ml-2">{{ __('messages.floor') }} {{ $apartment->floor->floor_number ?? 'N/A' }}</span>
                        </h2>
                        @if($apartment->rentals->isNotEmpty())
                            @php $rental = $apartment->rentals->first(); @endphp
                            <p class="text-sm text-gray-600">
                                {{ __('messages.tenant') }}: <span class="font-medium">{{ $rental->tenant->name ?? 'N/A' }}</span>
                                — {{ __('messages.rent') }}: <span class="font-semibold text-blue-600">${{ number_format($rental->rent_amount, 2) }}/{{ __('messages.mo_short') }}</span>
                            </p>
                        @else
                            <p class="text-sm text-gray-400 italic">{{ __('messages.no_active_tenant') }}</p>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $apartment->status === 'occupied' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ __('messages.' . $apartment->status) }}
                    </span>
                </div>

                <!-- Current Apartment Costs -->
                @if($apartment->fixedExpenses->isNotEmpty())
                <div class="overflow-x-auto mb-4">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('messages.expense') }}</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('messages.type') }}</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('messages.amount') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600 uppercase">{{ __('messages.status') }}</th>
                                <th class="px-3 py-2 text-center text-xs font-semibold text-gray-600 uppercase">{{ __('messages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($apartment->fixedExpenses as $expense)
                            <tr class="{{ $expense->is_active ? '' : 'opacity-50' }}">
                                <td class="px-3 py-2 text-sm font-medium text-gray-800">{{ $expense->expense_name }}</td>
                                <td class="px-3 py-2">
                                    @php
                                        $typeColors = [
                                            'parking' => 'bg-orange-100 text-orange-700',
                                            'internet' => 'bg-purple-100 text-purple-700',
                                            'trash' => 'bg-gray-100 text-gray-700',
                                            'other' => 'bg-blue-100 text-blue-700',
                                        ];
                                        $typeIcons = [
                                            'parking' => '🚗',
                                            'internet' => '📡',
                                            'trash' => '🗑️',
                                            'other' => '📋',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $typeColors[$expense->expense_type] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $typeIcons[$expense->expense_type] ?? '📋' }} {{ __('messages.type_' . $expense->expense_type) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right font-semibold text-red-600">${{ number_format($expense->amount, 2) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $expense->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $expense->is_active ? __('messages.active') : __('messages.disabled') }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex justify-center gap-2">
                                        <form action="{{ route('admin.revenue_expense.toggle_fixed_expense', $expense) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="p-1 rounded hover:bg-gray-100 transition" title="{{ $expense->is_active ? __('messages.disable') : __('messages.enable') }}">
                                                @if($expense->is_active)
                                                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                @else
                                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                @endif
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.revenue_expense.delete_fixed_expense', $expense) }}" method="POST" class="inline" onsubmit="return confirm('{{ __('messages.remove_apt_cost_confirm') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded hover:bg-red-50 transition" title="{{ __('messages.delete') }}">
                                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td class="px-3 py-2 font-bold text-gray-900" colspan="2">{{ __('messages.total_monthly') }}</td>
                                <td class="px-3 py-2 text-right font-bold text-red-600">${{ number_format($apartment->fixedExpenses->where('is_active', true)->sum('amount'), 2) }}</td>
                                <td class="px-3 py-2" colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="text-center py-4 text-gray-400 text-sm mb-4">
                    <p>{{ __('messages.no_apt_costs_yet') }}</p>
                </div>
                @endif
            </div>
            @empty
            <div class="bg-white rounded-lg shadow-md p-8 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p>{{ __('messages.no_apartments_found') }}</p>
            </div>
            @endforelse
        </div>

        <!-- Right: Add Apartment Cost Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-red-500 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>{{ __('messages.add_apartment_cost') }}</h3>

                <form action="{{ route('admin.revenue_expense.store_fixed_expense') }}" method="POST">
                    @csrf

                    <div class="space-y-4">
                        <div>
                            <label for="apartment_id" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.apartment') }} <span class="text-red-500">*</span></label>
                            <select name="apartment_id" id="apartment_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                <option value="">{{ __('messages.select_dash') }}</option>
                                @foreach($apartments as $apartment)
                                <option value="{{ $apartment->id }}" {{ old('apartment_id') == $apartment->id ? 'selected' : '' }}>
                                    {{ $apartment->apartment_number }} ({{ __('messages.floor') }} {{ $apartment->floor->floor_number ?? 'N/A' }})
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="expense_type" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.expense_type') }} <span class="text-red-500">*</span></label>
                            <select name="expense_type" id="expense_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                                <option value="">{{ __('messages.select_dash') }}</option>
                                <option value="parking" {{ old('expense_type') == 'parking' ? 'selected' : '' }}>🚗 {{ __('messages.type_parking') }}</option>
                                <option value="internet" {{ old('expense_type') == 'internet' ? 'selected' : '' }}>📡 {{ __('messages.type_internet') }}</option>
                                <option value="trash" {{ old('expense_type') == 'trash' ? 'selected' : '' }}>🗑️ {{ __('messages.type_trash') }}</option>
                                <option value="other" {{ old('expense_type') == 'other' ? 'selected' : '' }}>📋 Other</option>
                            </select>
                        </div>

                        <div>
                            <label for="expense_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.expense_name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="expense_name" id="expense_name" required value="{{ old('expense_name') }}" placeholder="{{ __('messages.eg_parking_space') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>

                        <div>
                            <label for="fixed_amount" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.monthly_amount') }} <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" id="fixed_amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>

                        <div>
                            <label for="fixed_note" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.note') }}</label>
                            <textarea name="note" id="fixed_note" rows="2" placeholder="{{ __('messages.optional_note') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">{{ old('note') }}</textarea>
                        </div>

                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>{{ __('messages.assign_apartment_cost') }}</button>
                    </div>
                </form>
            </div>

            <!-- Quick Info -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-3 pb-2 border-b">{{ __('messages.how_it_works') }}</h3>
                <div class="space-y-3 text-sm text-gray-600">
                    <div class="flex items-start gap-2">
                        <span class="text-blue-500 font-bold">1.</span>
                        <p>{{ __('messages.how_step1') }}</p>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-500 font-bold">2.</span>
                        <p>{{ __('messages.how_step2') }}</p>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-500 font-bold">3.</span>
                        <p>{{ __('messages.how_step3_pre') }} <a href="{{ route('admin.revenue_expense.generate_bills') }}" class="text-blue-600 underline">{{ __('messages.generate_monthly_bills') }}</a> {{ __('messages.how_step3_post') }}</p>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-blue-500 font-bold">4.</span>
                        <p>{{ __('messages.how_step4') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-fill expense name based on type selection
    document.getElementById('expense_type').addEventListener('change', function() {
        const nameField = document.getElementById('expense_name');
        const names = {
            'parking': '{{ __('messages.type_parking') }}',
            'internet': '{{ __('messages.type_internet') }}',
            'trash': '{{ __('messages.type_trash') }}',
            'other': ''
        };
        if (!nameField.value || Object.values(names).includes(nameField.value)) {
            nameField.value = names[this.value] || '';
        }
    });
</script>
@endsection
