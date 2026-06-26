@auth
@php
    $properties = $topbarProperties ?? collect();
    $activeProp = $topbarActiveProperty ?? null;
    $selectorEnabled = $topbarPropertySelectorEnabled ?? false;
    $showingAll = $topbarShowingAllProperties ?? false;
@endphp

@if($properties->isNotEmpty())
    @if($selectorEnabled)
        {{-- Interactive selector: more than one accessible property --}}
        <div
            class="relative"
            x-data="{ open: false, q: '', loading: false }"
            @keydown.escape.window="open = false"
            @click.outside="open = false"
        >
            <button
                type="button"
                @click="open = !open"
                :disabled="loading"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-token hover:bg-hover focus:outline-none focus:ring-2 focus:ring-token transition max-w-[150px] sm:max-w-[220px]"
                :aria-expanded="open.toString()"
                aria-label="{{ __('messages.switch_property') }}"
                title="{{ __('messages.switch_property') }}">
                <span class="material-icons text-base sm:text-lg flex-shrink-0">{{ $showingAll ? 'apps' : 'apartment' }}</span>
                <span class="text-sm font-medium truncate">{{ $showingAll ? __('messages.all_properties') : ($activeProp?->name ?? __('messages.select_property')) }}</span>
                <svg x-show="!loading" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                <svg x-show="loading" x-cloak class="w-4 h-4 flex-shrink-0 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </button>

            {{-- Hidden POST form: a chosen option fills property_id and submits --}}
            <form method="POST" action="{{ route('property.switch') }}" x-ref="form" class="hidden">
                @csrf
                <input type="hidden" name="property_id" x-ref="pid">
            </form>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute right-0 mt-2 w-[80vw] max-w-xs sm:w-72 bg-white rounded-xl shadow-2xl ring-1 ring-black/5 z-50 overflow-hidden"
                role="menu">
                <div class="px-3 py-2.5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-2 text-slate-700">
                        <span class="material-icons text-base">apartment</span>
                        <h3 class="font-semibold text-sm">{{ __('messages.property') }}</h3>
                    </div>
                    @if($properties->count() > 5)
                        <div class="mt-2 relative">
                            <span class="material-icons absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-base">search</span>
                            <input
                                type="text"
                                x-model="q"
                                @click.stop
                                placeholder="{{ __('messages.search_properties') }}"
                                class="w-full pl-8 pr-3 py-1.5 text-sm border border-slate-200 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                        </div>
                    @endif
                </div>

                <div class="max-h-72 overflow-y-auto py-1">
                    {{-- Consolidated view across every accessible property --}}
                    <button
                        type="button"
                        x-show="q === ''"
                        @click="loading = true; $refs.pid.value = 0; $refs.form.submit()"
                        class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left text-sm transition hover:bg-slate-50 border-b border-gray-100 {{ $showingAll ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-700' }}">
                        <span class="material-icons text-base flex-shrink-0 {{ $showingAll ? 'text-sky-600' : 'text-slate-400' }}">apps</span>
                        <span class="truncate flex-1">{{ __('messages.all_properties') }}</span>
                        @if($showingAll)
                            <span class="material-icons text-base text-sky-600 flex-shrink-0">check</span>
                        @endif
                    </button>
                    @foreach($properties as $prop)
                        @php($isActive = $activeProp && $activeProp->id === $prop->id)
                        <button
                            type="button"
                            x-show="q === '' || @js(\Illuminate\Support\Str::lower($prop->name)).includes(q.toLowerCase())"
                            @click="loading = true; $refs.pid.value = {{ $prop->id }}; $refs.form.submit()"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left text-sm transition hover:bg-slate-50 {{ $isActive ? 'bg-sky-50 text-sky-700 font-semibold' : 'text-slate-700' }}">
                            <span class="material-icons text-base flex-shrink-0 {{ $isActive ? 'text-sky-600' : 'text-slate-400' }}">apartment</span>
                            <span class="truncate flex-1">{{ $prop->name }}</span>
                            @if($isActive)
                                <span class="material-icons text-base text-sky-600 flex-shrink-0">check</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        {{-- Single accessible property: static, non-interactive context label --}}
        <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-token" title="{{ $activeProp?->name }}">
            <span class="material-icons text-base sm:text-lg flex-shrink-0">apartment</span>
            <span class="text-sm font-medium truncate max-w-[150px] sm:max-w-[220px]">{{ $activeProp?->name }}</span>
        </div>
    @endif
@endif
@endauth
