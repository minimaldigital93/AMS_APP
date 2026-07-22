{{-- Shared delete / sensitive-action confirmation modal.
     Replaces native window.confirm() for delete operations across the app.

     Three ways to use it:
     1. Declaratively on any <form>:
          - any form with @method('DELETE') is intercepted automatically
          - or add  data-confirm="Your message"  to any form
          - optional: data-confirm-title="..."  data-confirm-ok="Delete"
     2. Programmatically (for fetch / JS driven actions):
          if (await window.confirmAction({ message: '...', okLabel: 'Delete' })) { ... }
     3. As a notice popup replacing native window.alert() (single OK button):
          window.amsAlert('Your message');  // optional: amsAlert(msg, { title: '...' })
--}}
<div id="confirm-modal"
     class="hidden fixed inset-0 z-[10000] items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
    <div data-confirm-overlay class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>

    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5
                transform transition-all">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div data-confirm-icon class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="flex-1 pt-0.5">
                    <h3 id="confirm-modal-title" class="text-base font-semibold text-slate-900">
                        {{ __('messages.confirm_delete_title') }}
                    </h3>
                    <p id="confirm-modal-message" class="mt-1 text-sm text-slate-600 whitespace-pre-line">
                        {{ __('messages.confirm_delete_default') }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" data-confirm-cancel
                        class="inline-flex justify-center rounded-lg border border-slate-300 bg-white px-4 py-2
                               text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none
                               focus:ring-2 focus:ring-slate-400">
                    {{ __('messages.cancel') }}
                </button>
                <button type="button" data-confirm-ok
                        class="inline-flex justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold
                               text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2
                               focus:ring-red-500 focus:ring-offset-1">
                    {{ __('messages.confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var DEFAULT_TITLE = @json(__('messages.confirm_delete_title'));
    var DEFAULT_MESSAGE = @json(__('messages.confirm_delete_default'));
    var DEFAULT_OK = @json(__('messages.confirm'));
    var DEFAULT_ALERT_TITLE = @json(__('messages.notice'));
    var DEFAULT_ALERT_OK = @json(__('messages.ok'));

    var pendingForm = null;
    var pendingResolve = null;
    var lastFocused = null;

    function el() { return document.getElementById('confirm-modal'); }

    function open(opts) {
        var modal = el();
        if (!modal) {
            if (opts.alert) { window.alert(opts.message || ''); return Promise.resolve(true); }
            return Promise.resolve(window.confirm(opts.message || DEFAULT_MESSAGE));
        }

        var isAlert = !!opts.alert;
        modal.querySelector('#confirm-modal-title').textContent = opts.title || (isAlert ? DEFAULT_ALERT_TITLE : DEFAULT_TITLE);
        modal.querySelector('#confirm-modal-message').textContent = opts.message || DEFAULT_MESSAGE;

        // Alert mode: single OK button, amber notice styling instead of the
        // red destructive-confirm styling.
        modal.querySelector('[data-confirm-cancel]').classList.toggle('hidden', isAlert);
        var icon = modal.querySelector('[data-confirm-icon]');
        icon.classList.toggle('bg-red-100', !isAlert);
        icon.classList.toggle('bg-amber-100', isAlert);
        icon.querySelector('svg').classList.toggle('text-red-600', !isAlert);
        icon.querySelector('svg').classList.toggle('text-amber-600', isAlert);
        var ok = modal.querySelector('[data-confirm-ok]');
        ok.textContent = opts.okLabel || (isAlert ? DEFAULT_ALERT_OK : DEFAULT_OK);
        ['bg-red-600', 'hover:bg-red-700', 'focus:ring-red-500'].forEach(function (c) { ok.classList.toggle(c, !isAlert); });
        ['bg-slate-800', 'hover:bg-slate-700', 'focus:ring-slate-500'].forEach(function (c) { ok.classList.toggle(c, isAlert); });

        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        // focus the confirm button for keyboard users
        setTimeout(function () { modal.querySelector('[data-confirm-ok]').focus(); }, 0);

        return new Promise(function (resolve) { pendingResolve = resolve; });
    }

    function close(result) {
        var modal = el();
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            try { lastFocused.focus(); } catch (e) {}
        }
        var resolve = pendingResolve;
        pendingResolve = null;
        if (resolve) resolve(!!result);
    }

    // Public API for JS / fetch driven actions.
    window.confirmAction = function (opts) { return open(opts || {}); };

    // Styled replacement for window.alert() — notice popup with a single OK
    // button, consistent with the rest of the AMS dialogs.
    window.amsAlert = function (message, opts) {
        opts = opts || {};
        return open({ alert: true, message: message, title: opts.title, okLabel: opts.okLabel });
    };

    // Capture-phase submit interceptor. Registered during parse so it runs
    // before the layout's spinner handler (which is bound on DOMContentLoaded).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.confirmed === '1') { delete form.dataset.confirmed; return; }

        var hasDataConfirm = form.hasAttribute('data-confirm');
        var methodField = form.querySelector('input[name="_method"]');
        var isDelete = methodField && String(methodField.value).toUpperCase() === 'DELETE';

        if (!hasDataConfirm && !isDelete) return;

        // Stop the native submit AND the spinner handler from firing on a
        // submission the user has not confirmed yet.
        e.preventDefault();
        e.stopImmediatePropagation();

        pendingForm = form;
        open({
            title: form.getAttribute('data-confirm-title') || undefined,
            message: form.getAttribute('data-confirm') || undefined,
            okLabel: form.getAttribute('data-confirm-ok') || undefined
        }).then(function (ok) {
            var f = pendingForm;
            pendingForm = null;
            if (!ok || !f) return;
            f.dataset.confirmed = '1';
            // requestSubmit re-fires submit (skipped via the confirmed flag) so
            // the spinner UX still runs; fall back for older browsers.
            if (typeof f.requestSubmit === 'function') { f.requestSubmit(); }
            else { f.submit(); }
        });
    }, true);

    // Wire up modal controls + dismissal.
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-confirm-ok]')) { close(true); }
        else if (e.target.closest('[data-confirm-cancel]') || e.target.closest('[data-confirm-overlay]')) {
            pendingForm = null;
            close(false);
        }
    });
    document.addEventListener('keydown', function (e) {
        var modal = el();
        if (!modal || modal.classList.contains('hidden')) return;
        if (e.key === 'Escape') {
            pendingForm = null;
            close(false);
            return;
        }
        // Focus trap: keep Tab cycling between the visible modal buttons
        // (alert mode hides Cancel, leaving only OK).
        if (e.key === 'Tab') {
            var focusables = [modal.querySelector('[data-confirm-cancel]'), modal.querySelector('[data-confirm-ok]')]
                .filter(function (btn) { return btn && !btn.classList.contains('hidden'); });
            var idx = focusables.indexOf(document.activeElement);
            e.preventDefault();
            if (idx === -1) { focusables[focusables.length - 1].focus(); return; }
            focusables[(idx + 1) % focusables.length].focus();
        }
    });
})();
</script>
