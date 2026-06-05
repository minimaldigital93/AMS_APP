@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-lg">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.edit_period') }}</h1>
        </div>
        <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            Back to Period
        </a>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <ul class="text-red-700 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.fiscalperiod.update', $fiscalperiod->id) }}" class="bg-white rounded-lg shadow p-6 space-y-5"
          x-data="{
              assets: {{ old('opening_assets', $fiscalperiod->opening_assets) }},
              liabilities: {{ old('opening_liabilities', $fiscalperiod->opening_liabilities) }},
              equity: {{ old('opening_equity', $fiscalperiod->opening_equity) }},
              get balanced() { return Math.abs(this.assets - (this.liabilities + this.equity)) < 0.01; },
              get diff() { return (this.assets - (this.liabilities + this.equity)); }
          }">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.period_name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $fiscalperiod->name) }}" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="opening_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.start_date') }}</label>
                <input type="date" id="opening_date" name="opening_date" value="{{ old('opening_date', $fiscalperiod->opening_date->format('Y-m-d')) }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white appearance-none h-10">
            </div>
            <div>
                <label for="closing_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.end_date') }}</label>
                <input type="date" id="closing_date" name="closing_date" value="{{ old('closing_date', $fiscalperiod->closing_date->format('Y-m-d')) }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white appearance-none h-10">
            </div>
        </div>

        {{-- Opening balance sheet --}}
        <div class="border border-gray-200 rounded-lg p-4">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('messages.opening_balance_sheet') }}</h2>
                <p class="text-xs text-gray-500">{{ __('messages.changing_rerolls') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label for="opening_assets" class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.assets') }}</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input type="number" id="opening_assets" name="opening_assets" x-model.number="assets"
                            value="{{ old('opening_assets', $fiscalperiod->opening_assets) }}" required step="0.01" min="0"
                            class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label for="opening_liabilities" class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.liabilities') }}</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input type="number" id="opening_liabilities" name="opening_liabilities" x-model.number="liabilities"
                            value="{{ old('opening_liabilities', $fiscalperiod->opening_liabilities) }}" required step="0.01" min="0"
                            class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label for="opening_equity" class="block text-xs font-medium text-gray-600 mb-1">{{ __('messages.equity') }}</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500">$</span>
                        <input type="number" id="opening_equity" name="opening_equity" x-model.number="equity"
                            value="{{ old('opening_equity', $fiscalperiod->opening_equity) }}" required step="0.01" min="0"
                            class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <div class="mt-3 text-xs rounded-lg px-3 py-2"
                 :class="balanced ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'">
                <template x-if="balanced">
                    <span>✓ Balanced — Assets = Liabilities + Equity.</span>
                </template>
                <template x-if="!balanced">
                    <span>{{ __('messages.not_balanced_pre') }}<span x-text="Math.abs(diff).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>).</span>
                </template>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" :disabled="!balanced"
                class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                Save Changes
            </button>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-semibold hover:bg-gray-200 text-sm text-center">
                {{ __('messages.cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
