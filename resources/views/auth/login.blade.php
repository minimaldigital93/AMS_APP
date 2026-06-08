<x-guest-layout>
    @php($plans = $plans ?? \App\Models\Plan::where('is_active', true)->orderBy('price_usd')->get())

    {{-- Subscribe button + pricing modal — pushed to the layout's overlays stack so
         they sit on top of the whole page, not inside the login card. --}}
    @push('overlays')
        <div x-data="{ open: false }">
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
                    <p class="mt-2 text-center text-base text-gray-500">{{ __('Pay monthly via KHQR. Cancel anytime.') }}</p>

                    <div class="mt-8 grid gap-6 sm:grid-cols-3">
                        @foreach($plans as $plan)
                            @php($popular = $plan->slug === 'pro')
                            <div class="relative flex flex-col rounded-2xl border p-6 {{ $popular ? 'border-indigo-500 ring-2 ring-indigo-500 shadow-lg' : 'border-gray-200' }}">
                                @if($popular)
                                    <span class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">{{ __('Most popular') }}</span>
                                @endif
                                <h4 class="text-lg font-semibold text-gray-900">{{ $plan->name }}</h4>
                                <p class="mt-2">
                                    <span class="text-4xl font-extrabold text-gray-900">${{ rtrim(rtrim(number_format($plan->price_usd, 2), '0'), '.') }}</span>
                                    <span class="text-sm text-gray-500">/{{ __('mo') }}</span>
                                </p>
                                <ul class="mt-5 space-y-3 text-sm text-gray-600">
                                    <li class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        {{ $plan->max_floors === null ? __('Unlimited floors') : $plan->max_floors.' '.__('floors') }}
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                        {{ $plan->max_apartments === null ? __('Unlimited apartments') : $plan->max_apartments.' '.__('apartments') }}
                                    </li>
                                </ul>
                                <a href="{{ route('subscribe.create', ['plan' => $plan->slug]) }}"
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
