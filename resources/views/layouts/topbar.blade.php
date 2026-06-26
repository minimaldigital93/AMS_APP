<div id="topbarNav" class="relative z-[60] w-full shadow-sm flex items-center justify-between px-3 sm:px-6 lg:px-8 py-2 sm:py-3 transition-transform duration-300 -translate-y-0" style="opacity:1;">
    <!-- Left: Hamburger (mobile only) + Logo/Brand -->
    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0">
        @unless($useBottomNav ?? false)
        <button
            type="button"
            @click="mobileOpen = !mobileOpen"
            class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-token hover:bg-hover focus:outline-none focus:ring-2 focus:ring-token"
            :aria-expanded="mobileOpen.toString()"
            aria-label="Toggle navigation">
            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        @endunless
        <x-application-logo class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 fill-current text-token flex-shrink-0" />
        <span class="font-bold text-base sm:text-lg lg:text-xl text-token truncate">{{ $topbarBrand ?? __('messages.ams') }}</span>
    </div>

    <!-- Right Actions -->
    <div class="flex items-center space-x-2 sm:space-x-4">
        {{-- Global active-property context selector (before the user / fiscal period) --}}
        @include('partials.property-selector')

        @auth
        @php
            $notifications = $topbarNotifications ?? collect();
            $notifCount = $notifications->count();
            $notifKey = 'notif_seen_' . auth()->id();
            $colorMap = [
                'amber'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600',   'ring' => 'ring-amber-100'],
                'red'     => ['bg' => 'bg-red-50',     'text' => 'text-red-600',     'ring' => 'ring-red-100'],
                'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'ring' => 'ring-emerald-100'],
                'indigo'  => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-600',  'ring' => 'ring-indigo-100'],
                'blue'    => ['bg' => 'bg-blue-50',    'text' => 'text-blue-600',    'ring' => 'ring-blue-100'],
                'slate'   => ['bg' => 'bg-slate-50',   'text' => 'text-slate-600',   'ring' => 'ring-slate-100'],
            ];
            $groupLabels = [
                'billing'   => __('messages.notif_group_billing'),
                'rent'      => __('messages.notif_group_rent'),
                'utilities' => __('messages.notif_group_utilities'),
                'expenses'  => __('messages.notif_group_expenses'),
                'tenants'   => __('messages.notif_group_tenants'),
            ];
            $groupedNotifications = $notifications
                ->groupBy(fn ($n) => $n['group'] ?? 'tenants')
                ->sortBy(fn ($g, $key) => array_search($key, array_keys($groupLabels)));
        @endphp

        <!-- Notification bell -->
        <div
            class="relative"
            x-data="{
                open: false,
                seenKey: @js($notifKey),
                count: {{ $notifCount }},
                seen: 0,
                init() {
                    try {
                        this.seen = parseInt(localStorage.getItem(this.seenKey) || '0', 10) || 0;
                    } catch (e) { this.seen = 0; }
                },
                unread() {
                    return Math.max(this.count - this.seen, 0);
                },
                markSeen() {
                    this.seen = this.count;
                    try { localStorage.setItem(this.seenKey, String(this.count)); } catch (e) {}
                },
                toggle() {
                    this.open = !this.open;
                    if (this.open) this.markSeen();
                }
            }"
            @keydown.escape.window="open = false"
            @click.outside="open = false"
        >
            <button
                type="button"
                @click="toggle()"
                class="relative inline-flex items-center justify-center p-2 rounded-full text-token hover:bg-hover focus:outline-none focus:ring-2 focus:ring-token transition"
                aria-label="{{ __('messages.notifications') }}">
                <span class="material-icons text-xl sm:text-2xl">notifications</span>
                <template x-if="unread() > 0">
                    <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 inline-flex items-center justify-center text-[10px] font-bold leading-none text-white bg-red-500 rounded-full ring-2 ring-slate-900">
                        <span x-text="unread() > 9 ? '9+' : unread()"></span>
                    </span>
                </template>
            </button>

            <!-- Dropdown panel -->
            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute right-0 mt-2 w-[88vw] max-w-sm sm:w-96 bg-white rounded-xl shadow-2xl ring-1 ring-black/5 z-50 overflow-hidden"
                role="menu">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-2">
                        <span class="material-icons text-slate-700">notifications_active</span>
                        <h3 class="font-semibold text-slate-800 text-sm">{{ __('messages.notifications') }}</h3>
                    </div>
                    <span class="text-xs text-slate-500">{{ $notifCount }} {{ __('messages.items') }}</span>
                </div>

                <div class="max-h-[60vh] sm:max-h-96 overflow-y-auto">
                    @forelse($groupedNotifications as $group => $groupItems)
                        <div class="sticky top-0 z-10 px-4 py-1.5 bg-slate-100/95 backdrop-blur-sm border-y border-slate-200/60 flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $groupLabels[$group] ?? $group }}</span>
                            <span class="text-[11px] text-slate-400">{{ $groupItems->count() }}</span>
                        </div>
                        <div class="divide-y divide-gray-100">
                        @foreach($groupItems as $n)
                            @php
                                $c = $colorMap[$n['color']] ?? $colorMap['blue'];
                                $time = $n['time'] ? \Carbon\Carbon::parse($n['time']) : null;
                            @endphp
                            @if(!empty($n['url']))
                                <a href="{{ $n['url'] }}" class="block px-4 py-3 hover:bg-slate-50 transition">
                            @else
                                <div class="px-4 py-3">
                            @endif
                                <div class="flex items-start gap-3">
                                    <span class="flex-shrink-0 w-9 h-9 rounded-full {{ $c['bg'] }} {{ $c['text'] }} ring-1 {{ $c['ring'] }} flex items-center justify-center">
                                        <span class="material-icons text-base">{{ $n['icon'] }}</span>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $n['title'] }}</p>
                                            @if($time)
                                                <span class="text-[11px] text-slate-400 flex-shrink-0">{{ $time->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-slate-600 mt-0.5 break-words">{{ $n['message'] }}</p>
                                    </div>
                                </div>
                            @if(!empty($n['url']))
                                </a>
                            @else
                                </div>
                            @endif
                        @endforeach
                        </div>
                    @empty
                        <div class="px-4 py-10 text-center">
                            <span class="material-icons text-slate-300 text-4xl">notifications_off</span>
                            <p class="text-sm text-slate-500 mt-2">{{ __('messages.no_notifications') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endauth

        <div class="flex items-center space-x-2 text-muted">
            <span class="material-icons text-lg">account_circle</span>
            <span class="hidden sm:inline text-sm font-medium truncate max-w-[160px] lg:max-w-none text-token">{{ auth()->user()->name ?? __('messages.admin') }}</span>
        </div>
    </div>
</div>
