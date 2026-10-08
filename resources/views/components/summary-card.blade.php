@props([
    'label' => '',
    'value' => '',
    'icon' => null,         // SVG path `d` (24×24 outline, stroke-width 2)
    'tone' => 'slate',      // emerald | red | sky | orange | purple | amber | slate
    'valueClass' => 'text-slate-800',
    'hint' => null,
])

{{-- <x-summary-card> — the admin dashboard's summary tile (white card,
     round tinted icon chip, small label over a bold figure), reusable on
     report pages so the two read as one system. Class strings are spelled
     out in full so Tailwind's scanner keeps them in the committed build. --}}
@php
    $tones = [
        'emerald' => ['bg-emerald-50', 'text-emerald-600'],
        'red'     => ['bg-red-50', 'text-red-500'],
        'sky'     => ['bg-sky-50', 'text-blue-600'],
        'orange'  => ['bg-orange-50', 'text-orange-600'],
        'purple'  => ['bg-purple-50', 'text-purple-600'],
        'amber'   => ['bg-amber-50', 'text-amber-600'],
        'slate'   => ['bg-slate-100', 'text-slate-600'],
    ];
    [$chipBg, $chipText] = $tones[$tone] ?? $tones['slate'];
@endphp
<div {{ $attributes->class('bg-white rounded-xl border border-slate-100 p-5') }}>
    <div class="flex items-center gap-3">
        @if($icon)
            <div class="p-3 rounded-full {{ $chipBg }} flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 {{ $chipText }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
            </div>
        @endif
        <div class="min-w-0">
            <p class="text-xs text-slate-400 font-medium">{{ $label }}</p>
            <p class="text-xl font-bold {{ $valueClass }} truncate">{{ $value }}</p>
            @if($hint)
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $hint }}</p>
            @endif
        </div>
    </div>
    {{ $slot }}
</div>
