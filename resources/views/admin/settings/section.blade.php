{{--
    One category of the settings form, on a page of its own — the shape the
    expense categories and the default utility prices already had. The fields
    and their controls are unchanged; they post to the same updateBatch() route
    the single big form posted to, carrying only this page's keys.
--}}
@extends('layouts.admin')

@php
    $titles = [
        'company' => __('messages.company_information'),
        'owner'   => __('messages.owner_information'),
        'billing' => __('messages.billing_late_fee_settings'),
    ];
    $title = $titles[$category] ?? ucfirst($category);
    $companyLogo = $category === 'company' ? settings('company_logo') : null;
@endphp

@section('title', $title)

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ $title }}</h1>
            <a href="{{ route('admin.settings.index') }}" class="ml-auto flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-400 hover:bg-white hover:text-gray-600 transition" title="{{ __('messages.back') }}" aria-label="{{ __('messages.back') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm" role="alert">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.updateBatch') }}" class="space-y-8" enctype="multipart/form-data"@if($category === 'company') x-data="logoUploader()"@endif>
            @csrf
            @method('PUT')

            <div>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                    {{-- The logo is company identity, so it heads the company card --}}
                    @if($category === 'company')
                    <div class="flex items-center gap-4 px-4 py-4">
                        <!-- Preview -->
                        <div class="flex-shrink-0">
                            <div x-show="hasLogo" class="w-16 h-16 rounded-xl border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">
                                <img :src="previewUrl" alt="{{ __('messages.company_logo') }}" class="w-full h-full object-contain">
                            </div>
                            <div x-show="!hasLogo" class="w-16 h-16 rounded-xl border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-gray-300">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                        </div>
                        <!-- Controls -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="$refs.logoInput.click()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                    <span x-text="hasLogo ? '{{ __('messages.change_logo') }}' : '{{ __('messages.upload_logo') }}'"></span>
                                </button>
                                <button type="button" x-show="hasLogo" @click="removeLogo()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    {{ __('messages.remove_logo') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <input type="file" name="company_logo" x-ref="logoInput" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onSelect($event)">
                    <input type="hidden" name="remove_company_logo" :value="removeFlag ? '1' : '0'">
                    @endif

                    @foreach($fields as $key => $defaultValue)
                        @include('admin.settings.partials.field-row', [
                            'key' => $key,
                            'value' => old("settings.$key", $values[$key]),
                        ])
                    @endforeach
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white font-semibold py-2.5 px-8 rounded-full transition duration-200 flex items-center gap-2 shadow-sm" title="{{ __('messages.save_all_settings') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
                    </svg></button>
            </div>
        </form>

    </div>
</div>

@if($category === 'company')
<script>
function logoUploader() {
    return {
        hasLogo: @json((bool) $companyLogo),
        previewUrl: '{{ $companyLogo ? asset('storage/' . $companyLogo) : '' }}',
        removeFlag: false,
        onSelect(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.removeFlag = false;
            this.previewUrl = URL.createObjectURL(file);
            this.hasLogo = true;
        },
        removeLogo() {
            this.removeFlag = true;
            this.hasLogo = false;
            this.previewUrl = '';
            this.$refs.logoInput.value = '';
        },
    };
}
</script>
@endif
@endsection
