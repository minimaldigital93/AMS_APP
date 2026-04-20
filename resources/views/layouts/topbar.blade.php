<div id="topbarNav" class="w-full bg-gradient-to-r from-slate-900 to-slate-800 shadow-lg flex items-center justify-between px-8 py-3 transition-transform duration-300 -translate-y-0" style="opacity:1;">
    <!-- Logo/Brand -->
    <div class="flex items-center space-x-3">
        <x-application-logo class="w-12 h-12 fill-current text-white drop-shadow-lg" />
        <span class="font-bold text-xl text-white">{{ __('messages.ams') }}</span>
    </div>

    <!-- Right Actions -->
    <div class="flex items-center space-x-4">
        <div class="flex items-center space-x-2 text-gray-300">
            <span class="material-icons text-lg">account_circle</span>
            <span class="text-sm font-medium">{{ auth()->user()->name ?? __('messages.admin') }}</span>
        </div>
      
    </div>
</div>
