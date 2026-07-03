@extends('layouts.admin')

@section('title', __('messages.property_management'))

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.property_management') }}</h1>
            @php($max = $usage['properties_max'])
            <p class="mt-1 text-sm text-slate-500">{{ __('messages.properties') }}: {{ $usage['properties_used'] }} / {{ $max ?? '∞' }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.properties.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="{{ __('messages.add_property') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </a>
        </div>
    </div>

    <!-- Properties (each card shows its floor / room counts) -->
    <div class="space-y-5">
        @forelse ($properties as $property)
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
            <div class="flex items-center justify-between gap-3 px-6 py-4">
                <!-- Identity -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-800 truncate">{{ $property->name }}</h2>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400 truncate">
                            <span class="material-icons text-[13px] leading-none">place</span>
                            {{ $property->address ?: '—' }}
                        </span>
                    </div>
                </div>

                <!-- Floor / room counts -->
                <div class="hidden sm:flex items-center gap-4">
                    <div class="flex items-center gap-1.5" title="{{ __('messages.floors') }}">
                        <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                        <span class="text-xs font-semibold text-slate-700">{{ $property->floors_count }}</span>
                        <span class="text-[11px] text-slate-400">{{ __('messages.floors') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5" title="{{ __('messages.rooms') }}">
                        <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                        <span class="text-xs font-semibold text-sky-600">{{ $property->apartments_count }}</span>
                        <span class="text-[11px] text-slate-400">{{ __('messages.rooms') }}</span>
                    </div>
                </div>

                <!-- Supervisor + actions -->
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span class="hidden md:inline-flex items-center gap-1.5 text-xs text-slate-500 max-w-[10rem] truncate">
                        <svg class="w-3.5 h-3.5 text-slate-300 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <span class="truncate">{{ $property->supervisor?->name ?? __('messages.unassigned') }}</span>
                    </span>

                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.properties.edit', $property) }}"
                           title="{{ __('messages.edit') }}"
                           class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20 hover:bg-sky-50/40 transition">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" class="inline" onsubmit="return confirm('{{ __('messages.confirm_delete_title') }}')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    title="{{ __('messages.delete') }}"
                                    class="text-red-400 hover:text-red-600 p-2 rounded-lg bg-red-50/20 hover:bg-red-50/40 transition">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Compact counts + supervisor for small screens -->
            <div class="sm:hidden border-t border-slate-50 px-6 py-3 flex items-center gap-4 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                    {{ __('messages.floors') }}: <span class="font-semibold text-slate-700">{{ $property->floors_count }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                    {{ __('messages.rooms') }}: <span class="font-semibold text-sky-600">{{ $property->apartments_count }}</span>
                </span>
                <span class="ml-auto text-slate-400 truncate">{{ $property->supervisor?->name ?? __('messages.unassigned') }}</span>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl border border-slate-100 p-16 text-center">
            <div class="w-14 h-14 rounded-xl bg-slate-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                </svg>
            </div>
            <p class="font-medium text-slate-600">{{ __('messages.no_properties_yet') }}</p>
            <p class="text-slate-400 text-sm mt-1">{{ __('messages.no_properties_desc') }}</p>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if ($properties->hasPages())
    <div class="flex justify-center mt-6">
        {{ $properties->links() }}
    </div>
    @endif
</div>
@endsection
