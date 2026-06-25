@props([
    'themes',          // Collection<Theme>
    'active',          // active slug (saved)
    'updateUrl',       // route to persist the choice (PUT)
])

{{--
    <x-theme.switcher> — premium theme picker with LIVE PREVIEW.

    • Clicking a card previews the theme instantly across the whole app
      (rewrites <html data-theme>) without a reload.
    • "Apply" persists the choice (PUT, JSON) and mirrors a cookie so the login
      screen keeps the look. The saved theme shows an "Active" indicator.
    • "Reset preview" reverts to the saved theme.
--}}
<div
    x-data="amsThemeSwitcher({
        applied: @js($active),
        url: @js($updateUrl),
    })"
    x-init="init()"
    class="space-y-6"
>
    {{-- Toolbar: active indicator + reset-preview --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="ams-muted text-sm">
            {{ __('messages.theme_active_label') }}
            <span class="ams-text font-semibold" x-text="appliedName()"></span>
        </p>
        <button type="button"
            class="ams-btn ams-btn-ghost text-xs"
            x-show="preview !== applied"
            x-cloak
            @click="revert()">
            <span class="material-icons" style="font-size:1rem">restart_alt</span>
            {{ __('messages.theme_reset_preview') }}
        </button>
    </div>

    {{-- Theme cards grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($themes as $theme)
            @php($p = $theme->preview)
            <div
                role="button" tabindex="0"
                @click="setPreview(@js($theme->slug))"
                @keydown.enter="setPreview(@js($theme->slug))"
                class="ams-card !p-0 overflow-hidden cursor-pointer transition group"
                :class="{
                    'ams-ring-accent': preview === @js($theme->slug),
                    'ams-card-hover': preview !== @js($theme->slug)
                }"
            >
                {{-- Thumbnail: a miniature dashboard rendered in the theme's own colors --}}
                <div class="relative h-32 p-3" style="background: {{ $p['background'] }}">
                    <div class="flex h-full gap-2">
                        {{-- mini sidebar --}}
                        <div class="w-1/4 rounded-lg flex flex-col gap-1.5 p-1.5"
                             style="background: {{ $p['sidebar'] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}">
                            <span class="h-1.5 rounded-full" style="background: {{ $p['accent'] }}; width: 80%"></span>
                            <span class="h-1.5 rounded-full" style="background: {{ $p['accent'] }}; width: 60%"></span>
                            <span class="h-1.5 rounded-full" style="background: {{ $p['primary'] }}; width: 70%"></span>
                        </div>
                        {{-- mini content --}}
                        <div class="flex-1 flex flex-col gap-2">
                            <div class="rounded-lg flex-1 p-2"
                                 style="background: {{ $p['card'] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}">
                                <span class="block h-2 rounded-full mb-1.5" style="background: {{ $p['primary'] }}; width: 50%"></span>
                                <span class="block h-1.5 rounded-full" style="background: {{ $p['accent'] }}; width: 80%"></span>
                            </div>
                            <div class="rounded-lg h-7 flex items-center px-2"
                                 style="background: {{ $p['card'] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}">
                                <span class="h-3 rounded px-3" style="background: {{ $theme->tokens['--accent-color'] }}"></span>
                            </div>
                        </div>
                    </div>
                    {{-- Active checkmark --}}
                    <div x-show="applied === @js($theme->slug)" x-cloak
                         class="absolute top-2 right-2 w-6 h-6 rounded-full flex items-center justify-center shadow-lg"
                         style="background: {{ $theme->tokens['--accent-color'] }}; color: {{ $theme->tokens['--accent-contrast'] }}">
                        <span class="material-icons" style="font-size:1rem">check</span>
                    </div>
                </div>

                {{-- Meta --}}
                <div class="p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h4 class="ams-title text-base">{{ $theme->name }}</h4>
                        <span class="ams-badge" :class="applied === @js($theme->slug) ? 'ams-badge-accent' : ''">
                            {{ $theme->isDark() ? __('messages.theme_mode_dark') : __('messages.theme_mode_light') }}
                        </span>
                    </div>
                    <p class="ams-muted text-xs mt-1 leading-relaxed">{{ $theme->description }}</p>

                    {{-- Primary color preview swatches --}}
                    <div class="flex items-center gap-1.5 mt-3">
                        @foreach(['background','sidebar','card','primary','accent'] as $swatch)
                            <span class="w-5 h-5 rounded-full"
                                  style="background: {{ $p[$swatch] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}"
                                  title="{{ ucfirst($swatch) }}"></span>
                        @endforeach
                    </div>

                    {{-- Apply --}}
                    <button type="button"
                        @click.stop="apply(@js($theme->slug))"
                        :disabled="saving === @js($theme->slug)"
                        class="ams-btn w-full mt-4"
                        :class="applied === @js($theme->slug) ? 'ams-btn-soft' : 'ams-btn-primary'">
                        <template x-if="saving === @js($theme->slug)">
                            <span class="material-icons animate-spin" style="font-size:1.05rem">progress_activity</span>
                        </template>
                        <span x-show="saving !== @js($theme->slug)"
                              x-text="applied === @js($theme->slug) ? @js(__('messages.theme_applied')) : @js(__('messages.theme_apply'))"></span>
                    </button>
                </div>
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
            preview: config.applied,
            url: config.url,
            saving: null,
            toast: '',
            _toastTimer: null,
            names: @js($themes->pluck('name', 'slug')),

            init() {
                // Ensure the page reflects the saved theme on entry.
                document.documentElement.setAttribute('data-theme', this.applied);
            },
            appliedName() { return this.names[this.applied] ?? this.applied; },
            setPreview(slug) {
                this.preview = slug;
                document.documentElement.setAttribute('data-theme', slug);
            },
            revert() { this.setPreview(this.applied); },
            showToast(msg) {
                this.toast = msg;
                clearTimeout(this._toastTimer);
                this._toastTimer = setTimeout(() => (this.toast = ''), 2600);
            },
            async apply(slug) {
                this.saving = slug;
                this.setPreview(slug);
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
                } catch (e) {
                    this.revert();
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
