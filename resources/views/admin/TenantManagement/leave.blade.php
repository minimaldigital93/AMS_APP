@extends('layouts.admin')

@section('title', 'Process Tenant Leave')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" x-data="leaveForm()">

    <!-- Header -->
    <div>
        <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm mb-3">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Back to Tenants
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Process Tenant Leave</h1>
    </div>

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif

    <!-- Tenant Info Card -->
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
                <p class="text-sm text-gray-500">Apt {{ $tenant->apartment?->apartment_number ?? 'N/A' }} &middot; {{ $tenant->phone ?? '' }}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-xs text-slate-400">Move In</p>
                <p class="font-semibold text-slate-800">{{ $tenant->move_in_date?->format('M d, Y') ?? 'N/A' }}</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-xs text-slate-400">Monthly Rent</p>
                <p class="font-semibold text-slate-800">${{ number_format($rental->rent_amount ?? 0, 2) }}</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
                <p class="text-xs text-slate-400">Deposit</p>
                <p class="font-semibold text-slate-800">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
            </div>
            <div class="bg-amber-50 rounded-lg p-3">
                <p class="text-xs text-slate-400">Stay Days</p>
                <p class="font-semibold text-amber-700" x-text="stayDays + ' days'">0</p>
            </div>
        </div>
    </div>

    <!-- Leave Form -->
    <form id="leaveForm" action="{{ route('admin.tenants.processLeave', $tenant->id) }}" method="POST">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h3 class="text-base font-semibold text-gray-900">Leave Details</h3>

            <!-- Leave Date -->
            <div>
                <label for="moveOutDate" class="block text-sm font-medium text-gray-700 mb-1">Move Out Date *</label>
                <input type="date" name="leave_date" id="moveOutDate" value="{{ old('leave_date', today()->format('Y-m-d')) }}" required
                    @change="updateCalculations()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white appearance-none h-10 text-sm">
            </div>

            <!-- Rent Charge Mode — Toggle Switch -->
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div>
                    <p class="text-sm font-medium text-gray-700">Charge full month rent</p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="fullMonth ? 'Charging full monthly rent' : 'Charging by actual days stayed'"></p>
                </div>
                <button type="button" @click="fullMonth = !fullMonth; updateCalculations()"
                    :class="fullMonth ? 'bg-blue-600' : 'bg-slate-300'"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <span :class="fullMonth ? 'translate-x-5' : 'translate-x-0'"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
                <!-- Hidden input to carry the value on form submit -->
                <input type="hidden" name="charge_full_month" :value="fullMonth ? '1' : '0'">
            </div>

            <!-- Outstanding Charges -->
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Outstanding Charges</p>
                @if($pendingCharges->isEmpty())
                    <p class="text-xs text-gray-400 italic">No outstanding utility or other charges recorded.</p>
                @else
                    <div class="border border-slate-200 rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                                <tr>
                                    <th class="px-3 py-2 text-left w-8">
                                        <input type="checkbox" id="selectAllCharges" class="w-3.5 h-3.5 text-blue-600 rounded">
                                    </th>
                                    <th class="px-3 py-2 text-left">Description</th>
                                    <th class="px-3 py-2 text-left">Type</th>
                                    <th class="px-3 py-2 text-left">Due Date</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($pendingCharges as $charge)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="charge_ids[]" value="{{ $charge->id }}"
                                            data-amount="{{ $charge->amount }}"
                                            class="charge-checkbox w-3.5 h-3.5 text-blue-600 rounded"
                                            {{ in_array($charge->id, old('charge_ids', [])) ? 'checked' : '' }}>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $charge->description }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            {{ $charge->type === 'utilities' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' }}">
                                            {{ ucfirst($charge->type) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-500">{{ $charge->due_date->format('M d, Y') }}</td>
                                    <td class="px-3 py-2 text-right font-semibold text-gray-800">${{ number_format($charge->amount, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Extra Charges (optional) -->
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Extra Charges <span class="text-xs font-normal text-gray-400">(optional — for damage or unpaid items)</span></p>
                <template x-for="(row, idx) in extraCharges" :key="idx">
                    <div class="flex gap-2 mb-2">
                        <input type="text"
                            :name="'extra_charges[' + idx + '][description]'"
                            x-model="row.description"
                            placeholder="What is the charge for?"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                        <input type="number" step="0.01" min="0"
                            :name="'extra_charges[' + idx + '][amount]'"
                            x-model.number="row.amount"
                            @input="updateCalculations()"
                            placeholder="0.00"
                            class="w-28 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                        <button type="button" @click="removeExtraCharge(idx)"
                            class="px-2 text-red-500 hover:text-red-700" title="Remove">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </template>
                <button type="button" @click="addExtraCharge()"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add extra charge
                </button>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" placeholder="Optional notes about the departure"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 mt-4">
            <button type="button" @click="showModal = true"
                class="flex-1 px-5 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition text-sm">
                Process Leave & Archive
            </button>
            <a href="{{ route('admin.tenants.index') }}" class="flex-1 px-5 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition text-center text-sm">
                Cancel
            </a>
        </div>
    </form>

    <!-- Settlement Summary Modal -->
    <div x-show="showModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">

        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>

        <!-- Modal Panel -->
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Settlement Summary</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Review before confirming tenant leave</p>
                </div>
                <button type="button" @click="showModal = false" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-5 space-y-3 text-sm">

                <!-- Tenant & Apartment -->
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="h-9 w-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-bold text-blue-600">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $tenant->name }}</p>
                        <p class="text-xs text-slate-400">Apt {{ $tenant->apartment?->apartment_number ?? 'N/A' }} &middot; <span x-text="stayDays"></span> days stay</p>
                    </div>
                </div>

                <!-- Line items -->
                <div class="space-y-2">
                    <div class="flex justify-between text-gray-600">
                        <span x-text="fullMonth ? 'Full Month Rent' : 'Pro-rata Rent'"></span>
                        <span class="font-semibold text-gray-800" x-text="'$' + proRataRent.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Outstanding Charges</span>
                        <span class="font-semibold text-gray-800" x-text="'$' + outstandingCharges.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600" x-show="extraTotal > 0">
                        <span>Damage / Extra Charges</span>
                        <span class="font-semibold text-gray-800" x-text="'$' + extraTotal.toFixed(2)"></span>
                    </div>
                </div>

                <!-- Total -->
                <div class="flex justify-between items-center bg-slate-50 rounded-lg px-3 py-2.5 border border-slate-200">
                    <span class="font-semibold text-gray-800">Total Due</span>
                    <span class="font-bold text-xl text-gray-900" x-text="'$' + totalDue.toFixed(2)"></span>
                </div>

                <!-- Deposit / Balance / Refund -->
                <div class="space-y-2 pt-1">
                    <div class="flex justify-between text-gray-600">
                        <span>Deposit Applied</span>
                        <span class="font-semibold text-green-600" x-text="'$' + depositApplied.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Balance Due from Tenant</span>
                        <span class="font-semibold" :class="balanceDue > 0 ? 'text-red-600' : 'text-slate-400'" x-text="'$' + balanceDue.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Refund to Tenant</span>
                        <span class="font-semibold" :class="refundAmount > 0 ? 'text-green-600' : 'text-slate-400'" x-text="'$' + refundAmount.toFixed(2)"></span>
                    </div>
                </div>

                <!-- Warning if balance due -->
                <template x-if="balanceDue > 0">
                    <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5 text-xs text-amber-700">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <span>Tenant owes <strong x-text="'$' + balanceDue.toFixed(2)"></strong> after deposit is applied.</span>
                    </div>
                </template>
                <template x-if="refundAmount > 0">
                    <div class="flex items-start gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2.5 text-xs text-green-700">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Refund <strong x-text="'$' + refundAmount.toFixed(2)"></strong> to tenant from deposit (recorded as expense).</span>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="flex gap-3 px-6 pb-6">
                <button type="button" @click="showModal = false"
                    class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition text-sm">
                    Cancel
                </button>
                <button type="button" @click="document.getElementById('leaveForm').submit()"
                    class="flex-1 px-4 py-2.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition text-sm">
                    Confirm & Archive
                </button>
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
            monthlyRent: {{ $rental->rent_amount ?? 0 }},
            deposit: {{ $tenant->deposit ?? 0 }},
            moveInDate: new Date('{{ $tenant->move_in_date?->format("Y-m-d") ?? now()->format("Y-m-d") }}'),

            fullMonth: {{ old('charge_full_month') ? 'true' : 'false' }},
            extraCharges: @json(old('extra_charges', [])),
            showModal: false,

            // Calculated values (reactive, shown in modal)
            stayDays: 0,
            proRataRent: 0,
            outstandingCharges: 0,
            extraTotal: 0,
            totalDue: 0,
            depositApplied: 0,
            balanceDue: 0,
            refundAmount: 0,

            init() {
                this.$nextTick(() => {
                    this.bindChargeCheckboxes();
                    this.updateCalculations();
                });
            },

            addExtraCharge() {
                this.extraCharges.push({ description: '', amount: 0 });
                this.updateCalculations();
            },

            removeExtraCharge(idx) {
                this.extraCharges.splice(idx, 1);
                this.updateCalculations();
            },

            updateCalculations() {
                const leaveDateEl = document.getElementById('moveOutDate');
                if (!leaveDateEl || !leaveDateEl.value) return;

                const leaveDate = new Date(leaveDateEl.value);
                this.stayDays = Math.ceil((leaveDate - this.moveInDate) / (1000 * 60 * 60 * 24)) + 1;
                this.proRataRent = this.fullMonth ? this.monthlyRent : this.stayDays * (this.monthlyRent / 30);

                this.outstandingCharges = 0;
                document.querySelectorAll('.charge-checkbox:checked').forEach(cb => {
                    this.outstandingCharges += parseFloat(cb.dataset.amount) || 0;
                });

                this.extraTotal = this.extraCharges.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);

                this.totalDue = this.proRataRent + this.outstandingCharges + this.extraTotal;
                this.depositApplied = Math.min(this.deposit, this.totalDue);
                this.balanceDue = Math.max(0, this.totalDue - this.depositApplied);
                this.refundAmount = this.deposit - this.depositApplied;
            },

            bindChargeCheckboxes() {
                document.querySelectorAll('.charge-checkbox').forEach(cb => {
                    cb.addEventListener('change', () => {
                        const all = document.querySelectorAll('.charge-checkbox');
                        const checked = document.querySelectorAll('.charge-checkbox:checked');
                        const selectAll = document.getElementById('selectAllCharges');
                        if (selectAll) {
                            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
                            selectAll.checked = checked.length === all.length;
                        }
                        this.updateCalculations();
                    });
                });

                const selectAllEl = document.getElementById('selectAllCharges');
                if (selectAllEl) {
                    selectAllEl.addEventListener('change', () => {
                        document.querySelectorAll('.charge-checkbox').forEach(cb => {
                            cb.checked = selectAllEl.checked;
                        });
                        this.updateCalculations();
                    });
                }
            },
        };
    }
</script>
@endsection
