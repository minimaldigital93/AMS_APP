@props([
    'label' => '',
    'value' => '',
    'icon' => null,        // Material Icons ligature, e.g. "apartment"
    'trend' => null,       // e.g. "+12.4%"
    'trendType' => 'success', // success | danger | warning | neutral
    'hint' => null,
])

{{-- <x-theme.stat-card> — dashboard widget. Token-driven, hover-elevating. --}}
<div {{ $attributes->class('ams-stat-card') }}>
    <div class="flex items-start justify-between gap-3">
        <span class="ams-stat-label">{{ $label }}</span>
        @if($icon)
            <span class="ams-stat-icon">
                <span class="material-icons" style="font-size:1.25rem">{{ $icon }}</span>
            </span>
        @endif
    </div>

    <div class="ams-stat-value">{{ $value }}</div>

    @if($trend || $hint)
        <div class="flex items-center gap-2 mt-1">
            @if($trend)
                <span @class([
                    'ams-badge',
                    'ams-badge-success' => $trendType === 'success',
                    'ams-badge-danger' => $trendType === 'danger',
                    'ams-badge-warning' => $trendType === 'warning',
                ])>{{ $trend }}</span>
            @endif
            @if($hint)
                <span class="ams-muted text-xs">{{ $hint }}</span>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
