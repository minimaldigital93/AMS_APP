@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-4">Edit Fiscal Period</h1>
        <p class="text-gray-600">Modify the details of {{ $fiscalperiod->name }}</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-red-900 mb-2">Validation Errors:</h3>
            <ul class="text-red-700 space-y-1">
                @foreach($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.fiscalperiod.update', $fiscalperiod->id) }}" class="bg-white rounded-lg shadow-lg p-8 max-w-2xl">
        @csrf
        @method('PUT')

        <div class="mb-6">
            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Fiscal Period Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $fiscalperiod->name) }}" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('name')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="opening_date" class="block text-sm font-semibold text-gray-700 mb-2">Opening Date</label>
                <input type="date" id="opening_date" name="opening_date" value="{{ old('opening_date', $fiscalperiod->opening_date->format('Y-m-d')) }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('opening_date')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="closing_date" class="block text-sm font-semibold text-gray-700 mb-2">Closing Date</label>
                <input type="date" id="closing_date" name="closing_date" value="{{ old('closing_date', $fiscalperiod->closing_date->format('Y-m-d')) }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('closing_date')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mb-8">
            <label for="opening_balance" class="block text-sm font-semibold text-gray-700 mb-2">Opening Balance</label>
            <div class="relative">
                <span class="absolute left-4 top-2 text-gray-600 text-lg">$</span>
                <input type="number" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', $fiscalperiod->opening_balance) }}" 
                    required step="0.01" min="0"
                    class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            @error('opening_balance')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
            <h3 class="font-semibold text-yellow-900 mb-2">Note:</h3>
            <p class="text-yellow-800 text-sm">You can only edit period details if the period is still open. Once closed, the period becomes read-only.</p>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                Update Fiscal Period
            </button>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="flex-1 bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-semibold hover:bg-gray-400 transition text-center">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
