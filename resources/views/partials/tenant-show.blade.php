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
    $totalCollected = $history->where('paid', true)->sum('amount_paid');
    $totalDue = $unpaid->sum('rent_amount');
    $hasPhoto = $tenant->photo_path && ! \Illuminate\Support\Str::endsWith($tenant->photo_path, '.pdf');
    $statusLabel = method_exists($tenant, 'trashed') && $tenant->trashed() ? 'Departed' : ucfirst($tenant->status);
    $statusDisplay = __('messages.' . strtolower($statusLabel));
@endphp

<div x-data="{
        payOpen: false,
        pay: { rental_id: '', amount: '', date: '', label: '' },
        openPay(rentalId, amount, date, label) {
            this.pay = { rental_id: rentalId, amount: amount, date: date, label: label };
            this.payOpen = true;
        }
     }"
     class="max-w-4xl mx-auto space-y-6">

    {{-- Header (hidden when embedded inside another page) --}}
    @if($showHeader)
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.tenant_details') }}</h1>
            <p class="text-slate-400 text-sm mt-1">{{ $tenant->name }}</p>
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

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif
    @if(session('error') || session('warning'))
        <div class="bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-lg px-4 py-3">{{ session('error') ?? session('warning') }}</div>
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
                    <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full {{ $statusLabel === 'Active' ? 'bg-emerald-50 text-emerald-600' : ($statusLabel === 'Pending' ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-600') }}">
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
                        <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.email') }}</p>
                        <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->email ?: '—' }}</p>
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
                <p class="text-sm font-medium text-slate-800 mt-0.5">${{ number_format($tenant->apartment?->monthly_rent ?? optional($tenant->rentals->first())->rent_amount ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.deposit') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.move_in') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-400 uppercase tracking-wide">{{ __('messages.move_out') }}</p>
                <p class="text-sm font-medium text-slate-800 mt-0.5">{{ $tenant->move_out_date ? $tenant->move_out_date->format('M d, Y') : __('messages.not_set') }}</p>
            </div>
        </div>
    </div>

    {{-- 4. Payment Information & History --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.payment_history') }}</h3>
            <div class="flex items-center gap-2 text-xs">
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 font-medium">{{ __('messages.collected') }} ${{ number_format($totalCollected, 2) }}</span>
                @if($totalDue > 0)
                    <span class="px-2.5 py-1 rounded-full bg-red-50 text-red-600 font-medium">{{ __('messages.outstanding') }} ${{ number_format($totalDue, 2) }}</span>
                @endif
            </div>
        </div>

        @if($history->isEmpty())
            <p class="text-slate-400 text-sm">{{ __('messages.no_rental_period') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-400 uppercase tracking-wide border-b border-slate-100">
                            <th class="py-2 pr-4 font-medium">{{ __('messages.month') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('messages.apartment') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('messages.rent') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('messages.status') }}</th>
                            <th class="py-2 pr-0 font-medium text-right">{{ __('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($history as $row)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-2.5 pr-4 font-medium text-slate-800">{{ $row['label'] }}</td>
                                <td class="py-2.5 pr-4 text-slate-600">{{ $row['apartment'] ?? '—' }}</td>
                                <td class="py-2.5 pr-4 text-slate-700">${{ number_format($row['paid'] ? ($row['amount_paid'] ?? $row['rent_amount']) : $row['rent_amount'], 2) }}</td>
                                <td class="py-2.5 pr-4">
                                    @if($row['paid'])
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-600">{{ __('messages.paid') }}{{ $row['paid_at'] ? ' · '.$row['paid_at']->format('M d') : '' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">{{ __('messages.unpaid') }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-0 text-right">
                                    @if(! $row['paid'])
                                        <button type="button"
                                                @click="openPay('{{ $row['rental_id'] }}', '{{ $row['rent_amount'] }}', '{{ $row['pay_date'] }}', '{{ $row['label'] }}')"
                                                class="inline-flex items-center gap-1 {{ $btnCls }} text-white px-3 py-1 rounded-lg transition text-xs font-medium" title="{{ __('messages.pay') }}">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>
                                    @else
                                        <span class="text-slate-300 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 5. Attached Document --}}
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">{{ __('messages.attached_document') }}</h3>
        @if($tenant->document_path)
            <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank"
               class="inline-flex items-center gap-3 px-4 py-3 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 hover:border-slate-300 transition group">
                <div class="h-10 w-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-slate-700 group-hover:text-slate-900">{{ __('messages.view_document') }}</p>
                    <p class="text-xs text-slate-400">{{ basename($tenant->document_path) }}</p>
                </div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
            </a>
        @else
            <p class="text-slate-400 text-sm">{{ __('messages.no_document_attached') }}</p>
        @endif
    </div>

    {{-- Pay modal (records a rent payment for the selected month) --}}
    <div x-show="payOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
         @keydown.escape.window="payOpen = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6" @click.outside="payOpen = false">
            <h3 class="text-lg font-semibold text-slate-800 mb-1">{{ __('messages.record_rent_payment') }}</h3>
            <p class="text-sm text-slate-500 mb-4">{{ __('messages.settle_rent_for') }} <span class="font-medium text-slate-700" x-text="pay.label"></span>.</p>

            <form method="POST" action="{{ route($role.'.revenue_expense.store_income') }}">
                @csrf
                <input type="hidden" name="rental_id" :value="pay.rental_id">
                <input type="hidden" name="payment_type" value="rent">
                <input type="hidden" name="transaction_date" :value="pay.date">

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">{{ __('messages.amount') }}</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                            <input type="number" step="0.01" min="0.01" name="amount" x-model="pay.amount" required
                                   class="w-full pl-7 pr-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 {{ $ringCls }}">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">{{ __('messages.method') }}</label>
                        <select name="payment_method" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 {{ $ringCls }}">
                            <option value="cash">{{ __('messages.cash') }}</option>
                            <option value="bank">{{ __('messages.bank') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">{{ __('messages.note') }} <span class="text-slate-300 normal-case">({{ __('messages.optional') }})</span></label>
                        <input type="text" name="note" maxlength="1000"
                               class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 {{ $ringCls }}">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 mt-6">
                    <button type="button" @click="payOpen = false" class="px-4 py-2 text-sm font-medium text-slate-500 hover:text-slate-700">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 {{ $btnCls }} text-white rounded-lg transition text-sm font-medium">{{ __('messages.record_payment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
