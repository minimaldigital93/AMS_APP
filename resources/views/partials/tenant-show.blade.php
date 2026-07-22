{{--
    Universal tenant detail view — shared by admin and supervisor.

    Required vars:
      $tenant     : App\Models\Tenants (with apartment, rentals.apartment, rentals.payments loaded)
      $role       : 'admin' | 'supervisor'  (used to resolve route names)
    Optional vars:
      $showHeader : bool (default true) — set false when embedding inside another
                    page (e.g. the apartment view) to drop the title/back/actions bar.

    Sections: 1) Photo  2) Personal info  3) Tenancy info
              4) Payment info & history (pay unpaid months inline)  5) Attached document
--}}
@php
    // Full literal class strings (Tailwind only compiles classes it can see as
    // complete tokens — never build them with string interpolation).
    $showHeader = $showHeader ?? true;
    $isSup = $role === 'supervisor';
    $avatarCls = $isSup ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-600';
    $btnCls = $isSup ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700';
    $ringCls = $isSup ? 'focus:ring-emerald-500 focus:border-emerald-500' : 'focus:ring-blue-500 focus:border-blue-500';
    $history = $tenant->paymentHistory();
    $unpaid = $history->where('paid', false);
    // Combined outstanding debt: unpaid rent months + unpaid utility charges,
    // both carried forward until settled (see Tenants::outstandingCharges()).
    $outstanding = $tenant->outstandingCharges();
    $totalDue = $outstanding['total_due'];
    $unpaidUtilities = $outstanding['unpaid_utilities'];
    // Whether to show the "Collect Outstanding" action: admin/supervisor only,
    // active tenant, with debt owed.
    $canCollect = in_array($role, ['admin', 'supervisor'], true)
        && ! (method_exists($tenant, 'trashed') && $tenant->trashed())
        && $totalDue > 0;
    $hasPhoto = $tenant->photo_path && ! \Illuminate\Support\Str::endsWith($tenant->photo_path, '.pdf');
    // The lease this tenant's contract belongs to: the open one, else the latest.
    $contractRental = $tenant->rentals->firstWhere('end_date', null) ?? $tenant->rentals->sortByDesc('start_date')->first();
    // Fixed-term (3/6/12-month) contract detail + overdue state for this lease.
    $termMonths = $contractRental?->contract_term_months;
    $contractStart = $contractRental?->start_date;
    $contractEnd = $contractRental?->contractEndDate();
    $contractOverdue = (bool) ($contractRental?->contractIsOverdue());
    $contractMonthsOverdue = (int) ($contractRental?->contractMonthsOverdue() ?? 0);
    // Progress through the fixed lease term (null for open-ended leases). Percent
    // of the start→end window elapsed, plus days remaining / days overdue.
    $contractProgress = null;
    if ($termMonths && $contractStart && $contractEnd) {
        $cpStart = \Illuminate\Support\Carbon::parse($contractStart)->startOfDay();
        $cpToday = now()->startOfDay();
        $cpTotalDays = max(1, (int) $cpStart->diffInDays($contractEnd));
        $cpElapsedDays = max(0, (int) $cpStart->diffInDays($cpToday));
        $contractProgress = [
            'percent' => (int) max(0, min(100, round(($cpElapsedDays / $cpTotalDays) * 100))),
            'days_left' => max(0, (int) $cpToday->diffInDays($contractEnd, false)),
            'days_overdue' => $contractOverdue ? (int) $contractEnd->diffInDays($cpToday) : 0,
        ];
    }
    $rawStatus = method_exists($tenant, 'trashed') && $tenant->trashed() ? 'departed' : $tenant->status;
    $statusDisplay = status_label($rawStatus);
@endphp

