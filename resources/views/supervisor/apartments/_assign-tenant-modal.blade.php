@php $assignBase = $assignBase ?? url('/supervisor/apartments'); @endphp
<!-- Assign Tenant Modal (shared by apartments index & 3D view) -->
<style>
    /* iOS zooms the whole page when a focused field is <16px — keep inputs at 16px on phones. */
    @media (max-width: 639px) {
        #assignTenantModal input,
        #assignTenantModal select,
        #assignTenantModal textarea { font-size: 16px; }
    }
    /* Momentum scrolling inside the sheet, and stop scroll from chaining to the page
       behind it (the chaining is what makes the sheet feel "stuck" on iOS). */
    #assignTenantModal .modal-scroll {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    /* Capped height for the centered dialog. JS narrows it further while the
       on-screen keyboard is open so the action buttons stay reachable on iPhone. */
    #assignTenantModal .modal-card {
        max-height: 90vh;
        max-height: 90dvh;
    }
</style>
<div id="assignTenantModal" class="hidden fixed inset-0 bg-slate-900/50 sm:backdrop-blur-sm z-[70] flex items-center justify-center px-4 py-4 sm:py-10" data-assign-base="{{ $assignBase }}">
    <div class="modal-card bg-white rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-slate-100 flex justify-between items-center rounded-t-2xl flex-shrink-0">
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
        <div id="tabNavigation" class="px-5 sm:px-6 pt-3 border-b border-slate-100 flex-shrink-0 hidden">
            <div class="flex gap-4">
                <button type="button" id="existingTenantTab" class="tab-button active px-3 py-2 text-sm font-medium text-slate-800 border-b-2 border-slate-800 hover:text-slate-900">
                    Existing Tenant
                </button>
                <button type="button" id="newTenantTab" class="tab-button px-3 py-2 text-sm font-medium text-slate-400 border-b-2 border-transparent hover:text-slate-600">
                    Create New Tenant
                </button>
            </div>
        </div>

        <form id="assignTenantForm" method="POST" enctype="multipart/form-data" class="flex-1 flex flex-col min-h-0"
            data-max-total-bytes="41943040" data-max-total-message="{{ __('messages.attachment_total_too_large') }}">
            @csrf
            <input type="hidden" id="apartmentId" name="apartment_id" value="{{ old('apartment_id') }}">
            <input type="hidden" id="tenantOption" name="tenant_option" value="{{ old('tenant_option', 'existing') }}">

            <div class="modal-scroll flex-1 min-h-0 overflow-y-auto p-5 sm:p-6 space-y-4">
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
                            <input type="date" id="date_of_birth" name="date_of_birth" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition bg-white appearance-none h-10">
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
                            <input type="date" id="move_in_date" name="move_in_date" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition bg-white appearance-none h-10">
                        </div>
                        <div>
                            <label for="deposit" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.deposit') }} <span class="text-red-400">*</span></label>
                            <input type="number" id="deposit" name="deposit" min="0" step="0.01" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <div class="flex gap-3 px-5 sm:px-6 pt-4 pb-5 border-t border-slate-100 flex-shrink-0">
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

    // iOS-safe scroll lock. `body{overflow:hidden}` is unreliable on iOS Safari —
    // the page keeps catching touch scroll and the sheet feels stuck. Pinning the
    // body with position:fixed actually freezes it; we restore the scroll on close.
    let lockedScrollY = 0;
    function lockScroll() {
        lockedScrollY = window.scrollY || window.pageYOffset || 0;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${lockedScrollY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
    }
    function unlockScroll() {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        window.scrollTo(0, lockedScrollY);
    }

    // Pin the dialog to the *visible* viewport (not the taller layout viewport) and
    // cap the card to it whenever the modal is open. On a short iPhone — or while the
    // on-screen keyboard is open — this guarantees the header and footer always fit and
    // the long form scrolls inside instead of pushing the buttons off-screen. Reverts
    // to the CSS-centered dialog on close.
    const modalCard = modal.querySelector('.modal-card');
    function syncModalToViewport() {
        const vv = window.visualViewport;
        if (!vv || !modalCard) return;
        if (modal.classList.contains('hidden')) {
            modal.style.height = '';
            modal.style.top = '';
            modal.style.bottom = '';
            modalCard.style.maxHeight = '';
            return;
        }
        modal.style.height = vv.height + 'px';
        modal.style.top = vv.offsetTop + 'px';
        modal.style.bottom = 'auto';
        modalCard.style.maxHeight = (vv.height - 32) + 'px';
    }
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncModalToViewport);
        window.visualViewport.addEventListener('scroll', syncModalToViewport);
    }

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

    // Shrink large iPhone photos client-side (same path the tenant/expense forms
    // use) so an image never approaches the upload cap. Replaces the input's file
    // with the compressed copy via DataTransfer. PDFs pass through untouched.
    let pendingCompressions = 0;
    async function compressInput(input) {
        const file = input && input.files && input.files[0];
        if (!file || !file.type || !file.type.startsWith('image/')) return;
        if (typeof window.amsCompressImage !== 'function') return;
        pendingCompressions++;
        try {
            const out = await window.amsCompressImage(file, 1920, 0.72);
            if (out && out !== file) {
                const dt = new DataTransfer();
                dt.items.add(out);
                input.files = dt.files;
            }
        } catch (err) {
            // Leave the original file in place — the size check below still guards it.
        } finally {
            pendingCompressions--;
        }
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
        if (el) el.addEventListener('change', async function() {
            await compressInput(el);
            fileTooLarge(el);
        });
    });

    form.addEventListener('submit', function(e) {
        // Only validate the new-tenant fields when creating a new tenant.
        if (tenantOptionInput.value !== 'new') return;

        // A just-picked photo may still be compressing — don't POST the raw
        // (possibly oversized) original; ask the user to submit again in a moment.
        if (pendingCompressions > 0) {
            e.preventDefault();
            alert(@json(__('messages.attachment_optimizing')));
            return;
        }

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
            // Always open scrolled to the first field. Without this the form keeps
            // whatever scroll position it had last time (or from a bounced submit),
            // so it can open near the bottom and feel like it scrolls "backwards".
            form.scrollTop = 0;
            lockScroll();
            syncModalToViewport();
        });
    });

    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.add('hidden');
            unlockScroll();
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
            unlockScroll();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
            unlockScroll();
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
        lockScroll();
        syncModalToViewport();
    })();
    @endif
});
</script>
