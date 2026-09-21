@extends('layouts.tenant')

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">{{ __('messages.settings') }}</h1>
    </div>

    {{-- Account context --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                <span class="material-icons">account_circle</span>
            </span>
            <div class="min-w-0">
                <p class="font-semibold text-gray-900 truncate">{{ auth()->user()->name }}</p>
                <p class="text-sm text-slate-500 truncate">{{ auth()->user()->phone }}</p>
            </div>
        </div>
    </div>

    {{-- Language --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sm:p-6">
        <h2 class="font-semibold text-slate-800 mb-3">{{ __('messages.language') }}</h2>
        <form method="POST" action="{{ route('language.switch') }}" class="flex gap-2">
            @csrf
            <button type="submit" name="locale" value="en"
                class="flex-1 rounded-xl px-4 py-3 text-sm font-semibold border transition {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-slate-200 hover:bg-slate-50' }}">
                English
            </button>
            <button type="submit" name="locale" value="km"
                class="flex-1 rounded-xl px-4 py-3 text-sm font-semibold border transition {{ app()->getLocale() === 'km' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-slate-200 hover:bg-slate-50' }}">
                ខ្មែរ
            </button>
        </form>
    </div>

    {{-- Logout --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 sm:p-6">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-red-700 bg-red-50 hover:bg-red-100 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                {{ __('messages.logout') }}
            </button>
        </form>
    </div>
</div>
@endsection