<div class="max-w-4xl mx-auto space-y-6">

    {{-- Overdue contract alert: the fixed lease term has lapsed without a move-out
         or renewal. Surfaced to both admin and supervisor. --}}
    @if($contractOverdue)
    <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div>
            <p class="text-sm font-semibold text-red-700">{{ __('messages.contract_overdue') }}</p>
            <p class="text-xs text-red-600 mt-0.5">{{ __('messages.contract_overdue_detail', ['months' => $termMonths, 'date' => $contractEnd?->format('M d, Y'), 'overdue' => $contractMonthsOverdue]) }}</p>
        </div>
    </div>
    @endif

    {{-- Header (hidden when embedded inside another page) --}}
    @if($showHeader)
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.tenant_details') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            @if(! (method_exists($tenant, 'trashed') && $tenant->trashed()))
            <a href="{{ route($role.'.tenants.edit', $tenant) }}" class="inline-flex items-center gap-2 bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-700 transition text-sm font-medium" title="{{ __('messages.edit') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
            <a href="{{ route($role.'.tenants.leave', $tenant) }}" class="inline-flex items-center gap-2 bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition text-sm font-medium" title="{{ __('messages.process_leave') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg></a>
            @endif
            <a href="{{ route($role.'.tenants.index') }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </div>
    @endif

    {{-- 1 + 2: Photo & Personal Information --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex flex-col sm:flex-row items-start gap-6">
            {{-- 1. Photo --}}
            @if($hasPhoto)
                <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}"
                     class="h-24 w-24 rounded-xl object-cover border border-slate-200 shrink-0">
            @else
                <div class="h-24 w-24 rounded-xl {{ $avatarCls }} flex items-center justify-center font-bold text-3xl shrink-0">
                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                </div>
            @endif

            <div class="flex-1 w-full">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-slate-800">{{ $tenant->name }}</h2>
                    <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full {{ $rawStatus === 'active' ? 'bg-emerald-50 text-emerald-600' : ($rawStatus === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-600') }}">
                        {{ $statusDisplay }}
                    </span>
                </div>

                {{-- 2. Personal Information --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.phone') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->phone ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.id_card_number') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->id_card_number ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.gender') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->gender ? __('messages.'.$tenant->gender) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.date_of_birth') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->date_of_birth ? $tenant->date_of_birth->format('M d, Y') : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.place_of_birth') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->place_of_birth ?: '—' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.address') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->address ?: '—' }}</p>
                    </div>
                    @if($tenant->notes)
                    <div class="sm:col-span-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.notes') }}</p>
                        <p class="text-sm text-slate-700 mt-0.5">{{ $tenant->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Tenancy Information --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">{{ __('messages.tenancy_information') }}</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.apartment') }}</p>
                <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $tenant->apartment?->apartment_number ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.floor') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->apartment?->floor?->floor_name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.monthly_rent') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ money($tenant->apartment?->monthly_rent ?? optional($tenant->rentals->first())->rent_amount ?? 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.deposit') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ money($tenant->deposit ?? 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.move_in') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.move_out') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->move_out_date ? $tenant->move_out_date->format('M d, Y') : __('messages.not_set') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.contract_term') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $termMonths ? __('messages.term_n_months', ['n' => $termMonths]) : __('messages.open_ended') }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.contract_ends') }}</p>
                <p class="text-sm font-medium mt-0.5 {{ $contractOverdue ? 'text-red-600' : 'text-slate-800' }}">
                    {{ $contractEnd ? $contractEnd->format('M d, Y') : '—' }}
                    @if($contractOverdue)<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-600">{{ __('messages.overdue') }}</span>@endif
                </p>
            </div>
        </div>
    </div>

    {{-- 4. Payment Information & History --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6" @if($canCollect) x-data="{ collectOpen: false }" @endif>
        <div class="flex items-start justify-between gap-3 mb-4">
            <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.payment_history') }}</h3>
            @if($totalDue > 0)
                <div class="flex flex-col items-end gap-2">
                    <div class="flex flex-wrap items-center justify-end gap-1.5 text-xs">
                        <span class="px-2.5 py-1 rounded-full bg-red-50 text-red-600 font-semibold">{{ __('messages.outstanding') }} {{ money($totalDue) }}</span>
                        @if($outstanding['rent_due'] > 0 && $outstanding['utilities_due'] > 0)
                            <span class="px-2 py-1 rounded-full bg-slate-50 text-slate-500 font-medium">{{ __('messages.rent') }} {{ money($outstanding['rent_due']) }}</span>
                            <span class="px-2 py-1 rounded-full bg-slate-50 text-slate-500 font-medium">{{ __('messages.utilities') }} {{ money($outstanding['utilities_due']) }}</span>
                        @endif
                    </div>
                    @if($canCollect)
                        <button type="button" @click="collectOpen = true"
                                class="inline-flex items-center gap-1.5 bg-emerald-600 text-white px-3 py-1.5 rounded-lg hover:bg-emerald-700 transition text-xs font-semibold">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('messages.collect_outstanding') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @if($canCollect)
            @php
                // Combined selectable debt items (rent months + utility charges).
                $collectItems = collect();
                foreach ($outstanding['unpaid_months'] as $m) {
                    $collectItems->push([
                        'id' => 'rent_'.$m['rental_id'].'_'.$m['year'].'_'.$m['month'],
                        'group' => __('messages.rent'),
                        'label' => $m['label'],
                        'amount' => (float) $m['rent_amount'],
                    ]);
                }
                foreach ($unpaidUtilities as $u) {
                    $collectItems->push([
                        'id' => 'utility_'.$u->id,
                        'group' => status_label($u->type),
                        'label' => $u->label,
                        'amount' => (float) $u->amount,
                    ]);
                }
                $collectAmounts = $collectItems->mapWithKeys(fn ($i) => [$i['id'] => $i['amount']]);
                $collectIds = $collectItems->pluck('id')->values();
            @endphp
            {{-- Collect-outstanding modal: pick which unpaid rent months / charges to settle. --}}
            <div x-show="collectOpen" x-cloak
                 x-data="{ amounts: @js($collectAmounts), selected: @js($collectIds),
                           get total() { return this.selected.reduce((s, id) => s + (this.amounts[id] || 0), 0); },
                           allChecked: true,
                           toggleAll() { this.selected = this.allChecked ? Object.keys(this.amounts) : []; } }"
                 class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
                 @keydown.escape.window="collectOpen = false">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md my-auto" @click.outside="collectOpen = false">
                    <form method="POST" action="{{ route($role.'.revenue_expense.collect_outstanding', $tenant) }}">
                        @csrf
                        <div class="px-6 py-4 border-b">
                            <h3 class="text-lg font-semibold text-slate-800">{{ __('messages.collect_outstanding') }}</h3>
                            <p class="text-sm text-slate-500 mt-1">{{ __('messages.collect_outstanding_pick', ['name' => $tenant->name]) }}</p>
                        </div>
                        <div class="px-6 py-4 space-y-4">
                            {{-- Item checklist --}}
                            <div>
                                <label class="flex items-center justify-between gap-2 pb-2 mb-1 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide cursor-pointer">
                                    <span class="flex items-center gap-2">
                                        <input type="checkbox" x-model="allChecked" @change="toggleAll()"
                                               class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        {{ __('messages.select_all') }}
                                    </span>
                                </label>
                                <div class="max-h-52 overflow-y-auto divide-y divide-slate-100">
                                    @foreach($collectItems as $item)
                                        <label class="flex items-center justify-between gap-3 py-2 cursor-pointer">
                                            <span class="flex items-center gap-2 min-w-0">
                                                <input type="checkbox" name="selection[]" value="{{ $item['id'] }}"
                                                       x-model="selected"
                                                       @change="allChecked = selected.length === Object.keys(amounts).length"
                                                       class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 shrink-0">
                                                <span class="truncate text-sm text-slate-700">
                                                    <span class="text-slate-400">{{ $item['group'] }} ·</span> {{ $item['label'] }}
                                                </span>
                                            </span>
                                            <span class="text-sm font-semibold text-slate-700 shrink-0">{{ money($item['amount']) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.payment_method') }}</label>
                                <select name="payment_method" required
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 {{ $ringCls }}">
                                    <option value="cash">{{ __('messages.cash') }}</option>
                                    <option value="bank">{{ __('messages.bank_transfer') }}</option>
                                    <option value="khqr">KHQR</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.payment_date') }}</label>
                                <input type="date" name="payment_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 {{ $ringCls }}">
                            </div>
                            <p class="text-xs text-slate-500">{{ __('messages.collect_outstanding_note') }}</p>
                        </div>
                        <div class="px-6 py-4 border-t flex justify-end gap-2">
                            <button type="button" @click="collectOpen = false"
                                    class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-sm font-semibold">{{ __('messages.cancel') }}</button>
                            <button type="submit" :disabled="selected.length === 0"
                                    class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ __('messages.collect') }} <span x-text="'{{ currency_symbol() }}' + total.toFixed(2)"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($history->isEmpty())
            <p class="text-slate-400 text-sm">{{ __('messages.no_rental_period') }}</p>
        @else
            {{-- One row per renting month: month/year · amount paid · status. --}}
            <div class="divide-y divide-slate-100">
                @foreach($history as $row)
                    @php
                        $paid = $row['paid'];
                        $amount = $row['amount_paid'] ?? $row['rent_amount'];
                    @endphp
                    <div class="flex items-center justify-between gap-3 py-3">
                        <p class="text-sm font-medium text-slate-700 w-20 shrink-0">{{ $row['label'] }}</p>
                        <p class="text-sm font-semibold {{ $paid ? 'text-emerald-700' : 'text-slate-400' }} flex-1 text-right">{{ money($amount) }}</p>
                        @if($paid)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-600 w-20 justify-center shrink-0">{{ __('messages.paid') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600 w-20 justify-center shrink-0">{{ __('messages.unpaid') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Unpaid utility/other charges carried forward (any month, until settled). --}}
        @if($unpaidUtilities->isNotEmpty())
            <div class="mt-5 pt-4 border-t border-slate-100">
                <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">{{ __('messages.unpaid_charges') }}</h4>
                <div class="divide-y divide-slate-100">
                    @foreach($unpaidUtilities as $charge)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="text-slate-500 w-20 shrink-0">{{ $charge->label }}</span>
                            <span class="text-slate-700 flex-1 capitalize">{{ status_label($charge->type) }}</span>
                            <span class="font-semibold text-red-600 shrink-0">{{ money($charge->amount) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- 5. ID Documents --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">{{ __('messages.tenant_documents') }}</h3>
        @if($tenant->attachments->isNotEmpty())
            <ul class="space-y-1">
                @foreach($tenant->attachments as $doc)
                    @php $docIsImage = $doc->isImage(); @endphp
                    <li class="flex items-center gap-2 text-sm bg-slate-50 rounded-lg px-3 py-1.5">
                        <button type="button"
                                onclick="openDocPreview(@js($doc->url()), @js($doc->original_name), {{ $docIsImage ? 'true' : 'false' }})"
                                class="flex-1 min-w-0 flex items-center gap-2 text-left text-sky-700 hover:underline">
                            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                            <span class="truncate">{{ $doc->original_name }}</span>
                        </button>
                        <form action="{{ route($role.'.tenants.destroy_document', [$tenant, $doc]) }}" method="POST" data-confirm="{{ __('messages.remove_attachment_confirm') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-600 text-base leading-none px-1" title="{{ __('messages.delete') }}">&times;</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-slate-400 text-sm">{{ __('messages.no_document_attached') }}</p>
        @endif
    </div>

    {{-- 6. Lease Contract (admin panel only — superadmin + admin) --}}
    @if($role === 'admin' && $contractRental)
        @php $hasContract = $contractRental->hasContract(); @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.lease_contract') }}</h3>
                @if($hasContract)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-600">{{ __('messages.contract_generated') }}</span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-600">{{ __('messages.contract_not_generated') }}</span>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.contract_number') }}</p>
                    <p class="text-sm font-semibold text-slate-800 mt-0.5">{{ $contractRental->contract_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.contract_generated_on') }}</p>
                    <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $contractRental->contract_generated_at ? $contractRental->contract_generated_at->format('M d, Y') : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.status') }}</p>
                    <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $hasContract ? __('messages.contract_generated') : __('messages.contract_not_generated') }}</p>
                </div>
            </div>

            {{-- Contract-term progress bar: how far the fixed lease term has run
                 (start_date → term end). Only shown for fixed-term leases. --}}
            @if($contractProgress)
                <div class="mb-8">
                    <div class="flex items-center justify-between text-xs mb-2">
                        <span class="font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.contract_progress') }}</span>
                        @if($contractOverdue)
                            <span class="font-semibold text-red-600">{{ __('messages.contract_days_overdue', ['days' => $contractProgress['days_overdue']]) }}</span>
                        @else
                            <span class="font-semibold text-slate-600">{{ __('messages.days_left', ['days' => $contractProgress['days_left']]) }}</span>
                        @endif
                    </div>
                    <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                        <div class="h-full rounded-full transition-all {{ $contractOverdue ? 'bg-red-500' : ($contractProgress['percent'] >= 80 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                             style="width: {{ max($contractProgress['percent'], 2) }}%"></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1.5">
                        <span>{{ $contractStart->format('M d, Y') }}</span>
                        <span>{{ __('messages.contract_percent_elapsed', ['percent' => $contractProgress['percent']]) }}</span>
                        <span class="{{ $contractOverdue ? 'text-red-500 font-medium' : '' }}">{{ $contractEnd->format('M d, Y') }}</span>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2" x-data="{ renewOpen: false }">
                <a href="{{ route('admin.contracts.view', $contractRental) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    {{ __('messages.preview') }} / {{ __('messages.print') }}
                </a>
                <a href="{{ route('admin.contracts.download', $contractRental) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-slate-100 border border-slate-200 hover:bg-slate-200 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    {{ __('messages.download') }}
                </a>
                <button type="button" @click="renewOpen = true"
                        class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 hover:bg-amber-100 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    {{ __('messages.regenerate_or_renew') }}
                </button>

                {{-- Regenerate / Renew dialog: optionally extend the fixed term by
                     3/6/12 months, then rebuild the PDF (same contract number). --}}
                <div x-show="renewOpen" x-cloak
                     x-data="{ months: '' }"
                     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
                     @keydown.escape.window="renewOpen = false">
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-md my-auto" @click.outside="renewOpen = false">
                        <form method="POST" action="{{ route('admin.contracts.regenerate', $contractRental) }}">
                            @csrf
                            <div class="px-6 py-4 border-b">
                                <h3 class="text-lg font-semibold text-slate-800">{{ __('messages.renew_contract') }}</h3>
                                <p class="text-sm text-slate-500 mt-1">{{ __('messages.renew_contract_prompt') }}</p>
                            </div>
                            <div class="px-6 py-4 space-y-3">
                                @if($contractEnd)
                                    <p class="text-xs text-slate-500">
                                        {{ __('messages.contract_ends') }}:
                                        <span class="font-semibold {{ $contractOverdue ? 'text-red-600' : 'text-slate-700' }}">{{ $contractEnd->format('M d, Y') }}</span>
                                    </p>
                                @endif
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach([3, 6, 12] as $m)
                                        <label class="flex items-center justify-center gap-2 px-3 py-2.5 text-sm border rounded-lg cursor-pointer transition"
                                               :class="months === '{{ $m }}' ? 'border-amber-500 bg-amber-50 text-amber-700 font-semibold' : 'border-slate-200 bg-slate-50/50 text-slate-600 hover:border-slate-300'">
                                            <input type="radio" name="renew_months" value="{{ $m }}" x-model="months" class="sr-only">
                                            {{ __('messages.renew_plus_months', ['n' => $m]) }}
                                        </label>
                                    @endforeach
                                </div>
                                <label class="flex items-center gap-2 px-3 py-2.5 text-sm border rounded-lg cursor-pointer transition"
                                       :class="months === '' ? 'border-slate-400 bg-slate-50 text-slate-700 font-medium' : 'border-slate-200 text-slate-500 hover:border-slate-300'">
                                    <input type="radio" name="renew_months" value="" x-model="months" class="text-slate-600 focus:ring-slate-400">
                                    {{ __('messages.keep_current_term') }}
                                </label>
                            </div>
                            <div class="px-6 py-4 border-t flex justify-end gap-2">
                                <button type="button" @click="renewOpen = false"
                                        class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-sm font-semibold">{{ __('messages.cancel') }}</button>
                                <button type="submit"
                                        class="px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 text-sm font-semibold"
                                        x-text="months === '' ? '{{ __('messages.regenerate_contract') }}' : '{{ __('messages.renew_contract') }}'"></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Document Preview Modal --}}
<div id="docPreviewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" onclick="if(event.target === this) closeDocPreview()">
    <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
            <p id="docPreviewName" class="text-sm font-medium text-slate-700 truncate pr-4"></p>
            <div class="flex items-center gap-2 shrink-0">
                <a id="docPreviewDownload" href="#" download
                   class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-white bg-slate-800 hover:bg-slate-900 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    {{ __('messages.download') }}
                </a>
                <button type="button" onclick="closeDocPreview()" class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition" aria-label="{{ __('messages.close') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-auto bg-slate-100 flex items-center justify-center">
            <img id="docPreviewImage" src="" alt="" class="hidden max-w-full max-h-[78vh] object-contain mx-auto">
            <iframe id="docPreviewFrame" src="" class="hidden w-full h-[78vh] border-0"></iframe>
        </div>
    </div>
</div>

<script>
function openDocPreview(url, name, isImage){
    var modal = document.getElementById('docPreviewModal');
    var img = document.getElementById('docPreviewImage');
    var frame = document.getElementById('docPreviewFrame');
    document.getElementById('docPreviewName').textContent = name;
    document.getElementById('docPreviewDownload').setAttribute('href', url);
    document.getElementById('docPreviewDownload').setAttribute('download', name);
    if(isImage){
        img.src = url; img.classList.remove('hidden');
        frame.src = ''; frame.classList.add('hidden');
    } else {
        frame.src = url; frame.classList.remove('hidden');
        img.src = ''; img.classList.add('hidden');
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function closeDocPreview(){
    var modal = document.getElementById('docPreviewModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.getElementById('docPreviewImage').src = '';
    document.getElementById('docPreviewFrame').src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') closeDocPreview();
});
</script>
