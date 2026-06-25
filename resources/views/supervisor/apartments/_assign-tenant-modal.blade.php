@php $assignBase = $assignBase ?? url('/supervisor/apartments'); @endphp
<!-- Assign Tenant Modal (shared by apartments index & 3D view) -->
<div id="assignTenantModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4" data-assign-base="{{ $assignBase }}">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center rounded-t-2xl flex-shrink-0">
            <div>
                <h3 id="modalTitle" class="text-base font-semibold text-slate-800">{{ __('messages.assign_tenant_to') }} <span id="apartmentNumberDisplay"></span></h3>
                <p class="text-slate-400 text-xs mt-0.5">{{ __('messages.fill_tenant_details') }}</p>
            </div>
            <button type="button" class="close-modal text-slate-300 hover:text-slate-500 p-1 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Tab Navigation -->
        <div id="tabNavigation" class="px-6 pt-3 border-b border-slate-100 flex-shrink-0 hidden">
            <div class="flex gap-4">
                <button type="button" id="existingTenantTab" class="tab-button active px-3 py-2 text-sm font-medium text-slate-800 border-b-2 border-slate-800 hover:text-slate-900">
                    Existing Tenant
                </button>
                <button type="button" id="newTenantTab" class="tab-button px-3 py-2 text-sm font-medium text-slate-400 border-b-2 border-transparent hover:text-slate-600">
                    Create New Tenant
                </button>
            </div>
        </div>

        <form id="assignTenantForm" method="POST" enctype="multipart/form-data" class="flex-1 flex flex-col min-h-0">
            @csrf
            <input type="hidden" id="apartmentId" name="apartment_id" value="{{ old('apartment_id') }}">
            <input type="hidden" id="tenantOption" name="tenant_option" value="{{ old('tenant_option', 'existing') }}">

            <div class="flex-1 overflow-y-auto p-6 space-y-4">
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Existing Tenant Tab -->
            <div id="existingTenantContent" class="tab-content space-y-4 hidden">
                <div>
                    <label for="tenant_id" class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.select_tenant') }}</label>
                    <select id="tenant_id" name="tenant_id" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        <option value="">-- Choose an unassigned tenant --</option>
                        @foreach(($availableTenants ?? collect()) as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->phone }})</option>
                        @endforeach
                    </select>
                    @if(($availableTenants ?? collect())->isEmpty())
                        <p class="text-xs text-amber-600 mt-1.5">{{ __('messages.no_unassigned_tenants') }}</p>
                    @endif
                </div>
            </div>

            <!-- New Tenant Tab -->
            <div id="newTenantContent" class="tab-content space-y-5">
                <!-- Personal Information -->
                <div class="space-y-3">
                    <h4 class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.personal_information') }}</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label for="name" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.full_name') }} <span class="text-red-400">*</span></label>
                            <input type="text" id="name" name="name" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div>
                            <label for="gender" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.gender') }}</label>
                            <select id="gender" name="gender" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition h-10">
                                <option value="">{{ __('messages.select_gender') }}</option>
                                <option value="male" {{ old('gender') === 'male' ? 'selected' : '' }}>{{ __('messages.male') }}</option>
                                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>{{ __('messages.female') }}</option>
                                <option value="other" {{ old('gender') === 'other' ? 'selected' : '' }}>{{ __('messages.other') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.phone') }} <span class="text-red-400">*</span></label>
                            <input type="tel" id="phone" name="phone" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="col-span-2">
                            <label for="id_card_number" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.id_card_number') }}</label>
                            <input type="text" id="id_card_number" name="id_card_number" value="{{ old('id_card_number') }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="col-span-2">
                            <label for="address" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.address') }}</label>
                            <input type="text" id="address" name="address" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="col-span-2">
                            <label for="date_of_birth" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.date_of_birth') }}</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" max="{{ now()->subYears(18)->toDateString() }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition bg-white appearance-none h-10">
                        </div>
                        <div class="col-span-2">
                            <label for="attached_photo" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.attached_photo') }}</label>
                            <input type="file" id="attached_photo" name="attached_photo" accept="image/*" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 transition file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-600">
                            <p class="text-[11px] text-slate-400 mt-1">{{ __('messages.file_types_1') }} · {{ __('messages.photo_max_hint', ['max' => '10 MB']) }}</p>
                        </div>
                        <div class="col-span-2">
                            <label for="id_pdf" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.attached_id_pdf') }}</label>
                            <input type="file" id="id_pdf" name="id_pdf" accept=".pdf,image/*" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 transition file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-600">
                            <p class="text-[11px] text-slate-400 mt-1">{{ __('messages.file_types_2') }} · {{ __('messages.photo_max_hint', ['max' => '10 MB']) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Rent Information -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h4 class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.rent_information') }}</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="move_in_date" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.move_in_date') }} <span class="text-red-400">*</span></label>
                            <input type="date" id="move_in_date" name="move_in_date" required min="{{ now()->subDays(3)->toDateString() }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition bg-white appearance-none h-10">
                        </div>
                        <div>
                            <label for="deposit" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.deposit') }} <span class="text-red-400">*</span></label>
                            <input type="number" id="deposit" name="deposit" min="0" step="0.01" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <div class="flex gap-3 p-6 pt-4 border-t border-slate-100 flex-shrink-0">
                <button type="button" class="close-modal flex-1 text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-4 rounded-lg transition">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition">
                    Assign Tenant
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('assignTenantModal');
    if (!modal) return;

    const assignBase = modal.dataset.assignBase || '/supervisor/apartments';
    const form = document.getElementById('assignTenantForm');
    const apartmentIdInput = document.getElementById('apartmentId');
    const tenantOptionInput = document.getElementById('tenantOption');
    const tabNavigation = document.getElementById('tabNavigation');

    const existingTenantTab = document.getElementById('existingTenantTab');
    const newTenantTab = document.getElementById('newTenantTab');
    const existingTenantContent = document.getElementById('existingTenantContent');
    const newTenantContent = document.getElementById('newTenantContent');
    const tabButtons = document.querySelectorAll('.tab-button');

    function switchToExistingTab() {
        tenantOptionInput.value = 'existing';
        existingTenantContent.classList.remove('hidden');
        newTenantContent.classList.add('hidden');
        tabButtons.forEach(btn => { btn.classList.remove('text-slate-800', 'border-slate-800'); btn.classList.add('text-slate-400', 'border-transparent'); });
        existingTenantTab.classList.add('text-slate-800', 'border-slate-800');
        existingTenantTab.classList.remove('text-slate-400', 'border-transparent');
    }

    function switchToNewTab() {
        tenantOptionInput.value = 'new';
        existingTenantContent.classList.add('hidden');
        newTenantContent.classList.remove('hidden');
        tabButtons.forEach(btn => { btn.classList.remove('text-slate-800', 'border-slate-800'); btn.classList.add('text-slate-400', 'border-transparent'); });
        newTenantTab.classList.add('text-slate-800', 'border-slate-800');
        newTenantTab.classList.remove('text-slate-400', 'border-transparent');
    }

    existingTenantTab.addEventListener('click', switchToExistingTab);
    newTenantTab.addEventListener('click', switchToNewTab);

    // Client-side validation for the "Create New Tenant" flow.
    const KHMER_RE = /[ក-៿᧠-᧿]/;
    const ALLOWED_PHONE_RE = /^[0-9+\-\s()]+$/;

    // Reject oversized attachments before upload (server cap: max:10240 KB = 10 MB).
    const MAX_FILE_MB = 10;
    const MAX_FILE_BYTES = MAX_FILE_MB * 1024 * 1024;
    function formatFileSize(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }
    function fileTooLarge(input) {
        const file = input && input.files && input.files[0];
        if (file && file.size > MAX_FILE_BYTES) {
            alert(@json(__('messages.photo_too_large'))
                .replace(':size', formatFileSize(file.size))
                .replace(':max', MAX_FILE_MB + ' MB'));
            input.value = '';
            return true;
        }
        return false;
    }
    ['attached_photo', 'id_pdf'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', function() { fileTooLarge(el); });
    });

    form.addEventListener('submit', function(e) {
        // Only validate the new-tenant fields when creating a new tenant.
        if (tenantOptionInput.value !== 'new') return;

        // Block submission if an attachment is still over the size limit.
        if (fileTooLarge(document.getElementById('attached_photo')) ||
            fileTooLarge(document.getElementById('id_pdf'))) {
            e.preventDefault();
            return;
        }

        const phone = document.getElementById('phone');
        if (phone.value !== '' && (KHMER_RE.test(phone.value) || !ALLOWED_PHONE_RE.test(phone.value))) {
            e.preventDefault();
            alert(@json(__('messages.phone_must_be_english')));
            phone.focus();
            return;
        }

        const dobInput = document.getElementById('date_of_birth');
        if (dobInput.value && dobInput.max && dobInput.value > dobInput.max) {
            e.preventDefault();
            alert(@json(__('messages.tenant_must_be_18')));
            dobInput.focus();
            return;
        }

        const moveIn = document.getElementById('move_in_date');
        if (moveIn.value && moveIn.min && moveIn.value < moveIn.min) {
            e.preventDefault();
            alert(@json(__('messages.move_in_date_min')));
            moveIn.focus();
            return;
        }
    });

    document.querySelectorAll('.assign-tenant-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const apartmentId = this.dataset.apartmentId;
            const apartmentNumber = this.dataset.apartmentNumber;
            apartmentIdInput.value = apartmentId;
            form.action = `${assignBase}/${apartmentId}/assign-tenant`;
            document.getElementById('modalTitle').innerHTML = 'Assign Tenant to <span id="apartmentNumberDisplay">' + apartmentNumber + '</span>';
            document.getElementById('submitBtn').textContent = 'Assign Tenant';
            tabNavigation.classList.add('hidden');
            existingTenantContent.classList.add('hidden');
            newTenantContent.classList.remove('hidden');
            tenantOptionInput.value = 'new';
            form.reset();
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    });

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });

    // Re-open the modal (and restore context) when the server bounced the form
    // back with validation errors — otherwise the failure is invisible to the user.
    @if($errors->any() && old('apartment_id'))
    (function reopenOnError() {
        const apartmentId = @json(old('apartment_id'));
        form.action = `${assignBase}/${apartmentId}/assign-tenant`;
        if (@json(old('tenant_option')) === 'existing') {
            switchToExistingTab();
        } else {
            switchToNewTab();
        }
        tabNavigation.classList.remove('hidden');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    })();
    @endif
});
</script>
