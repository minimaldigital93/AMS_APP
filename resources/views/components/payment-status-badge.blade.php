@props(['status'])
@php
    $map = [
        'paid' => 'bg-green-100 text-green-700',
        'qr_generated' => 'bg-blue-100 text-blue-700',
        'waiting_payment' => 'bg-blue-100 text-blue-700',
        'pending' => 'bg-gray-100 text-gray-600',
        'expired' => 'bg-amber-100 text-amber-700',
        'failed' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-gray-100 text-gray-500',
        'rejected' => 'bg-red-100 text-red-700',
        'refunded' => 'bg-purple-100 text-purple-700',
    ];
    $cls = $map[$status] ?? 'bg-gray-100 text-gray-600';
@endphp
<span {{ $attributes->merge(['class' => "inline-block text-xs px-2 py-0.5 rounded $cls"]) }}>{{ status_label($status) }}</span>
