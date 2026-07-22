@php
    $assignBase = $assignBase ?? url('/'.$panel.'/apartments');
    // Date rules mirror AssignTenantRequest: tenants are 18+ (DOB no later than
    // 18 years ago) and move-in can't be backdated more than a few days. We do
    // NOT constrain the native date picker with min/max — iOS/iPadOS Safari
    // honours those inconsistently, so instead JS pops up a message when an
    // out-of-range date is picked or submitted (see DATE_BOUNDS below).
    $maxBirthDate = now()->subYears(18)->toDateString();
    $minMoveInDate = now()->subDays(3)->toDateString();
@endphp
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
    /* Footer is sticky inside the scrolling form so the action buttons stay
       pinned and reachable. Pad past the iPhone home indicator (safe area). */
    #assignTenantModal .modal-footer {
        padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
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

        <form id="assignTenantForm" method="POST" enctype="multipart/form-data" class="modal-scroll flex-1 min-h-0 overflow-y-auto flex flex-col"
            data-max-total-bytes="41943040" data-max-total-message="{{ __('messages.attachment_total_too_large') }}">
            @csrf
            <input type="hidden" id="apartmentId" name="apartment_id" value="{{ old('apartment_id') }}">
            {{-- Assignment always creates a new tenant now (the Existing-tenant tab was removed). --}}
            <input type="hidden" id="tenantOption" name="tenant_option" value="new">

            <div class="flex-1 p-5 sm:p-6 space-y-4">
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- New Tenant -->
            <div id="newTenantContent" class="tab-content space-y-5">
                <!-- Personal Information -->
                <div class="space-y-3">
                    <h4 class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.personal_information') }}</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label for="name" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.full_name') }} <span class="text-red-400">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" required class="w-full px-3.5 py-2 text-sm border {{ $errors->has('name') ? 'border-red-300' : 'border-slate-200' }} rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
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
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" required class="w-full px-3.5 py-2 text-sm border {{ $errors->has('phone') ? 'border-red-300' : 'border-slate-200' }} rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="col-span-2">
                            <label for="id_card_number" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.id_card_number') }}</label>
                            <input type="text" id="id_card_number" name="id_card_number" value="{{ old('id_card_number') }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="col-span-2">
                            <label for="address" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.address') }}</label>
                            <input type="text" id="address" name="address" value="{{ old('address') }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                        </div>
                        <div class="col-span-2">
                            <label for="date_of_birth" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.date_of_birth') }}</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition bg-white appearance-none h-10">
                        </div>
                        <div class="col-span-2">
                            <label for="attached_photo" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.attached_photo') }}</label>
                            <input type="file" id="attached_photo" name="attached_photo" accept="image/*"
                                data-max-bytes="5242880" data-allowed-ext="jpg,jpeg,png,webp,heic,heif"
                                data-too-large-message="{{ __('messages.photo_too_large') }}"
                                data-bad-type-message="{{ __('messages.invalid_file_type', ['types' => __('messages.file_types_1')]) }}"
                                class="w-full px-3.5 py-2 text-sm border {{ $errors->has('attached_photo') ? 'border-red-300' : 'border-slate-200' }} rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 transition file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-600">
                            <p class="text-[11px] text-slate-400 mt-1">{{ __('messages.file_types_1') }} · {{ __('messages.photo_max_hint', ['max' => '5 MB']) }}</p>
                        </div>
                        <div class="col-span-2">
                            <label for="id_pdf" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.attached_id_pdf') }}</label>
                            <input type="file" id="id_pdf" name="id_pdf" accept=".pdf,image/*"
                                data-max-bytes="10485760" data-allowed-ext="pdf,jpg,jpeg,png,webp,heic,heif"
                                data-too-large-message="{{ __('messages.document_too_large') }}"
                                data-bad-type-message="{{ __('messages.invalid_file_type', ['types' => __('messages.file_types_2')]) }}"
                                class="w-full px-3.5 py-2 text-sm border {{ $errors->has('id_pdf') ? 'border-red-300' : 'border-slate-200' }} rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 transition file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-600">
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
                            <input type="date" id="move_in_date" name="move_in_date" value="{{ old('move_in_date') }}" required class="w-full px-3.5 py-2 text-sm border {{ $errors->has('move_in_date') ? 'border-red-300' : 'border-slate-200' }} rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition bg-white appearance-none h-10">
                        </div>
                        <div>
                            <label for="deposit" class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wide">{{ __('messages.deposit') }} <span class="text-red-400">*</span></label>
                            <input type="number" id="deposit" name="deposit" value="{{ old('deposit') }}" min="0" step="0.01" required class="w-full px-3.5 py-2 text-sm border {{ $errors->has('deposit') ? 'border-red-300' : 'border-slate-200' }} rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition" placeholder="0.00">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.contract_term') }}</label>
                            {{-- Single-select "checkboxes": picking one clears the others (JS below).
                                 Optional — leaving all unchecked keeps the tenancy open-ended. --}}
                            <div class="grid grid-cols-3 gap-2" id="contractTermGroup">
                                @foreach([3, 6, 12] as $term)
                                    <label class="contract-term-option flex items-center justify-center gap-2 px-3 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 cursor-pointer hover:border-slate-300 transition has-[:checked]:border-slate-800 has-[:checked]:bg-slate-800 has-[:checked]:text-white">
                                        <input type="checkbox" name="contract_term_months" value="{{ $term }}"
                                            {{ (string) old('contract_term_months') === (string) $term ? 'checked' : '' }}
                                            class="contract-term-checkbox rounded border-slate-300 text-slate-800 focus:ring-slate-300">
                                        {{ __('messages.term_n_months', ['n' => $term]) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>

            <div class="modal-footer sticky bottom-0 z-10 bg-white flex gap-3 px-5 sm:px-6 pt-4 pb-5 border-t border-slate-100">
                <button type="button" class="close-modal flex-1 text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-4 rounded-lg transition">
                    {{ __('messages.cancel') }}
                </button>
                <button type="submit" id="submitBtn" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition">
                    <span id="submitBtnLabel">{{ __('messages.assign_tenant') }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var MESSAGES = {
        optimizing: @json(__('messages.attachment_optimizing')),
        phoneEnglish: @json(__('messages.phone_must_be_english')),
        uploading: @json(__('messages.uploading')),
        assign: @json(__('messages.assign_tenant')),
        mustBe18: @json(__('messages.tenant_must_be_18')),
        moveInMin: @json(__('messages.move_in_date_min')),
    };

    // The native date picker offers every date (we don't set min/max — iOS honours
    // them inconsistently). Enforce the bounds in JS instead so every device pops
    // up the same message and rejects out-of-range dates identically.
    var DATE_BOUNDS = {
        date_of_birth: { max: @json($maxBirthDate), message: @json(__('messages.tenant_must_be_18')) },
        move_in_date: { min: @json($minMoveInDate), message: @json(__('messages.move_in_date_min')) },
    };

    // Returns an error message if the field's value violates its bound, else ''.
    function dateBoundError(input) {
        if (!input || !input.value) return '';
        var b = DATE_BOUNDS[input.id];
        if (!b) return '';
        if (b.max && input.value > b.max) return b.message;
        if (b.min && input.value < b.min) return b.message;
        return '';
    }

    function notify(message) {
        (window.amsAlert || window.alert)(message);
    }

    function formatFileSize(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    // Shrink large phone photos client-side (same path the tenant/expense forms
    // use) so an image never approaches the upload cap. Replaces the input's file
    // with the compressed copy via DataTransfer. PDFs pass through untouched.
    var pendingCompressions = 0;
    async function compressInput(input) {
        var file = input && input.files && input.files[0];
        if (!file || !file.type || !file.type.startsWith('image/')) return;
        if (typeof window.amsCompressImage !== 'function') return;
        pendingCompressions++;
        try {
            var out = await window.amsCompressImage(file, 1920, 0.72);
            if (out && out !== file) {
                var dt = new DataTransfer();
                dt.items.add(out);
                input.files = dt.files;
            }
        } catch (err) {
            // Leave the original file in place — the size check below still guards it.
        } finally {
            pendingCompressions--;
        }
    }

    // Per-field pre-upload validation: extension whitelist + size cap, both read
    // from the input's data attributes (kept in sync with AssignTenantRequest).
    // A failing file is rejected with a popup and cleared from the input.
    function validateFileInput(input) {
        var file = input && input.files && input.files[0];
        if (!file) return true;

        var allowed = (input.dataset.allowedExt || '').split(',').filter(Boolean);
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (allowed.length && allowed.indexOf(ext) === -1) {
            notify(input.dataset.badTypeMessage || 'This file type is not allowed.');
            input.value = '';
            return false;
        }

        var maxBytes = Number(input.dataset.maxBytes || 0);
        if (maxBytes && file.size > maxBytes) {
            notify((input.dataset.tooLargeMessage || 'This file is too large.')
                .replace(':size', formatFileSize(file.size))
                .replace(':max', formatFileSize(maxBytes)));
            input.value = '';
            return false;
        }

        return true;
    }

    // Capture-phase validator, registered at PARSE time so it runs BEFORE the
    // layout's global submit handler (bound on DOMContentLoaded). Blocking here
    // stops the spinner/disable UX from engaging on a submission that never
    // leaves the page — otherwise the Assign button is left permanently dead.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.id !== 'assignTenantForm') return;

        function block() {
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        var tenantOption = document.getElementById('tenantOption');
        var submitLabel = document.getElementById('submitBtnLabel');

        // Date bounds, enforced for every tenant type via a popup — see
        // DATE_BOUNDS above (no native min/max is set on the inputs).
        var dateFields = [document.getElementById('date_of_birth'), document.getElementById('move_in_date')];
        for (var i = 0; i < dateFields.length; i++) {
            var err = dateBoundError(dateFields[i]);
            if (err) {
                block();
                notify(err);
                if (dateFields[i].focus) dateFields[i].focus();
                return;
            }
        }

        if (tenantOption && tenantOption.value === 'new') {
            // A just-picked photo may still be compressing — don't POST the raw
            // (possibly oversized) original; ask the user to submit again shortly.
            if (pendingCompressions > 0) {
                block();
                notify(MESSAGES.optimizing);
                return;
            }

            if (!validateFileInput(document.getElementById('attached_photo')) ||
                !validateFileInput(document.getElementById('id_pdf'))) {
                block();
                return;
            }

            var phone = document.getElementById('phone');
            var KHMER_RE = /[ក-៿᧠-᧿]/;
            var ALLOWED_PHONE_RE = /^[0-9+\-\s()]+$/;
            if (phone && phone.value !== '' && (KHMER_RE.test(phone.value) || !ALLOWED_PHONE_RE.test(phone.value))) {
                block();
                notify(MESSAGES.phoneEnglish);
                phone.focus();
                return;
            }
        }

        // Submission goes ahead: the layout handler adds the spinner and disables
        // the buttons; we swap the label so the user sees upload progress.
        if (submitLabel) submitLabel.textContent = MESSAGES.uploading;
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('assignTenantModal');
        if (!modal) return;

        // iOS-safe scroll lock. `body{overflow:hidden}` is unreliable on iOS Safari —
        // the page keeps catching touch scroll and the sheet feels stuck. Pinning the
        // body with position:fixed actually freezes it; we restore the scroll on close.
        var lockedScrollY = 0;
        function lockScroll() {
            lockedScrollY = window.scrollY || window.pageYOffset || 0;
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + lockedScrollY + 'px';
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
        var modalCard = modal.querySelector('.modal-card');
        function syncModalToViewport() {
            var vv = window.visualViewport;
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

        var assignBase = modal.dataset.assignBase || @json($assignBase);
        var form = document.getElementById('assignTenantForm');
        var apartmentIdInput = document.getElementById('apartmentId');

        var submitLabel = document.getElementById('submitBtnLabel');

        // Contract term "checkboxes" behave as a single-select group: ticking one
        // clears the others, and a second click on the ticked one clears it (so the
        // term stays optional). Only one contract_term_months value is ever posted.
        var termCheckboxes = document.querySelectorAll('.contract-term-checkbox');
        termCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.checked) {
                    termCheckboxes.forEach(function (other) {
                        if (other !== cb) other.checked = false;
                    });
                }
            });
        });

        // Validate (and compress) files the moment they're picked, so the user
        // hears about an oversized or wrong-type file immediately — not after
        // filling in the whole form.
        ['attached_photo', 'id_pdf'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', async function () {
                await compressInput(el);
                validateFileInput(el);
            });
        });

        // Immediate feedback when a date outside the allowed range is picked
        // (the iOS picker lets it through). Warn and clear the invalid value.
        ['date_of_birth', 'move_in_date'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', function () {
                var err = dateBoundError(el);
                if (err) {
                    notify(err);
                    el.value = '';
                }
            });
        });

        // Fields to wipe when the modal opens fresh. form.reset() is NOT used:
        // it restores the old() values baked into the value="" attributes after
        // a validation bounce, which would leak one apartment's half-filled form
        // into the next apartment's dialog.
        function clearForm() {
            form.querySelectorAll('.tab-content input, .tab-content select').forEach(function (field) {
                if (field.type === 'checkbox' || field.type === 'radio') { field.checked = false; return; }
                field.value = '';
            });
            if (submitLabel) submitLabel.textContent = MESSAGES.assign;
        }

        function openModal() {
            modal.classList.remove('hidden');
            // Always open scrolled to the first field. Without this the form keeps
            // whatever scroll position it had last time (or from a bounced submit),
            // so it can open near the bottom and feel like it scrolls "backwards".
            form.scrollTop = 0;
            lockScroll();
            syncModalToViewport();
        }

        function closeModal() {
            modal.classList.add('hidden');
            unlockScroll();
        }

        document.querySelectorAll('.assign-tenant-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var apartmentId = this.dataset.apartmentId;
                var apartmentNumber = this.dataset.apartmentNumber;
                apartmentIdInput.value = apartmentId;
                form.action = assignBase + '/' + apartmentId + '/assign-tenant';
                document.getElementById('apartmentNumberDisplay').textContent = apartmentNumber;
                clearForm();
                openModal();
            });
        });

        document.querySelectorAll('.close-modal').forEach(function (btn) {
            btn.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        // Re-open the modal (and restore context) when the server bounced the form
        // back with validation errors — otherwise the failure is invisible to the
        // user. The old() values are already baked into the fields server-side.
        @if($errors->any() && old('apartment_id'))
        (function reopenOnError() {
            var apartmentId = @json(old('apartment_id'));
            form.action = assignBase + '/' + apartmentId + '/assign-tenant';
            openModal();
        })();
        @endif
    });
})();
</script>
