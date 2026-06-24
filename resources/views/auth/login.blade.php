<x-guest-layout>
    @php($plans = $plans ?? \App\Models\Plan::where('is_active', true)->orderBy('price_usd')->get())

    {{-- Plan grid adapts to however many plans the superadmin has active, so the
         layout always fits (no stretched/empty columns when < 5, no awkward wrap
         when > 5). Only classes already present in the compiled CSS are used. --}}
    @php($planGridCols = match(true) {
        $plans->count() <= 1 => 'max-w-sm',
        $plans->count() === 2 => 'sm:grid-cols-2 max-w-2xl',
        $plans->count() === 3 => 'sm:grid-cols-2 lg:grid-cols-3 max-w-4xl',
        $plans->count() === 4 => 'sm:grid-cols-2 lg:grid-cols-4 max-w-5xl',
        default => 'sm:grid-cols-2 lg:grid-cols-5',
    })

    {{-- Subscribe button + pricing modal — pushed to the layout's overlays stack so
         they sit on top of the whole page, not inside the login card. --}}
    @push('overlays')
        <div x-data="{ open: false, cycle: 'monthly' }">
            <button type="button" @click="open = true"
                    class="fixed top-4 right-4 z-40 inline-flex items-center gap-1.5 rounded-full bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-lg hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400 sm:gap-2 sm:px-5 sm:py-2.5 sm:text-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                {{ __('Subscribe') }}
            </button>

            <!-- Modal -->
            <div x-show="open" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
                 x-transition.opacity>
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>

                <div class="relative w-full max-w-6xl max-h-[90vh] overflow-y-auto rounded-3xl bg-white p-8 shadow-2xl sm:p-12" @keydown.escape.window="open = false">
                    <button type="button" @click="open = false" class="absolute top-5 right-5 text-gray-400 hover:text-gray-600">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>

                    <h3 class="text-center text-3xl font-bold text-gray-900">{{ __('Choose your plan') }}</h3>
                    <p class="mt-2 text-center text-base text-gray-500">{{ __('Pay via KHQR. Cancel anytime.') }}</p>

                    <!-- Billing cycle toggle -->
                    <div class="mt-6 flex justify-center">
                        <div class="inline-flex rounded-full bg-gray-100 p-1 text-sm font-medium">
                            <button type="button" @click="cycle = 'monthly'" :class="cycle === 'monthly' ? 'bg-white text-gray-900 shadow' : 'text-gray-500'" class="rounded-full px-5 py-1.5">{{ __('messages.monthly') }}</button>
                            <button type="button" @click="cycle = 'yearly'" :class="cycle === 'yearly' ? 'bg-white text-gray-900 shadow' : 'text-gray-500'" class="rounded-full px-5 py-1.5">{{ __('messages.yearly') }}</button>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-6 mx-auto {{ $planGridCols }}">
                        @foreach($plans as $plan)
                            @php($popular = $plan->slug === 'basic')
                            <div class="relative flex flex-col rounded-2xl border p-6 {{ $popular ? 'border-indigo-500 ring-2 ring-indigo-500 shadow-lg' : 'border-gray-200' }}">
                                @if($popular)
                                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">{{ __('Most popular') }}</span>
                                @endif
                                <h4 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h4>
                                <p class="mt-2">
                                    <span class="text-4xl font-extrabold text-gray-900" x-show="cycle === 'monthly'">${{ rtrim(rtrim(number_format($plan->price_usd, 2), '0'), '.') }}</span>
                                    <span class="text-sm text-gray-500" x-show="cycle === 'monthly'">/{{ __('mo') }}</span>
                                    <span class="text-4xl font-extrabold text-gray-900" x-show="cycle === 'yearly'" x-cloak>${{ rtrim(rtrim(number_format($plan->hasYearly() ? $plan->price_yearly_usd : $plan->price_usd, 2), '0'), '.') }}</span>
                                    <span class="text-sm text-gray-500" x-show="cycle === 'yearly'" x-cloak>/{{ __('messages.year') }}</span>
                                </p>
                                <ul class="mt-5 space-y-3 text-sm text-gray-600">
                                    @php($features = [
                                        $plan->max_properties === null ? __('messages.unlimited_properties') : $plan->max_properties.' '.__('messages.properties'),
                                        $plan->max_rooms === null ? __('messages.unlimited_rooms') : $plan->max_rooms.' '.__('messages.rooms'),
                                        __('messages.unlimited_floors'),
                                        $plan->max_staff === null ? __('messages.unlimited_staff') : $plan->max_staff.' '.__('messages.staff'),
                                    ])
                                    @foreach($features as $feature)
                                        <li class="flex items-center gap-2">
                                            <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                                <a x-bind:href="'{{ route('subscribe.create', ['plan' => $plan->slug]) }}?billing_cycle=' + cycle"
                                   class="mt-6 block rounded-xl px-4 py-2.5 text-center text-sm font-semibold {{ $popular ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-900 text-white hover:bg-gray-700' }}">
                                    {{ __('Get started') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endpush

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <h2 class="login-title">AMS</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Phone Number -->
        <div>
            <x-input-label for="phone" :value="__('Phone Number')" class="form-label" />
            <x-text-input id="phone" class="form-input block mt-1 w-full rounded-xl" type="text" name="phone" :value="old('phone')" required autofocus autocomplete="username" :placeholder="__('messages.enter_phone')" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="form-label" />

            <x-text-input id="password" class="form-input block mt-1 w-full rounded-xl"
                            type="password"
                            name="password"
                            required autocomplete="current-password" :placeholder="__('messages.enter_password')" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

    

        <div class="flex justify-end mt-6">
            <x-primary-button class="login-btn w-full justify-center sm:w-auto rounded-xl">
                {{ __('Sign In') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
