@props([
    'label' => '',
    'value' => '',
    'icon' => null,         // SVG path `d` (24×24 outline, stroke-width 2)
    'tone' => 'slate',      // emerald | red | sky | orange | purple | amber | slate
    'valueClass' => null,   // defaults to the tone's figure colour
    'hint' => null,
])

{{-- <x-summary-card> — the admin dashboard's summary tile (white card,
     round tinted icon chip, small label over a bold figure), reusable on
     report pages so the two read as one system. Class strings are spelled
     out in full so Tailwind's scanner keeps them in the committed build.
     On a phone the chip stacks above the text and the figure wraps rather
     than truncates: a half-width tile beside a chip left ~70px for the
     amount, so every money figure read "$12,3…". --}}
@php
    $tones = [
        'emerald' => ['bg-emerald-50', 'text-emerald-600', 'to-emerald-50', 'border-emerald-100', 'text-emerald-700'],
        'red'     => ['bg-red-50', 'text-red-500', 'to-rose-50', 'border-red-100', 'text-red-600'],
        'sky'     => ['bg-sky-50', 'text-blue-600', 'to-sky-50', 'border-sky-100', 'text-sky-700'],
        'orange'  => ['bg-orange-50', 'text-orange-600', 'to-orange-50', 'border-orange-100', 'text-orange-600'],
        'purple'  => ['bg-purple-50', 'text-purple-600', 'to-purple-50', 'border-purple-100', 'text-purple-700'],
        'amber'   => ['bg-amber-50', 'text-amber-600', 'to-amber-50', 'border-amber-100', 'text-amber-600'],
        'slate'   => ['bg-slate-100', 'text-slate-600', 'to-slate-50', 'border-slate-200', 'text-slate-800'],
    ];
    [$chipBg, $chipText, $cardTo, $cardBorder, $figure] = $tones[$tone] ?? $tones['slate'];
    // A caller's explicit valueClass wins; the default follows the tone.
    $valueColor = $valueClass ?? $figure;
@endphp
<div {{ $attributes->class('bg-gradient-to-br from-white '.$cardTo.' rounded-xl border '.$cardBorder.' p-4 sm:p-5') }}>
    <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
        @if($icon)
            <div class="p-2 sm:p-3 rounded-full {{ $chipBg }} flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 sm:w-6 sm:h-6 {{ $chipText }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
            </div>
        @endif
        <div class="min-w-0 w-full">
            <p class="text-xs text-slate-400 font-medium">{{ $label }}</p>
            <p class="text-lg sm:text-xl font-bold leading-tight tabular-nums {{ $valueColor }} [overflow-wrap:anywhere]">{{ $value }}</p>
            @if($hint)
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $hint }}</p>
            @endif
        </div>
    </div>
    {{ $slot }}
</div>
