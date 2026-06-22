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
        <a href="{{ route('admin.properties.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            {{ __('messages.add_property') }}
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
    @endif

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
                        <th class="px-4 py-3">{{ __('messages.property_name') }}</th>
                        <th class="px-4 py-3">{{ __('messages.property_address') }}</th>
                        <th class="px-4 py-3">{{ __('messages.assign_supervisor') }}</th>
                        <th class="px-4 py-3">{{ __('messages.floors') }}</th>
                        <th class="px-4 py-3">{{ __('messages.rooms') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($properties as $property)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ $property->name }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $property->address ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $property->supervisor?->name ?? __('messages.unassigned') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $property->floors_count }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $property->apartments_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.properties.edit', $property) }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">{{ __('messages.edit') }}</a>
                                <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" class="inline" onsubmit="return confirm('{{ __('messages.confirm_delete_title') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ml-3 text-sm font-medium text-red-400 hover:text-red-600">{{ __('messages.delete') }}</button>
                                </form>
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
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-base font-semibold text-slate-800">{{ $property->name }}</p>
                            <p class="text-sm text-slate-500">{{ $property->address ?: '—' }}</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ $property->supervisor?->name ?? __('messages.unassigned') }}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-xs text-slate-500">
                        <span>{{ __('messages.floors') }}: <span class="font-medium text-slate-700">{{ $property->floors_count }}</span></span>
                        <span>{{ __('messages.rooms') }}: <span class="font-medium text-slate-700">{{ $property->apartments_count }}</span></span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <a href="{{ route('admin.properties.edit', $property) }}" class="flex-1 text-center text-sm font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 py-2 rounded-lg transition">{{ __('messages.edit') }}</a>
                        <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" class="flex-1" onsubmit="return confirm('{{ __('messages.confirm_delete_title') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full text-sm font-medium text-red-500 bg-red-50 hover:bg-red-100 py-2 rounded-lg transition">{{ __('messages.delete') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        {{ $properties->links() }}
    @endif
</div>
@endsection
