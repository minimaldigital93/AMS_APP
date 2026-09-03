@props(['backlog'])
{{--
    "Close last month" — the dashboard's standing reminder, and the notice that
    money entry has stopped once two finished months are open.

    Two states, one component, because they are the same fact at two sizes:
      amber  — one finished month is open. A reminder; dismissible.
      red    — two or more are. Recording income and expenses is blocked
               (EnsureMonthCloseBacklogClear), so this is NOT dismissible: the
               banner is the only place that explains why the next save fails.

    The button appears only for a user who can actually close a month (an
    admin — `close_url` is null for a supervisor, who is told to ask the owner).
--}}
@php
    $blocking = $backlog['blocking'];
    $months = implode(', ', $backlog['names']);
@endphp
<div @if(! $blocking) x-data="{ show: true }" x-show="show" @endif
     class="rounded-lg px-4 py-3 text-sm flex items-start sm:items-center justify-between gap-3 border {{ $blocking ? 'bg-red-50 border-red-100 text-red-700' : 'bg-amber-50 border-amber-100 text-amber-700' }}">
    <div class="flex items-start sm:items-center gap-2.5 min-w-0">
        <svg class="w-4 h-4 shrink-0 mt-0.5 sm:mt-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M6 2a1 1 0 011 1v1h6V3a1 1 0 112 0v1h1a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2h1V3a1 1 0 011-1zm11 6H3v8h14V8z" clip-rule="evenodd"/>
        </svg>
        <span class="font-medium">
            @if($blocking)
                {{ __('messages.month_close_blocked_banner', ['count' => $backlog['count'], 'months' => $months, 'month' => $backlog['oldest']->name]) }}
            @else
                {{ __('messages.month_close_due_banner', ['month' => $backlog['oldest']->name]) }}
            @endif
            @if(! $backlog['close_url'])
                <span class="block font-normal opacity-90">{{ __('messages.month_close_ask_owner') }}</span>
            @endif
        </span>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        @if($backlog['close_url'])
            <a href="{{ $backlog['close_url'] }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white transition {{ $blocking ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700' }}">
                {{ __('messages.close_month_now', ['month' => $backlog['oldest']->name]) }}
            </a>
        @endif
        @if(! $blocking)
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100 transition" aria-label="{{ __('messages.dismiss') }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        @endif
    </div>
</div>
