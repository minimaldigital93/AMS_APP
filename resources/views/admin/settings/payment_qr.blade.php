{{--
    The scan-to-pay QR — image plus the account name printed under it on the
    tenant's unpaid bill. Its own page now, but the same form: it posts to
    updateBatch(), which hands both fields to handleScanToPayQr() exactly as
    before. Neither lives in the Settings table (see the controller).
--}}
@extends('layouts.admin')

@section('title', __('messages.payment_qr_code'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.payment_qr_code') }}</h1>
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

        <form method="POST" action="{{ route('admin.settings.updateBatch') }}" class="space-y-8" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div x-data="qrUploader()">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="flex items-center gap-4 px-4 py-4">
                        <!-- Preview -->
                        <div class="flex-shrink-0">
                            <div x-show="hasQr" class="w-16 h-16 rounded-xl border border-gray-200 bg-white overflow-hidden flex items-center justify-center p-1">
                                <img :src="previewUrl" alt="{{ __('messages.payment_qr_code') }}" class="w-full h-full object-contain">
                            </div>
                            <div x-show="!hasQr" class="w-16 h-16 rounded-xl border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-gray-300">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Zm0 9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm9.75-9.75c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Zm0 9.75h3m3 0h-.008v.008H19.5v-.008Zm-3 3h.008v.008H16.5v-.008Zm3 3h.008v.008H19.5v-.008Zm-3 0h.008v.008H16.5v-.008Zm-3-3h.008v.008H13.5v-.008Zm0 3h.008v.008H13.5v-.008Z" />
                                </svg>
                            </div>
                        </div>
                        <!-- Controls -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="$refs.qrInput.click()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                    <span x-text="hasQr ? '{{ __('messages.change_qr_image') }}' : '{{ __('messages.upload_qr_image') }}'"></span>
                                </button>
                                <button type="button" x-show="hasQr" @click="removeQr()"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    {{ __('messages.remove') }}
                                </button>
                                <button type="button" x-show="hasQr && !removeFlag" @click="zoom = true"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14zM8 11h6" /></svg>
                                    {{ __('messages.preview') }}
                                </button>
                            </div>
                            <p x-show="removeFlag" x-cloak class="mt-1 text-[13px] font-medium text-red-600">{{ __('messages.payment_qr_code_removed_on_save') }}</p>
                        </div>
                    </div>

                    {{-- Whose account the QR pays into — printed under it on the
                         bill, so the tenant knows before they scan. --}}
                    <div class="flex items-center gap-3 border-t border-gray-100 px-4 py-3">
                        <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <label for="khqr_account_name" class="text-[15px] text-gray-900 whitespace-nowrap">{{ __('messages.payment_qr_account_name') }}</label>
                        <input type="text" name="khqr_account_name" id="khqr_account_name" maxlength="255"
                            value="{{ old('khqr_account_name', $khqrAccountName) }}"
                            class="flex-1 bg-transparent border-0 p-0 text-right text-[15px] text-gray-500 focus:text-gray-900 placeholder-gray-400 focus:ring-0 focus:outline-none"
                            placeholder="{{ __('messages.payment_qr_account_name_placeholder') }}">
                    </div>
                    <input type="file" name="khqr_image" x-ref="qrInput" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onSelect($event)">
                    <input type="hidden" name="remove_khqr_image" :value="removeFlag ? '1' : '0'">
                </div>

                <!-- Full-size preview: the QR has to be scannable to be worth uploading -->
                <div x-show="zoom" x-cloak @click="zoom = false" @keydown.escape.window="zoom = false"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-6">
                    <div class="rounded-2xl bg-white p-4 shadow-xl" @click.stop>
                        <img :src="previewUrl" alt="{{ __('messages.payment_qr_code') }}" class="max-h-[70vh] max-w-[70vw] object-contain">
                        <button type="button" @click="zoom = false" class="mt-3 w-full rounded-lg bg-gray-100 py-2 text-[13px] font-medium text-gray-700 hover:bg-gray-200">
                            {{ __('messages.close') }}
                        </button>
                    </div>
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

<script>
function qrUploader() {
    return {
        hasQr: @json((bool) $khqrImageUrl),
        previewUrl: @json($khqrImageUrl ?? ''),
        removeFlag: false,
        zoom: false,
        onSelect(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.removeFlag = false;
            this.previewUrl = URL.createObjectURL(file);
            this.hasQr = true;
        },
        removeQr() {
            this.removeFlag = true;
            this.hasQr = false;
            this.previewUrl = '';
            this.zoom = false;
            this.$refs.qrInput.value = '';
        },
    };
}
</script>
@endsection
