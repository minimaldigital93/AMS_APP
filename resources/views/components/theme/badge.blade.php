@props([
    'type' => 'neutral', // success | warning | danger | accent | neutral
    'icon' => null,
])

{{-- <x-theme.badge> — token-driven status pill. --}}
<span {{ $attributes->class([
    'ams-badge',
    'ams-badge-success' => $type === 'success',
    'ams-badge-warning' => $type === 'warning',
    'ams-badge-danger'  => $type === 'danger',
    'ams-badge-accent'  => $type === 'accent',
]) }}>
    @if($icon)<span class="material-icons" style="font-size:0.95rem">{{ $icon }}</span>@endif
    {{ $slot }}
</span>
