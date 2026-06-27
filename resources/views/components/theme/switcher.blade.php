@props([
    'themes',          // Collection<Theme>
    'active',          // active slug (saved)
    'updateUrl',       // route to persist the choice (PUT)
])

{{--
    <x-theme.switcher> — theme picker rendered as a LIST.

    • Each theme is a row showing its name, description and colour swatches.
    • "Apply" persists the choice (PUT, JSON) and mirrors a cookie so the login
      screen keeps the look; the saved theme shows an "Active" indicator.
    • No live preview — the new look takes effect after Apply (page reloads).
--}}
<div
    x-data="amsThemeSwitcher({
        applied: @js($active),
        url: @js($updateUrl),
    })"
    class="space-y-5"
>
    {{-- Section header --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h3 class="ams-title text-lg">{{ __('messages.theme_choose') }}</h3>
            <p class="ams-muted text-sm mt-0.5">{{ __('messages.theme_choose_hint') }}</p>
        </div>
        <p class="ams-muted text-sm">
            {{ __('messages.theme_active_label') }}
            <span class="ams-text font-semibold" x-text="appliedName()"></span>
        </p>
    </div>

    {{-- Theme list. Each row is a theme; "Apply" saves it to the account. --}}
    <div class="ams-theme-list">
        @foreach($themes as $theme)
            @php
                $p = $theme->preview;
                $t = $theme->tokens;
                $accent = $t['--accent-color'] ?? '#111827';
                $border = $t['--border-color'] ?? 'rgba(0,0,0,0.08)';
            @endphp
            <div
                class="ams-theme-row"
                :class="applied === @js($theme->slug) ? 'is-active' : ''"
            >
                {{-- Swatches --}}
                <div class="ams-theme-row__swatches flex-shrink-0">
                    @foreach(['background','sidebar','card','accent'] as $swatch)
                        <span class="w-4 h-4 rounded-full"
                              style="background: {{ $swatch === 'accent' ? $accent : ($p[$swatch] ?? '#fff') }}; border: 1px solid {{ $border }}"></span>
                    @endforeach
                </div>

                {{-- Meta --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="ams-title text-base truncate">{{ $theme->name }}</h4>
                        <span x-show="applied === @js($theme->slug)" x-cloak
                              class="ams-badge ams-badge-accent flex-shrink-0">
                            {{ __('messages.theme_demo_active') }}
                        </span>
                    </div>
                    <p class="ams-muted text-xs mt-0.5 line-clamp-1">{{ $theme->description }}</p>
                </div>

                {{-- Apply --}}
                <button type="button"
                    @click="apply(@js($theme->slug))"
                    :disabled="saving === @js($theme->slug)"
                    class="ams-btn flex-shrink-0"
                    :class="applied === @js($theme->slug) ? 'ams-btn-soft' : 'ams-btn-primary'">
                    <template x-if="saving === @js($theme->slug)">
                        <span class="material-icons animate-spin" style="font-size:1.05rem">progress_activity</span>
                    </template>
                    <span x-show="saving !== @js($theme->slug)"
                          x-text="applied === @js($theme->slug) ? @js(__('messages.theme_applied')) : @js(__('messages.theme_apply'))"></span>
                </button>
            </div>
        @endforeach
    </div>

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[80] ams-surface px-4 py-2.5 shadow-token-lg flex items-center gap-2">
        <span class="material-icons text-state-success" style="font-size:1.1rem">check_circle</span>
        <span class="ams-text text-sm" x-text="toast"></span>
    </div>
</div>

@once
@push('scripts')
<script>
    function amsThemeSwitcher(config) {
        return {
            applied: config.applied,
            url: config.url,
            saving: null,
            toast: '',
            _toastTimer: null,
            names: @js($themes->pluck('name', 'slug')),

            appliedName() { return this.names[this.applied] ?? this.applied; },
            showToast(msg) {
                this.toast = msg;
                clearTimeout(this._toastTimer);
                this._toastTimer = setTimeout(() => (this.toast = ''), 2600);
            },
            async apply(slug) {
                if (slug === this.applied) return;
                this.saving = slug;
                try {
                    const res = await fetch(this.url, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ theme: slug }),
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const data = await res.json();
                    this.applied = data.theme ?? slug;
                    this.showToast(@js(__('messages.theme_saved')));
                    // Reload so the saved theme is applied across the whole app.
                    setTimeout(() => window.location.reload(), 600);
                } catch (e) {
                    this.showToast(@js(__('messages.theme_save_failed')));
                } finally {
                    this.saving = null;
                }
            },
        };
    }
</script>
@endpush
@endonce
