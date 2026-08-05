@extends('layouts.admin')

@section('title', __('messages.expense_categories'))

@section('content')
<div class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-8">

        <!-- Header -->
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ __('messages.expense_categories') }}</h1>
                <p class="mt-1 text-[13px] text-gray-500">{{ __('messages.expense_categories_hint') }}</p>
            </div>
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

        <!-- Add a category -->
        <div>
            <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">{{ __('messages.add_expense_category') }}</p>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <form method="POST" action="{{ route('admin.settings.expense_categories.store') }}" class="flex items-center gap-3 px-4 py-3">
                    @csrf
                    <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <input type="text" name="name" maxlength="60" required
                        value="{{ old('name') }}"
                        placeholder="{{ __('messages.expense_category_name_placeholder') }}"
                        class="flex-1 bg-transparent border-0 p-0 text-[15px] text-gray-900 placeholder-gray-400 focus:ring-0 focus:outline-none">
                    <button type="submit"
                        class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        {{ __('messages.add_expense_category') }}
                    </button>
                </form>
            </div>
        </div>

        <!-- The list -->
        <div>
            <p class="px-4 mb-2 text-[13px] font-medium uppercase tracking-wide text-gray-500">
                {{ __('messages.expense_categories') }}
                <span class="text-gray-400 normal-case font-normal">({{ $categories->count() }})</span>
            </p>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y divide-gray-100">
                @forelse($categories as $category)
                @php
                    $used = $usage[$category->key] ?? 0;
                    $locked = $used > 0;
                @endphp
                <div x-data="{ editing: false }" class="px-4 py-3">
                    <!-- Read mode -->
                    <div x-show="!editing" class="flex items-center gap-3">
                        <span class="flex-shrink-0 w-2 h-2 rounded-full {{ $category->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        <div class="min-w-0">
                            <p class="text-[15px] text-gray-900 truncate">{{ $category->name }}</p>
                            <p class="text-[12px] text-gray-400">
                                <span class="font-mono">{{ $category->key }}</span>
                                @unless($category->is_active)
                                    · {{ __('messages.inactive') }}
                                @endunless
                                @if($locked)
                                    · {{ __('messages.expense_category_in_use_short', ['count' => $used]) }}
                                @endif
                            </p>
                        </div>
                        <div class="ml-auto flex items-center gap-1">
                            <button type="button" @click="editing = true"
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 hover:bg-blue-50 transition" title="{{ __('messages.edit') }}" aria-label="{{ __('messages.edit') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            @if($locked)
                            {{-- Deleting is refused (the expense stores the key), so the
                                 button explains why instead of vanishing — and offers the
                                 thing the owner actually wants: retire it. --}}
                            <button type="button"
                                data-category-locked
                                data-title="{{ __('messages.expense_category_locked_title') }}"
                                data-message="{{ __('messages.expense_category_in_use_count', ['name' => $category->name, 'count' => $used]) }}"
                                @if($category->is_active)
                                    data-ok="{{ __('messages.expense_category_deactivate_instead') }}"
                                    data-deactivate-form="deactivate-category-{{ $category->id }}"
                                @endif
                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                                title="{{ __('messages.expense_category_locked_title') }}" aria-label="{{ __('messages.expense_category_locked_title') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </button>
                            @else
                            <form method="POST" action="{{ route('admin.settings.expense_categories.destroy', $category) }}"
                                  data-confirm="{{ __('messages.expense_category_delete_confirm', ['name' => $category->name]) }}">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 transition" title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <form x-show="editing" x-cloak method="POST"
                          action="{{ route('admin.settings.expense_categories.update', $category) }}"
                          class="flex flex-wrap items-center gap-3">
                        @csrf @method('PUT')
                        <input type="text" name="name" maxlength="60" required value="{{ $category->name }}"
                            class="flex-1 min-w-[10rem] bg-transparent border-0 border-b border-gray-200 p-0 pb-1 text-[15px] text-gray-900 focus:ring-0 focus:border-blue-500 focus:outline-none">
                        {{-- Unchecked checkboxes don't POST, so ship an explicit 0 first. --}}
                        <input type="hidden" name="is_active" value="0">
                        <label class="inline-flex items-center gap-2 text-[13px] text-gray-500 cursor-pointer">
                            {{ __('messages.active') }}
                            <span class="relative inline-flex items-center">
                                <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $category->is_active ? 'checked' : '' }}>
                                <span class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-blue-500 transition-colors"></span>
                                <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                        <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 text-[13px] font-medium text-white bg-blue-500 hover:bg-blue-600 rounded-lg transition">{{ __('messages.save') }}</button>
                        <button type="button" @click="editing = false"
                            class="inline-flex items-center px-3 py-1.5 text-[13px] font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">{{ __('messages.cancel') }}</button>
                    </form>

                    @if($locked && $category->is_active)
                    {{-- Submitted by the "can't delete" dialog: the one action that IS
                         allowed on an in-use category. Same update route, so the
                         last-active-category guard still applies. --}}
                    <form id="deactivate-category-{{ $category->id }}" method="POST"
                          action="{{ route('admin.settings.expense_categories.update', $category) }}" class="hidden">
                        @csrf @method('PUT')
                        <input type="hidden" name="name" value="{{ $category->name }}">
                        <input type="hidden" name="is_active" value="0">
                    </form>
                    @endif
                </div>
                @empty
                <p class="px-4 py-6 text-center text-[15px] text-gray-400">{{ __('messages.no_data') }}</p>
                @endforelse
            </div>
            <p class="px-4 mt-2 text-[13px] text-gray-500">{{ __('messages.expense_category_key_hint') }}</p>
        </div>

        <!-- Restore defaults -->
        <div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <form method="POST" action="{{ route('admin.settings.expense_categories.restore') }}">
                    @csrf
                    <button type="submit"
                        class="w-full px-4 py-3 text-center text-[15px] font-medium text-blue-600 hover:bg-blue-50 active:bg-blue-100 transition duration-150">
                        {{ __('messages.expense_categories_restore_defaults') }}
                    </button>
                </form>
            </div>
            <p class="px-4 mt-2 text-[13px] text-gray-500">{{ __('messages.expense_categories_restore_defaults_hint') }}</p>
        </div>

    </div>
</div>

{{-- The lock button on an in-use category. Uses the shared dialog from
     partials/confirm-modal: a notice ("this can't be deleted") when there is
     nothing left to offer, or a confirm whose OK button retires the category
     instead — which is the supported way to get it out of the expense form. --}}
<script>
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-category-locked]');
        if (!btn) return;

        var title = btn.getAttribute('data-title');
        var message = btn.getAttribute('data-message');
        var formId = btn.getAttribute('data-deactivate-form');
        var form = formId ? document.getElementById(formId) : null;

        if (!form) {
            window.amsAlert(message, { title: title });
            return;
        }

        window.confirmAction({
            title: title,
            message: message,
            okLabel: btn.getAttribute('data-ok')
        }).then(function (ok) {
            if (!ok) return;
            if (typeof form.requestSubmit === 'function') { form.requestSubmit(); }
            else { form.submit(); }
        });
    });
})();
</script>
@endsection
