@extends('layouts.superadmin')

@section('content')
<div x-data="{ creating: false }">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Plans') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Leave a cap blank for unlimited.') }}</p>
        </div>
        <button type="button" @click="creating = !creating"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
            {{ __('New plan') }}
        </button>
    </div>

    {{-- Create plan --}}
    <form x-show="creating" x-cloak method="POST" action="{{ route('superadmin.plans.store') }}"
        class="mt-6 rounded-2xl border border-indigo-200 bg-indigo-50/40 p-5 shadow-sm">
        @csrf
        <h2 class="text-lg font-semibold text-gray-900">{{ __('New plan') }}</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Slug') }}</label>
                <input name="slug" value="{{ old('slug') }}" placeholder="basic" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>
                @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Display name') }}</label>
                <input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Price (USD / month)') }}</label>
                <input name="price_usd" type="number" step="0.01" min="0" value="{{ old('price_usd') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Max floors') }}</label>
                <input name="max_floors" type="number" min="0" value="{{ old('max_floors') }}" placeholder="∞" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Max apartments') }}</label>
                <input name="max_apartments" type="number" min="0" value="{{ old('max_apartments') }}" placeholder="∞" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500">{{ __('Billing period (days)') }}</label>
                <input name="billing_period_days" type="number" min="1" value="{{ old('billing_period_days', 30) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>
            </div>
        </div>

        <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-gray-300">
            {{ __('Active') }}
        </label>

        <div class="mt-4 flex gap-3">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Create plan') }}</button>
            <button type="button" @click="creating = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
        </div>
    </form>

    <div class="mt-6 grid gap-5 lg:grid-cols-3">
        @foreach ($plans as $plan)
            <div x-data="{ editing: {{ $errors->any() && old('_plan_id') == $plan->id ? 'true' : 'false' }} }"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h2>
                    <span class="text-xs uppercase tracking-wide text-gray-400">{{ $plan->slug }}</span>
                </div>

                {{-- Read-only summary --}}
                <div x-show="!editing" class="mt-4 space-y-2 text-sm text-gray-600">
                    <div class="flex justify-between"><span>{{ __('Price (USD / month)') }}</span><span class="font-medium text-gray-900">${{ number_format($plan->price_usd, 2) }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Max floors') }}</span><span class="font-medium text-gray-900">{{ $plan->max_floors ?? '∞' }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Max apartments') }}</span><span class="font-medium text-gray-900">{{ $plan->max_apartments ?? '∞' }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Billing period (days)') }}</span><span class="font-medium text-gray-900">{{ $plan->billing_period_days }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Active') }}</span>
                        <span class="font-medium {{ $plan->is_active ? 'text-green-600' : 'text-gray-400' }}">{{ $plan->is_active ? __('Yes') : __('No') }}</span>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="button" @click="editing = true"
                            class="flex-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('superadmin.plans.destroy', $plan) }}"
                            onsubmit="return confirm('{{ __('Delete this plan?') }}')" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
                        </form>
                    </div>
                </div>

                {{-- Edit form --}}
                <form x-show="editing" x-cloak method="POST" action="{{ route('superadmin.plans.update', $plan) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_plan_id" value="{{ $plan->id }}">

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
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active)) class="rounded border-gray-300">
                        {{ __('Active') }}
                    </label>

                    <div class="mt-4 flex gap-2">
                        <button class="flex-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save') }}</button>
                        <button type="button" @click="editing = false" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endsection
