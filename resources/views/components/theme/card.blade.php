@props([
    'title' => null,
    'subtitle' => null,
    'hover' => true,   // lift + enhanced shadow on hover
    'as' => 'div',      // render as div / a / button
    'href' => null,
])

{{--
    <x-theme.card> — token-driven premium surface.
    16px radius · soft shadow · subtle border · optional hover elevation.
    All colors come from the active theme (no hardcoded values).
--}}
<{{ $as }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->class(['ams-card', 'ams-card-hover' => $hover, 'block' => $as === 'a']) }}
>
    @if($title || $subtitle)
        <div class="mb-4">
            @if($title)
                <h3 class="ams-title text-lg">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="ams-muted text-sm mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</{{ $as }}>
