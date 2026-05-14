<div id="topbarNav" class="w-full bg-gradient-to-r from-slate-900 to-slate-800 shadow-lg flex items-center justify-between px-3 sm:px-6 lg:px-8 py-2 sm:py-3 transition-transform duration-300 -translate-y-0" style="opacity:1;">
    <!-- Left: Hamburger (mobile only) + Logo/Brand -->
    <div class="flex items-center space-x-2 sm:space-x-3 min-w-0">
        <button
            type="button"
            @click="mobileOpen = !mobileOpen"
            class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30"
            :aria-expanded="mobileOpen.toString()"
            aria-label="Toggle navigation">
            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <x-application-logo class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 fill-current text-white drop-shadow-lg flex-shrink-0" />
        <span class="font-bold text-base sm:text-lg lg:text-xl text-white truncate">{{ __('messages.ams') }}</span>
    </div>

    <!-- Right Actions -->
    <div class="flex items-center space-x-2 sm:space-x-4">
        <div class="flex items-center space-x-2 text-gray-300">
            <span class="material-icons text-lg">account_circle</span>
            <span class="hidden sm:inline text-sm font-medium truncate max-w-[160px] lg:max-w-none">{{ auth()->user()->name ?? __('messages.admin') }}</span>
        </div>
    </div>
</div>
