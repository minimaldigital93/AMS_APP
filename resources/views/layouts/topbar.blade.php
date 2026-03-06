<div id="topbarNav" class="w-full bg-gradient-to-r from-slate-900 to-slate-800 shadow-lg flex items-center justify-between px-8 py-3 transition-transform duration-300 -translate-y-0" style="opacity:1;">
    <!-- Logo/Brand -->
    <div class="flex items-center space-x-3">
        <x-application-logo class="w-12 h-12 fill-current text-white drop-shadow-lg" />
        <span class="font-bold text-xl text-white">{{ __('messages.ams') }}</span>
    </div>

    <!-- Right Actions -->
    <div class="flex items-center space-x-4">
        <!-- Language Switcher -->
        <form method="POST" action="{{ route('language.switch') }}" class="inline">
            @csrf
            <select name="locale" onchange="this.form.submit()" class="bg-slate-700 text-gray-200 text-sm rounded-lg border border-slate-600 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                <option value="en" {{ app()->getLocale() == 'en' ? 'selected' : '' }}>🇺🇸 EN</option>
                <option value="km" {{ app()->getLocale() == 'km' ? 'selected' : '' }}>🇰🇭 KM</option>
            </select>
        </form>

        <div class="flex items-center space-x-2 text-gray-300">
            <span class="material-icons text-lg">account_circle</span>
            <span class="text-sm font-medium">{{ auth()->user()->name ?? __('messages.admin') }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 text-white text-sm font-semibold rounded-lg hover:from-purple-700 hover:to-blue-700 transition-all duration-300 shadow-md hover:shadow-lg">
                {{ __('messages.logout') }}
            </button>
        </form>
    </div>
</div>
