@extends('layouts.admin')

@section('title', __('messages.add_new_floor'))

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.add_new_floor') }}</h1>
        </div>
        <a href="{{ route('admin.floors.index') }}" title="{{ __('messages.back_to_floors') }}" class="inline-flex items-center justify-center text-slate-400 hover:text-slate-600 p-2 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
    </div>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Form -->
    <div class="bg-white rounded-xl border border-slate-100">
        <form method="POST" action="{{ route('admin.floors.store') }}" id="createFloorForm">
            @csrf

            <!-- Floor Information -->
            <div class="p-6 space-y-5">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.floor_information') }}</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.property') }}</label>
                    @if ($activeProperty)
                        <div class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-700">
                            {{ $activeProperty->name }}
                        </div>
                    @else
                        <p class="text-sm text-amber-600">{{ __('messages.no_properties_yet') }}
                            <a href="{{ route('admin.properties.create') }}" class="font-medium underline">{{ __('messages.add_property') }}</a>
                        </p>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.floor_name') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="floor_name" id="floor_name" required
                           value="{{ old('floor_name') }}"
                           placeholder="{{ __('messages.eg_floor_1') }}"
                           class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('floor_name') border-red-300 ring-1 ring-red-200 @enderror">
                    @error('floor_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-100"></div>

            <!-- Apartments Section -->
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.apt_units') }}</h3>
                    <span id="unitCount" class="text-xs font-medium text-slate-400 hidden"></span>
                </div>

                <!-- Add Unit Inputs -->
                <div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.unit_number') }}</label>
                            <input type="text" id="input_unit_number"
                                   placeholder="{{ __('messages.eg_unit') }}"
                                   class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.monthly_rent') }}</label>
                            <input type="number" id="input_monthly_rent" step="0.01" min="0"
                                   placeholder="0.00"
                                   class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <button type="button" id="addUnitBtn"
                                class="inline-flex items-center justify-center shrink-0 bg-slate-100 hover:bg-slate-200 text-slate-600 py-2 px-4 rounded-lg transition" title="{{ __('messages.add_unit') }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                    <p id="unitError" class="text-red-500 text-xs mt-1 hidden"></p>
                </div>

                <!-- Added Units Table -->
                <div id="unitsTable" class="hidden rounded-xl border border-slate-100 overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/80">
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.unit_hash') }}</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.monthly_rent') }}</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.status') }}</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody id="unitsTableBody" class="divide-y divide-slate-50"></tbody>
                    </table>
                </div>
                <div id="noUnitsMsg" class="text-center py-10">
                    <p class="text-slate-400 text-sm">{{ __('messages.no_units_added') }}</p>
                </div>

                <!-- Hidden inputs injected by JS -->
                <div id="hiddenApartmentInputs"></div>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 border-t border-slate-100 flex gap-3">
                <a href="{{ route('admin.floors.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">
                    {{ __('messages.cancel') }}
                </a>
                <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
                    Create Floor
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    let units = [];

    const addUnitBtn      = document.getElementById('addUnitBtn');
    const inputNumber     = document.getElementById('input_unit_number');
    const inputRent       = document.getElementById('input_monthly_rent');
    const unitError       = document.getElementById('unitError');
    const unitCount       = document.getElementById('unitCount');
    const unitsTable      = document.getElementById('unitsTable');
    const noUnitsMsg      = document.getElementById('noUnitsMsg');
    const tableBody       = document.getElementById('unitsTableBody');
    const hiddenContainer = document.getElementById('hiddenApartmentInputs');

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    function renderUnits() {
        tableBody.innerHTML     = '';
        hiddenContainer.innerHTML = '';

        if (units.length > 0) {
            unitsTable.classList.remove('hidden');
            noUnitsMsg.classList.add('hidden');
            unitCount.classList.remove('hidden');
            unitCount.textContent = units.length + ' unit(s) added';
        } else {
            unitsTable.classList.add('hidden');
            noUnitsMsg.classList.remove('hidden');
            unitCount.classList.add('hidden');
        }

        units.forEach(function (unit, index) {
            // Table row
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/50 transition';
            tr.innerHTML =
                '<td class="px-4 py-2.5">' +
                    '<span class="text-sm font-medium text-slate-700">' + escapeHtml(unit.number) + '</span>' +
                '</td>' +
                '<td class="px-4 py-2.5">' +
                    '<span class="text-sm text-slate-600">$' + parseFloat(unit.rent).toFixed(2) + '</span>' +
                '</td>' +
                '<td class="px-4 py-2.5">' +
                    '<span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600">' +
                        '<span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>Available' +
                    '</span>' +
                '</td>' +
                '<td class="px-4 py-2.5 text-right">' +
                    '<button type="button" data-index="' + index + '" class="remove-unit text-red-400 hover:text-red-600 text-xs font-medium transition">{{ __('messages.remove') }}</button>' +
                '</td>';
            tableBody.appendChild(tr);

            // Hidden inputs for form submission
            [
                ['apartment_number', unit.number],
                ['monthly_rent',     unit.rent],
                ['status',           'available'],
            ].forEach(function (pair) {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'apartments[' + index + '][' + pair[0] + ']';
                inp.value = pair[1];
                hiddenContainer.appendChild(inp);
            });
        });
    }

    function addUnit() {
        const number = inputNumber.value.trim();
        const rent   = parseFloat(inputRent.value) || 0;

        if (!number) {
            unitError.textContent = 'Unit number is required.';
            unitError.classList.remove('hidden');
            inputNumber.focus();
            return;
        }

        if (units.some(function (u) { return u.number === number; })) {
            unitError.textContent = 'Unit "' + number + '" has already been added.';
            unitError.classList.remove('hidden');
            inputNumber.focus();
            return;
        }

        unitError.classList.add('hidden');
        units.push({ number: number, rent: rent });
        renderUnits();

        inputNumber.value = '';
        inputRent.value   = '';
        inputNumber.focus();
    }

    addUnitBtn.addEventListener('click', addUnit);

    // Enter key on either input triggers Add Unit
    [inputNumber, inputRent].forEach(function (el) {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addUnit(); }
        });
    });

    // Remove button delegation
    tableBody.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-unit');
        if (!btn) return;
        units.splice(parseInt(btn.dataset.index, 10), 1);
        renderUnits();
    });

    renderUnits();
}());
</script>
@endsection
