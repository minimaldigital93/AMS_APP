@extends('layouts.admin')

@section('title', __('messages.property_management'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.property_management') }}</h1>
            @php($max = $usage['properties_max'])
            <p class="mt-1 text-sm text-slate-500">{{ __('messages.properties') }}: {{ $usage['properties_used'] }} / {{ $max ?? '∞' }}</p>
        </div>
        <a href="{{ route('admin.properties.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="{{ __('messages.add_property') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        </a>
    </div>

    @if ($properties->isEmpty())
        <div class="rounded-xl border border-slate-100 bg-white p-12 text-center">
            <p class="text-slate-500">{{ __('messages.no_properties_yet') }}</p>
            <p class="mt-1 text-sm text-slate-400">{{ __('messages.no_properties_desc') }}</p>
        </div>
    @else
        <!-- Desktop table -->
        <div class="hidden md:block rounded-xl border border-slate-100 bg-white overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/80 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">{{ __('messages.property_name') }}</th>
                        <th class="px-4 py-3">{{ __('messages.property_address') }}</th>
                        <th class="px-4 py-3">{{ __('messages.assign_supervisor') }}</th>
                        <th class="px-4 py-3">{{ __('messages.floors') }}</th>
                        <th class="px-4 py-3">{{ __('messages.rooms') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($properties as $property)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-sm text-slate-500">{{ ($properties->currentPage() - 1) * $properties->perPage() + $loop->iteration }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $property->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $property->address ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $property->supervisor?->name ?? __('messages.unassigned') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $property->floors_count }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $property->apartments_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.properties.edit', $property) }}"
                                       title="{{ __('messages.edit') }}"
                                       class="inline-flex items-center justify-center p-1.5 rounded-lg text-sky-600 hover:bg-sky-50 transition">
                                        <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" class="inline" onsubmit="return confirm('{{ __('messages.confirm_delete_title') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                title="{{ __('messages.delete') }}"
                                                class="inline-flex items-center justify-center p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                                            <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden space-y-3">
            @foreach ($properties as $property)
                <div class="rounded-xl border border-slate-100 bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-slate-800 truncate">{{ $property->name }}</p>
                            <p class="text-sm text-slate-500">{{ $property->address ?: '—' }}</p>
                        </div>
                        <div class="shrink-0 flex items-center gap-1.5">
                            <a href="{{ route('admin.properties.edit', $property) }}"
                               title="{{ __('messages.edit') }}"
                               class="inline-flex items-center justify-center p-2 rounded-lg text-sky-600 bg-sky-50 hover:bg-sky-100 transition">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" onsubmit="return confirm('{{ __('messages.confirm_delete_title') }}')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        title="{{ __('messages.delete') }}"
                                        class="inline-flex items-center justify-center p-2 rounded-lg text-red-500 bg-red-50 hover:bg-red-100 transition">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                        <span>{{ __('messages.floors') }}: <span class="font-medium text-slate-700">{{ $property->floors_count }}</span></span>
                        <span>{{ __('messages.rooms') }}: <span class="font-medium text-slate-700">{{ $property->apartments_count }}</span></span>
                        <span class="ml-auto text-slate-400 truncate">{{ $property->supervisor?->name ?? __('messages.unassigned') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{ $properties->links() }}
    @endif
</div>
@endsection
