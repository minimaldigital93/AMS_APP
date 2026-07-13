{{--
    Shared tenant move-out form (admin + supervisor).

    Expects: $tenant, $rental, $pendingCharges, plus:
      $formAction — POST target (processLeave route)
      $backUrl    — cancel / back link
--}}
@php
    $step = 0;
@endphp

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="leaveForm()">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.process_tenant_leave') }}</h1>
        <a href="{{ $backUrl }}" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition" title="{{ __('messages.back_to_tenants') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
    </div>

    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm mb-6">
        <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif

    <form x-ref="form" action="{{ $formAction }}" method="POST" class="space-y-5">
        @csrf
        <input type="hidden" name="charge_full_month" :value="fullMonth ? '1' : '0'">
        @if(($tenant->deposit ?? 0) > 0)
            <input type="hidden" name="deposit_action" :value="depositAction">
        @endif

            <!-- Tenant Info -->
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center gap-4 mb-4">
                    @if($tenant->photo_path)
                        <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-14 w-14 rounded-lg object-cover border">
                    @else
                        <div class="h-14 w-14 rounded-lg bg-blue-100 flex items-center justify-center border">
                            <span class="text-xl font-bold text-blue-600">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                        </div>
                    @endif
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</h2>
                        <p class="text-sm text-gray-500">{{ __('messages.apt_short') }} {{ $tenant->apartment?->apartment_number ?? 'N/A' }} &middot; {{ $tenant->phone ?? '' }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400">{{ __('messages.move_in') }}</p>
                        <p class="font-semibold text-slate-800">{{ $tenant->move_in_date?->format('M d, Y') ?? 'N/A' }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400">{{ __('messages.monthly_rent') }}</p>
                        <p class="font-semibold text-slate-800">{{ money($rental->rent_amount ?? 0) }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400">{{ __('messages.deposit') }}</p>
                        <p class="font-semibold text-slate-800">{{ money($tenant->deposit ?? 0) }}</p>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-3">
                        <p class="text-xs text-slate-400">{{ __('messages.stay_days_label') }}</p>
                        <p class="font-semibold text-amber-700"><span x-text="stayDays"></span> {{ __('messages.days_word') }}</p>
                    </div>
                </div>
            </div>

            <!-- Step: Move-out date -->
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-bold flex-shrink-0">{{ ++$step }}</span>
                    <h3 class="text-base font-semibold text-gray-900">{{ __('messages.when_moving_out') }}</h3>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="date" name="leave_date" x-model="leaveDate" required
                        min="{{ $tenant->move_in_date?->format('Y-m-d') }}"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white appearance-none h-10 text-sm w-full sm:w-56">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-xs font-medium">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('messages.stayed_for') }} <span x-text="stayDays"></span> {{ __('messages.days_word') }}
                    </span>
                </div>
            </div>

            <!-- Step: Final rent -->
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-bold flex-shrink-0">{{ ++$step }}</span>
                    <h3 class="text-base font-semibold text-gray-900">{{ __('messages.how_charge_last_rent') }}</h3>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                        :class="!fullMonth ? 'border-blue-500 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="rent_mode_ui" class="mt-1 h-4 w-4 text-blue-600" :checked="!fullMonth" @change="fullMonth = false">
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-gray-800">{{ __('messages.only_days_stayed') }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5"><span x-text="stayDays"></span> {{ __('messages.days_times_daily_rate') }}</span>
                            <span class="block text-lg font-bold text-blue-600 mt-1.5" x-text="fmt(proRataRent)"></span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                        :class="fullMonth ? 'border-blue-500 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="rent_mode_ui" class="mt-1 h-4 w-4 text-blue-600" :checked="fullMonth" @change="fullMonth = true">
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-gray-800">{{ __('messages.full_month_rent') }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5">{{ __('messages.charge_entire_month') }}</span>
                            <span class="block text-lg font-bold text-blue-600 mt-1.5" x-text="fmt(monthlyRent)"></span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- Step: Unpaid bills -->
            @if($pendingCharges->isNotEmpty())
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center gap-3 mb-1">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-bold flex-shrink-0">{{ ++$step }}</span>
                    <h3 class="text-base font-semibold text-gray-900">{{ __('messages.unpaid_bills') }}</h3>
                </div>
                <p class="text-xs text-gray-400 mb-3 ml-10">{{ __('messages.unpaid_bills_hint') }}</p>

                <div class="border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-100">
                    <label class="flex items-center gap-3 px-4 py-2.5 bg-slate-50 cursor-pointer">
                        <input type="checkbox" class="h-4 w-4 text-blue-600 rounded" :checked="allSelected" @change="toggleAll()">
                        <span class="text-xs font-semibold text-slate-500 uppercase flex-1">{{ __('messages.select_all') }}</span>
                        <span class="text-xs font-medium text-slate-500">
                            <span x-text="selectedCharges.length"></span>/{{ $pendingCharges->count() }} {{ __('messages.bills_selected') }}
                        </span>
                    </label>
                    @foreach($pendingCharges as $charge)
                    <label class="flex items-center gap-3 px-4 py-3 cursor-pointer transition hover:bg-slate-50"
                        :class="selectedCharges.includes('{{ $charge->id }}') && 'bg-blue-50/40'">
                        <input type="checkbox" name="charge_ids[]" value="{{ $charge->id }}" x-model="selectedCharges"
                            class="h-4 w-4 text-blue-600 rounded">
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm text-gray-700 truncate">{{ $charge->description }}</span>
                            <span class="flex items-center gap-2 mt-0.5">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium
                                    {{ $charge->type === 'utilities' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                    {{ ucfirst($charge->type) }}
                                </span>
                                <span class="text-xs text-gray-400">{{ __('messages.due_date') }}: {{ $charge->due_date->format('M d, Y') }}</span>
                            </span>
                        </span>
                        <span class="text-sm font-semibold text-gray-800 flex-shrink-0">{{ money($charge->amount) }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Step: Deposit -->
            @if(($tenant->deposit ?? 0) > 0)
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-bold flex-shrink-0">{{ ++$step }}</span>
                    <h3 class="text-base font-semibold text-gray-900">{{ __('messages.deposit_question') }} <span class="text-slate-400 font-normal">({{ money($tenant->deposit) }})</span></h3>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                        :class="depositAction === 'return_deposit' ? 'border-blue-500 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="deposit_action_ui" class="mt-1 h-4 w-4 text-blue-600" :checked="depositAction === 'return_deposit'" @change="depositAction = 'return_deposit'">
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-gray-800">{{ __('messages.return_deposit_option') }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5">{{ __('messages.return_deposit_hint') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition"
                        :class="depositAction === 'last_payment' ? 'border-green-500 bg-green-50/50' : 'border-slate-200 hover:border-slate-300'">
                        <input type="radio" name="deposit_action_ui" class="mt-1 h-4 w-4 text-green-600" :checked="depositAction === 'last_payment'" @change="depositAction = 'last_payment'">
                        <span class="flex-1">
                            <span class="block text-sm font-semibold text-gray-800">{{ __('messages.deposit_as_last_payment_option') }}</span>
                            <span class="block text-xs text-gray-500 mt-0.5">{{ __('messages.deposit_as_last_payment_hint') }}</span>
                        </span>
                    </label>
                </div>
            </div>
            @endif

            <!-- Step: Extra charges & notes -->
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-bold flex-shrink-0">{{ ++$step }}</span>
                        <h3 class="text-base font-semibold text-gray-900">{{ __('messages.extra_and_notes') }} <span class="text-slate-400 font-normal text-sm">({{ __('messages.optional') }})</span></h3>
                    </div>
                    <button type="button" @click="addExtraCharge()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold hover:bg-blue-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('messages.add_extra_charge') }}
                    </button>
                </div>

                <p class="text-xs text-gray-400 mb-3 ml-10" x-show="extraCharges.length === 0">{{ __('messages.no_extra_charges_yet') }}</p>

                <template x-for="(row, idx) in extraCharges" :key="idx">
                    <div class="flex gap-2 mb-2">
                        <input type="text" required
                            :name="'extra_charges[' + idx + '][description]'"
                            x-model="row.description"
                            placeholder="{{ __('messages.what_is_charge_for') }}"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white">
                        <input type="number" step="{{ currency_is_khr() ? '1' : '0.01' }}" min="{{ currency_is_khr() ? '1' : '0.01' }}" required
                            :name="'extra_charges[' + idx + '][amount]'"
                            x-model.number="row.amount"
                            placeholder="0"
                            class="w-28 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm bg-white">
                        <button type="button" @click="removeExtraCharge(idx)"
                            class="px-2 text-red-500 hover:text-red-700" title="{{ __('messages.remove') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>

                <textarea name="notes" rows="2" placeholder="{{ __('messages.optional_notes_departure') }}"
                    class="w-full mt-2 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">{{ old('notes') }}</textarea>
            </div>

            <!-- Settlement summary -->
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h3 class="text-base font-semibold text-gray-900">{{ __('messages.settlement_summary') }}</h3>
                <p class="text-xs text-slate-400 mb-4">{{ __('messages.updates_live') }}</p>

                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span x-text="fullMonth ? '{{ __('messages.full_month_rent') }}' : '{{ __('messages.pro_rata_rent') }}'"></span>
                        <span class="font-semibold text-gray-800" x-text="fmt(rentCharge)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600" x-show="chargeCount > 0">
                        <span>{{ __('messages.unpaid_bills') }} (<span x-text="selectedCharges.length"></span>)</span>
                        <span class="font-semibold text-gray-800" x-text="fmt(billsTotal)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600" x-show="extraTotal > 0">
                        <span>{{ __('messages.damage_extra_charges') }}</span>
                        <span class="font-semibold text-gray-800" x-text="fmt(extraTotal)"></span>
                    </div>

                    <div class="flex justify-between items-center border-t border-slate-200 pt-2.5">
                        <span class="font-semibold text-gray-800">{{ __('messages.total_due') }}</span>
                        <span class="font-bold text-lg text-gray-900" x-text="fmt(totalDue)"></span>
                    </div>

                    @if(($tenant->deposit ?? 0) > 0)
                    <div class="flex justify-between text-gray-600">
                        <span x-text="depositAction === 'last_payment' ? '{{ __('messages.deposit_as_last_payment_label') }}' : '{{ __('messages.deposit_applied') }}'"></span>
                        <span class="font-semibold text-green-600" x-text="'− ' + fmt(depositAction === 'last_payment' ? deposit : depositApplied)"></span>
                    </div>
                    @endif
                </div>

                <!-- Outcome banner -->
                <div class="mt-4">
                    <template x-if="balanceDue > 0">
                        <div class="rounded-lg bg-red-50 border border-red-200 px-3 py-3 text-center">
                            <p class="text-xs text-red-500 font-medium">{{ __('messages.tenant_still_owes') }}</p>
                            <p class="text-xl font-bold text-red-600" x-text="fmt(balanceDue)"></p>
                        </div>
                    </template>
                    <template x-if="balanceDue <= 0 && refundAmount > 0">
                        <div class="rounded-lg bg-green-50 border border-green-200 px-3 py-3 text-center">
                            <p class="text-xs text-green-600 font-medium">{{ __('messages.refund_to_tenant') }}</p>
                            <p class="text-xl font-bold text-green-700" x-text="fmt(refundAmount)"></p>
                        </div>
                    </template>
                    <template x-if="balanceDue <= 0 && refundAmount <= 0">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 px-3 py-3 text-center">
                            <p class="text-sm font-medium text-slate-500">{{ __('messages.all_settled') }}</p>
                        </div>
                    </template>
                    <template x-if="depositAction === 'last_payment' && deposit > 0">
                        <p class="text-xs text-slate-400 mt-2 text-center">{{ __('messages.deposit_kept_note') }}</p>
                    </template>
                </div>

                <!-- Actions -->
                <div class="mt-5 space-y-2">
                    <button type="button" @click="if ($refs.form.reportValidity()) showModal = true"
                        class="w-full px-5 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition text-sm">{{ __('messages.process_leave_archive') }}</button>
                    <a href="{{ $backUrl }}" class="block w-full px-5 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition text-center text-sm">{{ __('messages.cancel') }}</a>
                </div>
            </div>
    </form>

    <!-- Confirm Modal -->
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">

        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm my-auto p-6 text-center"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">

            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-3">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>

            <h3 class="text-lg font-bold text-gray-900">{{ __('messages.confirm_leave_title') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ $tenant->name }} &middot; {{ __('messages.apt_short') }} {{ $tenant->apartment?->apartment_number ?? 'N/A' }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ __('messages.confirm_leave_warning') }}</p>

            <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 text-sm space-y-1.5 text-left">
                <div class="flex justify-between text-gray-600">
                    <span>{{ __('messages.total_due') }}</span>
                    <span class="font-semibold text-gray-800" x-text="fmt(totalDue)"></span>
                </div>
                <div class="flex justify-between text-gray-600" x-show="balanceDue > 0">
                    <span>{{ __('messages.tenant_still_owes') }}</span>
                    <span class="font-semibold text-red-600" x-text="fmt(balanceDue)"></span>
                </div>
                <div class="flex justify-between text-gray-600" x-show="refundAmount > 0">
                    <span>{{ __('messages.refund_to_tenant') }}</span>
                    <span class="font-semibold text-green-600" x-text="fmt(refundAmount)"></span>
                </div>
            </div>

            <div class="flex gap-3 mt-5">
                <button type="button" @click="showModal = false"
                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition text-sm">{{ __('messages.cancel') }}</button>
                <button type="button" @click="$refs.form.submit()"
                    class="flex-1 px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition text-sm">{{ __('messages.confirm_archive') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<script>
    function leaveForm() {
        return {
            // Amounts are pre-converted to the active display currency (see money_input()).
            sym: '{{ currency_symbol() }}',
            dec: {{ currency_is_khr() ? 0 : 2 }},
            monthlyRent: {{ money_input($rental->rent_amount ?? 0) }},
            deposit: {{ money_input($tenant->deposit ?? 0) }},
            moveInDate: new Date('{{ $tenant->move_in_date?->format("Y-m-d") ?? now()->format("Y-m-d") }}'),

            leaveDate: '{{ old('leave_date', today()->format('Y-m-d')) }}',
            fullMonth: {{ old('charge_full_month') ? 'true' : 'false' }},
            depositAction: '{{ old('deposit_action', 'return_deposit') }}',
            selectedCharges: @json(array_values(old('charge_ids', []))),
            extraCharges: @json(array_values(old('extra_charges', []))),
            showModal: false,

            chargeCount: {{ $pendingCharges->count() }},
            allIds: @json($pendingCharges->pluck('id')),
            chargeAmounts: @json($pendingCharges->mapWithKeys(fn ($c) => [$c->id => (float) money_input($c->amount)])),

            get stayDays() {
                if (!this.leaveDate) return 0;
                const days = Math.ceil((new Date(this.leaveDate) - this.moveInDate) / 86400000) + 1;
                return Math.max(days, 1);
            },
            // Final-month days only — earlier months were billed by the normal
            // monthly rent flow. Mirrors TenantLeaveCalculator::finalMonthDays().
            get finalMonthDays() {
                if (!this.leaveDate) return 0;
                const leave = new Date(this.leaveDate);
                const monthStart = new Date(leave.getFullYear(), leave.getMonth(), 1);
                const anchor = this.moveInDate > monthStart ? this.moveInDate : monthStart;
                const days = Math.floor((leave - anchor) / 86400000) + 1;
                return Math.min(Math.max(days, 1), 30);
            },
            get proRataRent() { return this.finalMonthDays * (this.monthlyRent / 30); },
            get rentCharge() { return this.fullMonth ? this.monthlyRent : this.proRataRent; },
            get billsTotal() { return this.selectedCharges.reduce((sum, id) => sum + (this.chargeAmounts[id] || 0), 0); },
            get extraTotal() { return this.extraCharges.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0); },
            get totalDue() { return this.rentCharge + this.billsTotal + this.extraTotal; },
            get depositApplied() { return Math.min(this.deposit, this.totalDue); },
            get balanceDue() { return Math.max(0, this.totalDue - this.deposit); },
            get refundAmount() { return this.depositAction === 'return_deposit' ? this.deposit - this.depositApplied : 0; },
            get allSelected() { return this.chargeCount > 0 && this.selectedCharges.length === this.chargeCount; },

            toggleAll() { this.selectedCharges = this.allSelected ? [] : [...this.allIds]; },
            addExtraCharge() { this.extraCharges.push({ description: '', amount: '' }); },
            removeExtraCharge(idx) { this.extraCharges.splice(idx, 1); },
            fmt(v) {
                return this.sym + Number(v).toLocaleString('en-US', {
                    minimumFractionDigits: this.dec,
                    maximumFractionDigits: this.dec,
                });
            },
        };
    }
</script>
