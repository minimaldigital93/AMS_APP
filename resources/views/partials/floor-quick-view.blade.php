{{--
    Floor quick-view popup — a minimal at-a-glance occupancy peek for the
    dashboard header. Expects $floorPlan (see HasDashboardMonthNavigation::buildFloorPlan):
    a collection of ['name', 'total', 'occupied', 'maintenance',
    'rooms' => [['number','occupied','maintenance'], …]].

    Maintenance rooms read gray and are labelled: they are out of the rentable
    stock, so the x/y counter deliberately excludes them on both sides.

    Must live inside an element carrying x-data="{ showFloorPeek: false }".
--}}

{{-- Trigger button (sits next to the Floor 3D button) --}}
<button type="button" @click="showFloorPeek = true"
        class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 hover:text-indigo-600 transition shadow-sm"
        title="{{ __('messages.floor_quick_view') }}" aria-label="{{ __('messages.floor_quick_view') }}">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 6v12a1 1 0 001 1h14a1 1 0 001-1V6M4 6l2-2h12l2 2M9 10h.01M13 10h.01M9 14h.01M13 14h.01"/>
    </svg>
</button>

{{-- Popup overlay --}}
<div x-cloak x-show="showFloorPeek"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition.opacity>
    {{-- backdrop --}}
    <div class="absolute inset-0 bg-slate-900/40" @click="showFloorPeek = false"></div>

    <div class="relative w-full max-w-lg max-h-[82vh] overflow-y-auto bg-white rounded-2xl shadow-xl border border-slate-100"
         @click.stop @keydown.escape.window="showFloorPeek = false">

        {{-- Header --}}
        <div class="sticky top-0 bg-white/95 backdrop-blur px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h3 class="text-sm font-semibold text-slate-800">{{ __('messages.floor_quick_view') }}</h3>
                <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>{{ __('messages.available') }}
                    <span class="w-2 h-2 rounded-full bg-sky-400 ml-2.5"></span>{{ __('messages.occupied') }}
                    <span class="w-2 h-2 rounded-full bg-slate-400 ml-2.5"></span>{{ __('messages.maintenance_short') }}
                </span>
            </div>
            <button type="button" @click="showFloorPeek = false"
                    class="opacity-60 hover:opacity-100 transition" aria-label="{{ __('messages.close') }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5 space-y-5">
            @forelse($floorPlan as $floor)
            <div class="rounded-xl bg-slate-50/60 border border-slate-100 px-4 py-3.5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-slate-700">{{ $floor['name'] }}</span>
                    <span class="inline-flex items-center gap-2 text-[11px] text-slate-400">
                        {{ $floor['occupied'] }}/{{ $floor['total'] }} {{ __('messages.occupied') }}
                        @if(($floor['maintenance'] ?? 0) > 0)
                        <span class="inline-flex items-center gap-1 text-slate-500" title="{{ __('messages.maintenance_mode') }}">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            {{ $floor['maintenance'] }} {{ __('messages.maintenance_short') }}
                        </span>
                        @endif
                    </span>
                </div>
                @if($floor['rooms']->isEmpty())
                    <p class="text-xs text-slate-300">{{ __('messages.no_data') }}</p>
                @else
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                    @foreach($floor['rooms'] as $room)
                    @php
                        $isMaintenance = $room['maintenance'] ?? false;
                        $roomStatus = $isMaintenance
                            ? __('messages.maintenance_short')
                            : ($room['occupied'] ? __('messages.occupied') : __('messages.available'));
                    @endphp
                    {{-- Maintenance chips read gray and dashed so they're
                         distinguishable from a vacant room at a glance. --}}
                    <span class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-md border text-xs font-medium {{ $isMaintenance ? 'bg-slate-50 border-dashed border-slate-300 text-slate-400' : 'bg-white border-slate-200 text-slate-600' }}"
                          title="{{ $room['number'] }} — {{ $roomStatus }}">
                        @if($isMaintenance)
                        <span class="material-icons text-[12px] leading-none text-slate-400">handyman</span>
                        @else
                        <span class="w-2 h-2 shrink-0 rounded-full {{ $room['occupied'] ? 'bg-sky-400' : 'bg-emerald-500' }}"></span>
                        @endif
                        {{ $room['number'] }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
            @empty
            <p class="text-sm text-slate-400 text-center py-8">{{ __('messages.no_data') }}</p>
            @endforelse
        </div>
    </div>
</div>
