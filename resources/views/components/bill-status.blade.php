@props(['bill', 'future' => false, 'compact' => false])
{{--
    ONE status per row: Paid · Pending · Overdue. Paid means the whole bill is
    settled — rent and charges both — so a rent-paid tenant whose charges are
    still owed (or whose meters haven't been read yet) reads Pending.

    The two sides are still tracked server-side; they only surface here as the
    badge's tooltip, so the collector can see why a row is pending without a
    second badge competing with the one status.
--}}
@php
    $upcoming = $future || ($bill['is_upcoming'] ?? false);
    $status = $bill['status'] ?? 'pending';
    $chargesStatus = $bill['charges_status'] ?? 'none';

    [$label, $classes] = match (true) {
        $upcoming => [__('messages.upcoming'), 'bg-sky-100 text-sky-700'],
        $status === 'paid' => [__('messages.paid'), 'bg-emerald-100 text-emerald-700'],
        $status === 'overdue' => [__('messages.overdue'), 'bg-red-100 text-red-700'],
        default => [__('messages.pending'), 'bg-amber-100 text-amber-700'],
    };

    // Why it's pending, on hover only. 'none' is not 'paid' — it means the
    // meters haven't been read yet, so the month isn't finished.
    $reason = ($status === 'pending' && ($bill['rent_status'] ?? null) === 'paid')
        ? ($chargesStatus === 'pending'
            ? __('messages.rent_paid_charges_due')
            : __('messages.rent_paid_charges_unread'))
        : null;
@endphp
@if($compact)
    <span @if($reason) title="{{ $reason }}" @endif
          class="mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded whitespace-nowrap {{ $classes }}">{{ $label }}</span>
@else
    <span @if($reason) title="{{ $reason }}" @endif
          class="px-2 py-1 text-xs font-semibold rounded-md whitespace-nowrap {{ $classes }}">{{ $label }}</span>
@endif
