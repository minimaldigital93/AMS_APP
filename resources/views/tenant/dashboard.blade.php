@extends('layouts.tenant')

@section('content')
<div class="space-y-6">

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('messages.my_dashboard') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('messages.welcome_back', ['name' => $tenant->name ?? Auth::user()->name]) }}</p>
    </div>

    @if($tenant)

    {{-- Top Row: Personal Info + Photo --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Personal Information --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">{{ __('messages.personal_information') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.full_name') }}</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ $tenant->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.phone') }}</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ $tenant->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.address') }}</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ $tenant->address ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.date_of_birth') }}</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">
                        {{ $tenant->date_of_birth ? $tenant->date_of_birth->format('M d, Y') : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.place_of_birth') }}</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ $tenant->place_of_birth ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.status') }}</p>
                    <span class="mt-0.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $tenant->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ ucfirst($tenant->status) }}
                    </span>
                </div>
                @if($rental)
                <div>
                    <p class="text-xs text-gray-400 uppercase">{{ __('messages.move_in_date') }}</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">
                        {{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : '—' }}
                    </p>
                </div>
                @endif
            </div>
        </div>

        {{-- Photo --}}
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 flex flex-col items-center justify-center gap-3">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide self-start">{{ __('messages.photo') }}</p>
            @if($tenant->photo_path)
                <img src="{{ asset('storage/' . $tenant->photo_path) }}"
                     alt="{{ __('messages.tenant_photo') }}"
                     class="w-36 h-36 rounded-full object-cover border-4 border-indigo-100 shadow">
            @else
                <div class="w-36 h-36 rounded-full bg-indigo-50 border-4 border-indigo-100 flex items-center justify-center">
                    <span class="text-5xl font-bold text-indigo-300">
                        {{ strtoupper(substr($tenant->name, 0, 1)) }}
                    </span>
                </div>
            @endif
            <p class="text-xs text-gray-400">{{ $tenant->name }}</p>
        </div>
    </div>

    {{-- Apartment & Payment Stats --}}
    @if($rental)
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.apartment') }}</p>
            <p class="text-lg font-bold text-indigo-700 mt-1">{{ $rental->apartment->apartment_number ?? '—' }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $rental->apartment->floor?->floor_name ?? '' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.monthly_rent') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">${{ number_format($paymentStats['this_month_total'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('messages.current_period') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.paid_this_month') }}</p>
            <p class="text-lg font-bold mt-1
                {{ $paymentStats['this_month_status'] === 'paid' ? 'text-green-600' : ($paymentStats['this_month_status'] === 'partial' ? 'text-yellow-600' : 'text-red-500') }}">
                ${{ number_format($paymentStats['this_month_paid'], 2) }}
            </p>
            <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full
                    {{ $paymentStats['this_month_status'] === 'paid' ? 'bg-green-500' : ($paymentStats['this_month_status'] === 'partial' ? 'bg-yellow-400' : 'bg-red-400') }}"
                    style="width: {{ $paymentStats['this_month_percent'] }}%">
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ __('messages.percent_of_rent', ['percent' => $paymentStats['this_month_percent']]) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.all_time_paid') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">${{ number_format($paymentStats['all_time_paid'], 2) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('messages.total_payments') }}</p>
        </div>
    </div>
    @endif

    {{-- Recent Payments + Document --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent Payments --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">{{ __('messages.recent_payments') }}</h2>
            </div>
            @if($recentPayments->isNotEmpty())
            <div class="divide-y divide-gray-50">
                @foreach($recentPayments as $payment)
                <div class="flex items-center justify-between py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ ucfirst($payment->payment_type ?? __('messages.rent')) }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : '—' }}
                                @if($payment->payment_method)
                                    · {{ ucfirst($payment->payment_method) }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">${{ number_format($payment->amount, 2) }}</p>
                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-green-100 text-green-700">{{ __('messages.paid') }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="flex flex-col items-center justify-center py-10 text-center">
                <svg class="w-10 h-10 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm text-gray-400">{{ __('messages.no_payments_yet') }}</p>
            </div>
            @endif
        </div>

        {{-- Document --}}
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">{{ __('messages.document') }}</h2>
            @if($tenant->document_path)
                @php
                    $ext = pathinfo($tenant->document_path, PATHINFO_EXTENSION);
                    $docUrl = asset('storage/' . $tenant->document_path);
                @endphp
                <div class="flex flex-col items-center gap-4">
                    @if(in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp']))
                        <img src="{{ $docUrl }}"
                             alt="{{ __('messages.document') }}"
                             class="w-full max-h-48 object-contain rounded-lg border border-slate-100">
                    @else
                        <div class="w-full flex flex-col items-center justify-center py-8 bg-indigo-50 rounded-lg border border-indigo-100">
                            <svg class="w-12 h-12 text-indigo-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-xs text-indigo-400 uppercase font-medium">{{ __('messages.file_label', ['ext' => strtoupper($ext)]) }}</p>
                        </div>
                    @endif
                    <a href="{{ $docUrl }}"
                       target="_blank"
                       class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition border border-indigo-100" title="{{ __('messages.view_download') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg></a>
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <svg class="w-10 h-10 text-gray-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm text-gray-400">{{ __('messages.no_document_uploaded') }}</p>
                </div>
            @endif
        </div>
    </div>

    @else
    {{-- No tenant record --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-12 text-center">
        <svg class="w-14 h-14 text-gray-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2"/>
        </svg>
        <p class="text-gray-500 font-medium">{{ __('messages.no_active_tenancy') }}</p>
        <p class="text-sm text-gray-400 mt-1">{{ __('messages.contact_property_manager') }}</p>
    </div>
    @endif

</div>
@endsection
