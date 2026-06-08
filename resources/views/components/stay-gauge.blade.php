@props([
    'percent' => null,   // int|float|null — arc fill 0–100 (current monthly-cycle progress); null = no fixed cycle
    'label' => null,     // center text, e.g. "8 mo", "1 yr 2 mo" (total stay duration)
    'size' => 64,        // px width of the half-donut (height follows the 100×56 viewBox)
    'tip' => null,       // tooltip text; falls back to the centre label
])

@php
    $hasPercent = $percent !== null;
    $pct = max(0, min(100, (float) ($percent ?? 0)));

    // Semicircle (half-donut) arc length for radius 42 → π·42 ≈ 131.95
    $arc = 131.95;
    $offset = $arc * (1 - $pct / 100);

    // Sky early in the cycle, amber/rose as it fills toward renewal (rent due soon).
    $color = match (true) {
        ! $hasPercent => '#0ea5e9', // sky-500 — no fixed cycle
        $pct >= 90 => '#f43f5e',    // rose-500 — renewal imminent
        $pct >= 75 => '#f59e0b',    // amber-500
        default => '#0ea5e9',       // sky-500
    };

    $tip = $tip ?: $label;
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex justify-center']) }} title="{{ $tip }}">
    <svg viewBox="0 0 100 56" style="width: {{ (int) $size }}px" role="img" aria-label="{{ $tip }}">
        {{-- Track --}}
        <path d="M 8 50 A 42 42 0 0 1 92 50" fill="none" stroke="#e2e8f0" stroke-width="9" stroke-linecap="round"/>

        {{-- Fill --}}
        @if($hasPercent)
            <path d="M 8 50 A 42 42 0 0 1 92 50" fill="none" stroke="{{ $color }}" stroke-width="9"
                  stroke-linecap="round" stroke-dasharray="{{ $arc }}" stroke-dashoffset="{{ $offset }}"/>
        @else
            {{-- Open-ended lease: dashed arc signals "ongoing, no fixed end" --}}
            <path d="M 8 50 A 42 42 0 0 1 92 50" fill="none" stroke="{{ $color }}" stroke-width="9"
                  stroke-linecap="round" stroke-dasharray="3 7" opacity="0.75"/>
        @endif

        {{-- Center tenure label --}}
        <text x="50" y="47" text-anchor="middle" class="fill-slate-700 font-bold" style="font-size:17px">{{ $label }}</text>
    </svg>
</div>
