@extends('layouts.superadmin')

@section('content')
<h1 class="text-2xl font-bold text-gray-900">{{ __('Plans') }}</h1>
<p class="mt-1 text-sm text-gray-500">{{ __('Leave a cap blank for unlimited.') }}</p>

<div class="mt-6 grid gap-5 lg:grid-cols-3">
    @foreach ($plans as $plan)
        <form method="POST" action="{{ route('superadmin.plans.update', $plan) }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            @csrf
            @method('PUT')
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h2>
                <span class="text-xs uppercase tracking-wide text-gray-400">{{ $plan->slug }}</span>
            </div>

            <label class="mt-4 block text-xs font-medium text-gray-500">{{ __('Display name') }}</label>
            <input name="name" value="{{ old('name', $plan->name) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>

            <label class="mt-3 block text-xs font-medium text-gray-500">{{ __('Price (USD / month)') }}</label>
            <input name="price_usd" type="number" step="0.01" min="0" value="{{ old('price_usd', $plan->price_usd) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>

            <div class="mt-3 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Max floors') }}</label>
                    <input name="max_floors" type="number" min="0" value="{{ old('max_floors', $plan->max_floors) }}" placeholder="∞" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Max apartments') }}</label>
                    <input name="max_apartments" type="number" min="0" value="{{ old('max_apartments', $plan->max_apartments) }}" placeholder="∞" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <label class="mt-3 block text-xs font-medium text-gray-500">{{ __('Billing period (days)') }}</label>
            <input name="billing_period_days" type="number" min="1" value="{{ old('billing_period_days', $plan->billing_period_days) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>

            <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="rounded border-gray-300">
                {{ __('Active') }}
            </label>

            <button class="mt-4 w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save') }}</button>
        </form>
    @endforeach
</div>
@endsection
