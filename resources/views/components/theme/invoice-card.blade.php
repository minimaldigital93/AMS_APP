@props([
    'number' => '',        // invoice/receipt number
    'title' => null,       // e.g. tenant or property name
    'amount' => '',        // formatted amount (already includes symbol)
    'date' => null,
    'status' => null,      // raw status string
    'statusType' => 'success',
    'href' => null,
    'variant' => 'invoice', // invoice | receipt
])

{{-- <x-theme.invoice-card> — token-driven invoice / receipt card. --}}
<{{ $href ? 'a' : 'div' }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'block p-5',
        'ams-invoice-card' => $variant === 'invoice',
        'ams-receipt-card' => $variant === 'receipt',
    ]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="ams-muted text-xs uppercase tracking-wide font-semibold">
                {{ $variant === 'receipt' ? __('messages.receipt') : __('messages.invoice') }}
            </p>
            <h3 class="ams-title text-lg mt-0.5">#{{ $number }}</h3>
            @if($title)<p class="ams-muted text-sm truncate">{{ $title }}</p>@endif
        </div>
        @if($status)
            <x-theme.badge :type="$statusType">{{ status_label($status) }}</x-theme.badge>
        @endif
    </div>

    <div class="ams-invoice-divider mt-4 pt-4 flex items-end justify-between">
        <div>
            <p class="ams-muted text-xs">{{ __('messages.amount') }}</p>
            <p class="ams-title text-2xl tracking-tight">{{ $amount }}</p>
        </div>
        @if($date)
            <div class="text-right">
                <p class="ams-muted text-xs">{{ __('messages.date') }}</p>
                <p class="ams-text text-sm font-medium">{{ $date }}</p>
            </div>
        @endif
    </div>

    {{ $slot }}
</{{ $href ? 'a' : 'div' }}>
