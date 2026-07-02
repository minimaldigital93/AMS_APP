@extends('layouts.tenant')

@section('content')
<div class="space-y-6" x-data="{ viewerOpen: false, viewerUrl: '', viewerIsImage: false, viewerTitle: '',
        openViewer(url, isImage, title) { this.viewerUrl = url; this.viewerIsImage = isImage; this.viewerTitle = title; this.viewerOpen = true; },
        closeViewer() { this.viewerOpen = false; this.viewerUrl = ''; } }"
    @keydown.escape.window="closeViewer()">

    {{-- Page Header --}}
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">{{ __('messages.my_dashboard') }}</h1>
    </div>

    @if($tenant)

    {{-- Top Row: Personal Info + Photo --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

        {{-- Personal Information --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">{{ __('messages.personal_information') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 sm:gap-4">
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
                        {{ status_label($tenant->status) }}
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

        {{-- Photo / Profile hero (shows first on phones for an at-a-glance profile) --}}
        <div class="order-first lg:order-none bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-6 flex flex-row lg:flex-col items-center gap-4 lg:gap-3">
            <p class="hidden lg:block text-xs font-semibold text-gray-400 uppercase tracking-wide self-start">{{ __('messages.photo') }}</p>
            @if($tenant->photo_path)
                <img src="{{ asset('storage/' . $tenant->photo_path) }}"
                     alt="{{ __('messages.tenant_photo') }}"
                     @click="openViewer('{{ asset('storage/' . $tenant->photo_path) }}', true, '{{ __('messages.photo') }}')"
                     class="w-20 h-20 sm:w-24 sm:h-24 lg:w-36 lg:h-36 flex-shrink-0 rounded-full object-cover border-4 border-indigo-100 shadow cursor-pointer hover:opacity-90 active:opacity-80 transition">
            @else
                <div class="w-20 h-20 sm:w-24 sm:h-24 lg:w-36 lg:h-36 flex-shrink-0 rounded-full bg-indigo-50 border-4 border-indigo-100 flex items-center justify-center">
                    <span class="text-3xl sm:text-4xl lg:text-5xl font-bold text-indigo-300">
                        {{ strtoupper(substr($tenant->name, 0, 1)) }}
                    </span>
                </div>
            @endif
            <div class="min-w-0 lg:text-center">
                <p class="text-base lg:text-sm font-semibold lg:font-medium text-gray-900 lg:text-gray-400 truncate">{{ $tenant->name }}</p>
                <span class="lg:hidden mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $tenant->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                    {{ status_label($tenant->status) }}
                </span>
            </div>
        </div>
    </div>

    {{-- Apartment & Payment Stats --}}
    @if($rental)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.apartment') }}</p>
            <p class="text-lg font-bold text-indigo-700 mt-1 truncate">{{ $rental->apartment->apartment_number ?? '—' }}</p>
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $rental->apartment->floor?->floor_name ?? '' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.monthly_rent') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ money($paymentStats['this_month_total']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('messages.current_period') }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.paid_this_month') }}</p>
            <p class="text-lg font-bold mt-1
                {{ $paymentStats['this_month_status'] === 'paid' ? 'text-green-600' : ($paymentStats['this_month_status'] === 'partial' ? 'text-yellow-600' : 'text-red-500') }}">
                {{ money($paymentStats['this_month_paid']) }}
            </p>
            <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full
                    {{ $paymentStats['this_month_status'] === 'paid' ? 'bg-green-500' : ($paymentStats['this_month_status'] === 'partial' ? 'bg-yellow-400' : 'bg-red-400') }}"
                    style="width: {{ $paymentStats['this_month_percent'] }}%">
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ __('messages.percent_of_rent', ['percent' => $paymentStats['this_month_percent']]) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-5">
            <p class="text-xs text-gray-400 uppercase">{{ __('messages.all_time_paid') }}</p>
            <p class="text-lg font-bold text-gray-900 mt-1">{{ money($paymentStats['all_time_paid']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ __('messages.total_payments') }}</p>
        </div>
    </div>
    @endif

    {{-- Recent Payments + Document --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">

        {{-- Recent Payments --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">{{ __('messages.recent_payments') }}</h2>
            </div>
            @if($recentPayments->isNotEmpty())
            <div class="divide-y divide-gray-50">
                @foreach($recentPayments as $payment)
                <div class="flex items-center justify-between gap-3 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ ucfirst($payment->payment_type ?? __('messages.rent')) }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                {{ $payment->paid_at ? $payment->paid_at->format('M d, Y') : '—' }}
                                @if($payment->payment_method)
                                    · {{ ucfirst($payment->payment_method) }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-semibold text-gray-900">{{ money($payment->amount) }}</p>
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
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4 sm:p-6">
            <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">{{ __('messages.document') }}</h2>
            @if($tenant->attachments->isNotEmpty())
                <div class="space-y-3">
                    @foreach($tenant->attachments as $doc)
                        @php $isImageDoc = $doc->isImage(); @endphp
                        <div class="flex flex-col items-center gap-2 pb-3 border-b border-slate-50 last:border-0 last:pb-0">
                            @if($isImageDoc)
                                <img src="{{ $doc->url() }}"
                                     alt="{{ $doc->original_name }}"
                                     @click="openViewer('{{ $doc->url() }}', true, '{{ $doc->original_name }}')"
                                     class="w-full max-h-48 object-contain rounded-lg border border-slate-100 cursor-pointer hover:opacity-90 transition">
                            @else
                                <button type="button"
                                    @click="openViewer('{{ $doc->url() }}', false, '{{ $doc->original_name }}')"
                                    class="w-full flex flex-col items-center justify-center py-8 bg-indigo-50 rounded-lg border border-indigo-100 hover:bg-indigo-100 transition">
                                    <svg class="w-12 h-12 text-indigo-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="text-xs text-indigo-400 uppercase font-medium truncate max-w-full px-2">{{ $doc->original_name }}</p>
                                </button>
                            @endif
                            <button type="button"
                               @click="openViewer('{{ $doc->url() }}', {{ $isImageDoc ? 'true' : 'false' }}, '{{ $doc->original_name }}')"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 active:bg-indigo-200 rounded-lg transition border border-indigo-100" title="{{ __('messages.view_download') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <span>{{ __('messages.view_download') }}</span>
                            </button>
                        </div>
                    @endforeach
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

    {{-- In-app viewer (lightbox) — keeps PWA users inside the app with a working back/close button --}}
    <div x-show="viewerOpen"
         x-cloak
         x-transition.opacity
         @click.self="closeViewer()"
         class="fixed inset-0 z-[60] bg-black/80 flex flex-col"
         style="display: none;">
        {{-- Header bar with Back / Close --}}
        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-black/40">
            <button type="button" @click="closeViewer()"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-white/10 hover:bg-white/20 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>{{ __('messages.back') }}</span>
            </button>
            <span class="text-sm font-medium text-white/90 truncate" x-text="viewerTitle"></span>
            <a :href="viewerUrl" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-white/10 hover:bg-white/20 rounded-lg transition" title="{{ __('messages.view_download') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </a>
        </div>
        {{-- Content --}}
        <div class="flex-1 overflow-auto flex items-center justify-center p-4">
            <template x-if="viewerIsImage">
                <img :src="viewerUrl" alt="{{ __('messages.document') }}" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
            </template>
            <template x-if="!viewerIsImage">
                <iframe :src="viewerUrl" class="w-full h-full bg-white rounded-lg shadow-2xl" frameborder="0"></iframe>
            </template>
        </div>
    </div>

</div>
@endsection
