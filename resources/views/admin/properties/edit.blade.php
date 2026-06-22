@extends('layouts.admin')

@section('title', __('messages.edit_property'))

@section('content')
<div class="max-w-2xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.edit_property') }}</h1>
        <a href="{{ route('admin.properties.index') }}" title="{{ __('messages.back_to_properties') }}" class="inline-flex items-center justify-center text-slate-400 hover:text-slate-600 p-2 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
    </div>

    @if (session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-slate-100">
        <form method="POST" action="{{ route('admin.properties.update', $property) }}">
            @csrf @method('PUT')
            @include('admin.properties._form', ['property' => $property])
        </form>
    </div>
</div>
@endsection
