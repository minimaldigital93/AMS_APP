@props([
    'name' => 'attachments',
    'label',
    'hint' => null,
    'maxFiles' => 5,
    'maxBytes' => 10485760,
    'tooLargeMessage' => null,
    'tooManyMessage' => null,
    'accept' => '.pdf,image/*',
])

@php
    $tooLargeMessage ??= __('messages.attachment_too_large');
    $tooManyMessage ??= __('messages.attachment_too_many');
@endphp

<div x-data="multiAttachments({
        maxFiles: {{ $maxFiles }},
        maxBytes: {{ $maxBytes }},
        tooLargeMessage: @js($tooLargeMessage),
        tooManyMessage: @js($tooManyMessage),
    })">
    <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ $label }} ({{ __('messages.optional') }})</label>
    <label class="flex items-center gap-3 w-full px-4 py-3 border border-dashed border-slate-200 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition">
        <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <span class="text-sm text-slate-500">{{ __('messages.click_to_upload_files') }}</span>
        <input type="file" name="{{ $name }}[]" multiple accept="{{ $accept }}" class="hidden" x-ref="input" @change="onSelect($event)">
    </label>
    @if($hint)
        <p class="text-[11px] text-slate-400 mt-1">{{ $hint }}</p>
    @endif

    <ul class="mt-2 space-y-1" x-show="files.length > 0">
        <template x-for="(f, i) in files" :key="i">
            <li class="flex items-center gap-2 text-sm bg-slate-50 rounded-lg px-3 py-1.5">
                <template x-if="f.isImage">
                    <img :src="f.previewUrl" alt="" class="h-8 w-8 object-cover rounded border border-slate-200">
                </template>
                <template x-if="!f.isImage">
                    <span class="h-8 w-8 flex items-center justify-center rounded bg-orange-50 text-orange-600 text-[10px] font-semibold">PDF</span>
                </template>
                <span class="flex-1 truncate text-slate-600" x-text="f.name"></span>
                <span class="text-slate-400 text-xs" x-text="f.sizeLabel"></span>
                <button type="button" @click="removeFile(i)" class="text-red-500 hover:text-red-600 text-base leading-none px-1">&times;</button>
            </li>
        </template>
    </ul>

    @error("{$name}.*")<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
</div>
