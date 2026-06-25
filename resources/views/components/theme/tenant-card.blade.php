@props([
    'name' => '',
    'unit' => null,        // e.g. "Room 304 · Sunrise Tower"
    'phone' => null,
    'status' => null,      // raw status string (occupied / pending / ...)
    'statusType' => 'success',
    'href' => null,
    'avatar' => null,      // image URL; falls back to initials
])

@php
    $initials = collect(explode(' ', trim($name)))
        ->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp

{{-- <x-theme.tenant-card> — token-driven tenant card. --}}
<{{ $href ? 'a' : 'div' }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->class('ams-tenant-card block p-5') }}>
    <div class="flex items-center gap-4">
        @if($avatar)
            <img src="{{ $avatar }}" alt="{{ $name }}" class="ams-avatar w-12 h-12 object-cover">
        @else
            <span class="ams-avatar w-12 h-12 text-base">{{ $initials ?: '?' }}</span>
        @endif

        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
                <h3 class="ams-title text-base truncate">{{ $name }}</h3>
                @if($status)
                    <x-theme.badge :type="$statusType">{{ status_label($status) }}</x-theme.badge>
                @endif
            </div>
            @if($unit)<p class="ams-muted text-sm truncate mt-0.5">{{ $unit }}</p>@endif
            @if($phone)
                <p class="ams-muted text-xs mt-1 flex items-center gap-1">
                    <span class="material-icons" style="font-size:0.95rem">call</span>{{ $phone }}
                </p>
            @endif
        </div>
    </div>

    @if($slot->isNotEmpty())
        <div class="ams-invoice-divider mt-4 pt-4">{{ $slot }}</div>
    @endif
</{{ $href ? 'a' : 'div' }}>
