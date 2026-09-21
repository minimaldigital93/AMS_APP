{{-- Centralized flash messages. Success pops once and auto-dismisses; errors and
     warnings persist until the user dismisses them so they can't be missed.
     success_sticky = success styling without the auto-dismiss — for messages the
     user must copy from (e.g. a freshly reset password).

     password_reveal is its own block, not a success_sticky string: a freshly
     generated password glued into a sentence ("...reset to Ab12Cd34Ef.") gave
     an admin nothing to select but the whole sentence, period included — paste
     that into the tenant's password field and it can never match. Isolating
     the value in its own <code> + Copy button removes the guesswork. --}}
@if (session('password_reveal'))
    @php($reveal = session('password_reveal'))
    <div
        class="no-print mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800"
        role="alert"
        aria-live="assertive"
        x-data="{ copied: false }">
        <p>{{ __('messages.flash_account_password_reset_label', ['name' => $reveal['name']]) }}</p>
        <div class="mt-2 flex items-center gap-2">
            <code class="select-all rounded border border-green-200 bg-white px-3 py-1.5 font-mono text-base font-semibold tracking-wide text-green-900">{{ $reveal['password'] }}</code>
            <button
                type="button"
                @click="navigator.clipboard.writeText(@js($reveal['password'])); copied = true; setTimeout(() => copied = false, 2000)"
                class="shrink-0 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-500">
                <span x-show="!copied">{{ __('messages.copy_password') }}</span>
                <span x-show="copied" x-cloak>{{ __('messages.copied_password') }}</span>
            </button>
        </div>
    </div>
@endif
@php($flashStyles = [
    'success' => ['classes' => 'border-green-300 bg-green-50 text-green-800', 'autoDismiss' => true],
    'success_sticky' => ['classes' => 'border-green-300 bg-green-50 text-green-800', 'autoDismiss' => false],
    'error' => ['classes' => 'border-red-300 bg-red-50 text-red-800', 'autoDismiss' => false],
    'warning' => ['classes' => 'border-yellow-300 bg-yellow-50 text-yellow-800', 'autoDismiss' => false],
])
@foreach ($flashStyles as $flash => $style)
    @if (session($flash))
        <div
            x-data="{ show: true }"
            @if ($style['autoDismiss'])
            x-init="setTimeout(() => show = false, 4000)"
            @endif
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="no-print mb-4 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $style['classes'] }}"
            role="alert"
            aria-live="{{ $flash === 'success' ? 'polite' : 'assertive' }}">
            <span>{{ session($flash) }}</span>
            <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100 transition" aria-label="{{ __('messages.close') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif
@endforeach
