@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-lg">
    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight mb-6">{{ __('messages.new_fiscal_period') }}</h1>

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
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.period_name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                placeholder="{{ __('messages.eg_fiscal_year') }}">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="opening_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.start_date') }}</label>
                <input type="date" id="opening_date" name="opening_date" value="{{ old('opening_date') }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white appearance-none h-10 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div>
                <label for="closing_date" class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.end_date') }}</label>
                <input type="date" id="closing_date" name="closing_date" value="{{ old('closing_date') }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white appearance-none h-10 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('admin.fiscalperiod.index') }}" class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-lg font-semibold hover:bg-gray-200 text-sm text-center">
                {{ __('messages.cancel') }}
            </a>
            <button type="submit"
                class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg font-semibold hover:bg-blue-700 text-sm">
                Create Period
            </button>
        </div>
    </form>
</div>
@endsection
