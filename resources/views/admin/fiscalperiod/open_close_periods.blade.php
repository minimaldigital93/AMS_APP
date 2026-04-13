@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-lg">
    <h1 class="text-2xl font-bold mb-6">New Fiscal Period</h1>

    @if(session('warning'))
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
            <p class="text-yellow-800 text-sm">{{ session('warning') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <ul class="text-red-700 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.fiscalperiod.store') }}" class="bg-white rounded-lg shadow p-6 space-y-5">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Period Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                placeholder="e.g., Fiscal Year 2026">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="opening_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="opening_date" name="opening_date" value="{{ old('opening_date') }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label for="closing_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="closing_date" name="closing_date" value="{{ old('closing_date') }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
        </div>

        <div>
            <label for="opening_balance" class="block text-sm font-medium text-gray-700 mb-1">Opening Balance</label>
            <div class="relative">
                <span class="absolute left-3 top-2 text-gray-500">$</span>
                <input type="number" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" 
                    required step="0.01" min="0"
                    class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
        </div>

        <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700">
            Monthly periods will be auto-created. After creating, you can add balance sheet items.
        </div>

        <div class="flex gap-3">
            <button type="submit" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm">
                Create Period
            </button>
            <a href="{{ route('admin.fiscalperiod.index') }}" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-semibold hover:bg-gray-200 text-sm text-center">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
