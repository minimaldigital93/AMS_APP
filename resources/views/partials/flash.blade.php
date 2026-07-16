{{-- Centralized flash messages. Success pops once and auto-dismisses; errors and
     warnings persist until the user dismisses them so they can't be missed.
     success_sticky = success styling without the auto-dismiss — for messages the
     user must copy from (e.g. a freshly reset password). --}}
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
