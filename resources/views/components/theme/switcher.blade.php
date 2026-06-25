@props([
    'themes',          // Collection<Theme>
    'active',          // active slug (saved)
    'updateUrl',       // route to persist the choice (PUT)
])

{{--
    <x-theme.switcher> — premium theme picker (LIST VIEW) with LIVE PREVIEW.

    • Clicking a row previews the theme instantly across the whole app
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
    class="space-y-4"
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

    {{-- Theme list --}}
    <div class="ams-surface overflow-hidden" style="box-shadow: var(--shadow)">
        @foreach($themes as $theme)
            @php($p = $theme->preview)
            <div
                role="button" tabindex="0"
                @click="setPreview(@js($theme->slug))"
                @keydown.enter="setPreview(@js($theme->slug))"
                @keydown.space.prevent="setPreview(@js($theme->slug))"
                class="relative flex items-center gap-4 p-4 cursor-pointer transition"
                :class="preview === @js($theme->slug) ? 'bg-active' : 'hover:bg-hover'"
                @if(!$loop->first) style="border-top: 1px solid var(--border-color)" @endif
            >
                {{-- Left accent bar shown for the previewed row --}}
                <span x-show="preview === @js($theme->slug)" x-cloak
                      class="absolute left-0 top-2.5 bottom-2.5 w-1 rounded-full"
                      style="background: {{ $theme->tokens['--accent-color'] }}"></span>

                {{-- Thumbnail: miniature dashboard in the theme's own colors --}}
                <div class="flex-shrink-0 w-24 h-16 rounded-xl overflow-hidden p-1.5 flex gap-1.5"
                     style="background: {{ $p['background'] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}">
                    <div class="w-1/3 rounded-md flex flex-col gap-1 p-1"
                         style="background: {{ $p['sidebar'] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}">
                        <span class="h-1 rounded-full" style="background: {{ $p['accent'] }}; width: 80%"></span>
                        <span class="h-1 rounded-full" style="background: {{ $p['accent'] }}; width: 60%"></span>
                    </div>
                    <div class="flex-1 rounded-md flex flex-col justify-between p-1"
                         style="background: {{ $p['card'] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}">
                        <span class="h-1.5 rounded-full" style="background: {{ $p['primary'] }}; width: 70%"></span>
                        <span class="h-2.5 rounded" style="background: {{ $theme->tokens['--accent-color'] }}; width: 45%"></span>
                    </div>
                </div>

                {{-- Meta --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="ams-title text-base">{{ $theme->name }}</h4>
                        <span x-show="applied === @js($theme->slug)" x-cloak
                              class="ams-badge ams-badge-accent">
                            <span class="material-icons" style="font-size:0.9rem">check</span>
                            {{ __('messages.theme_demo_active') }}
                        </span>
                    </div>
                    <p class="ams-muted text-xs mt-0.5 truncate">{{ $theme->description }}</p>

                    {{-- Color swatches --}}
                    <div class="flex items-center gap-1.5 mt-2">
                        @foreach(['background','sidebar','card','primary','accent'] as $swatch)
                            <span class="w-4 h-4 rounded-full"
                                  style="background: {{ $p[$swatch] }}; border: 1px solid {{ $theme->tokens['--border-color'] }}"
                                  title="{{ ucfirst($swatch) }}"></span>
                        @endforeach
                    </div>
                </div>

                {{-- Apply --}}
                <button type="button"
                    @click.stop="apply(@js($theme->slug))"
                    :disabled="saving === @js($theme->slug)"
                    class="ams-btn flex-shrink-0 min-w-[7rem]"
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
