@extends('layouts.superadmin')

@section('content')
@php($sub = $account->subscription)
@php($statusKey = $account->status === 'suspended' ? 'suspended' : ($sub && $sub->isActive() ? 'active' : 'inactive'))
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ $account->name }}</h1>
            <span class="px-3 py-1 text-xs font-semibold rounded-full
                {{ $statusKey === 'suspended' ? 'bg-red-100 text-red-700' : ($statusKey === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700') }}">
                {{ $statusKey === 'suspended' ? __('Suspended') : ($statusKey === 'active' ? __('Active') : __('Inactive')) }}
            </span>
        </div>
        <a href="{{ route('superadmin.accounts.index') }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            {{ __('Back to accounts') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Account details -->
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">{{ __('Account details') }}</h2>
            <dl class="divide-y divide-gray-100">
                <div class="flex justify-between py-3">
                    <dt class="text-sm text-gray-500">{{ __('Name') }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $account->name }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-sm text-gray-500">{{ __('Phone') }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $account->phone }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-sm text-gray-500">{{ __('Plan') }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $sub?->plan?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-sm text-gray-500">{{ __('Expires') }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $sub?->expires_at ? $sub->expires_at->format('M j, Y') : '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-sm text-gray-500">{{ __('Last login') }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $account->last_login_at ? $account->last_login_at->format('M j, Y g:i A') : '—' }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-sm text-gray-500">{{ __('Created') }}</dt>
                    <dd class="text-sm font-medium text-gray-900">{{ $account->created_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Usage -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('Usage') }}</h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['floors'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('Floors') }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['apartments'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('Rooms') }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['tenants'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('Tenants') }}</div>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['members'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ __('Members') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset password -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ __('Reset password') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ __('messages.reset_password_help', ['password' => '12345678']) }}</p>
            </div>
            <form method="POST" action="{{ route('superadmin.accounts.reset-password', $account) }}"
                  data-confirm="{{ __('messages.confirm_reset_password', ['name' => $account->name, 'password' => '12345678']) }}"
                  data-confirm-title="{{ __('Reset password') }}"
                  data-confirm-ok="{{ __('messages.confirm_reset_password_ok') }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition whitespace-nowrap">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                    </svg>
                    {{ __('Reset password') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
