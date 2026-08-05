@props(['bill', 'future' => false, 'compact' => false])
{{--
    ONE status per row, four labels, still three buckets:

        Pending · Overdue · Rent Paid · Paid

    A bill has two sides that settle on separate visits (rent before the month
    ends, the meters at the turn of it), so "Rent Paid" is the label for the
    gap between them: rent is in, the charges side is not settled yet. It is a
    LABEL, not a fourth bucket — such a row is still `status = pending`, so the
    filter chips, the floor dots, the dashboard tiles and every count keep the
    paid/pending/overdue vocabulary they have always had. Paid still means the
    whole bill is settled, rent and charges both.

    Which side is unsettled ("charges due" vs "meters not read yet") stays on
    the tooltip — a second badge competing with the one status is exactly what
    was removed.
--}}
@php
    $status = $bill['status'] ?? 'pending';
    $upcoming = $future || ($bill['is_upcoming'] ?? false) || $status === 'upcoming';

    // Rent side. The tenant-index calculator has no separate rent_status —
    // its bucket IS the rent answer — so fall back to the row status.
    $rentStatus = $bill['rent_status'] ?? $status;

    // Charges side. 'none' is not 'paid': it means the meters haven't been read
    // yet, so nothing is owed *and* the month isn't finished. The caller passes
    // `charges_settled` because only it knows whether the month is still
    // running (a closed month that was never billed has nothing left to
    // collect); without it, settled means an actually-paid charges side.
    $chargesStatus = $bill['charges_status'] ?? 'none';
    $chargesSettled = (bool) ($bill['charges_settled'] ?? ($chargesStatus === 'paid'));

    [$label, $classes] = match (true) {
        $upcoming => [__('messages.upcoming'), 'bg-sky-100 text-sky-700'],
        $rentStatus === 'paid' && $chargesSettled => [__('messages.paid'), 'bg-emerald-100 text-emerald-700'],
        $rentStatus === 'paid' => [__('messages.rent_paid'), 'bg-teal-100 text-teal-700'],
        $status === 'overdue' => [__('messages.overdue'), 'bg-red-100 text-red-700'],
        default => [__('messages.pending'), 'bg-amber-100 text-amber-700'],
    };

    // Which side is still open, on hover only.
    $reason = (! $upcoming && $rentStatus === 'paid' && ! $chargesSettled)
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
