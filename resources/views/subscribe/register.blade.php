<x-guest-layout>
    <h2 class="login-title">{{ __('Create your account') }}</h2>

    <form method="POST" action="{{ route('subscribe.store') }}"
          x-data="{ plan: '{{ $selected->slug }}', trials: {{ \Illuminate\Support\Js::from($plans->pluck('trial_days', 'slug')) }} }">
        @csrf

        <!-- Plan picker -->
        <div>
            <x-input-label :value="__('Selected plan')" class="form-label" />
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach($plans as $p)
                    <label class="cursor-pointer rounded-xl border p-3 text-center text-white/90 transition"
                           :class="plan === '{{ $p->slug }}' ? 'border-indigo-400 bg-indigo-500/30' : 'border-white/20 bg-white/5'">
                        <input type="radio" name="plan" value="{{ $p->slug }}" class="sr-only" x-model="plan">
                        <div class="text-sm font-semibold">{{ $p->name }}</div>
                        <div class="text-lg font-bold">${{ rtrim(rtrim(number_format($p->price_usd, 2), '0'), '.') }}<span class="text-xs font-normal">/{{ __('mo') }}</span></div>
                        <div class="mt-1 text-[11px] leading-tight opacity-80">
                            {{ $p->max_floors === null ? '∞' : $p->max_floors }} {{ __('floors') }}<br>
                            {{ $p->max_apartments === null ? '∞' : $p->max_apartments }} {{ __('apts') }}
                        </div>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('plan')" class="mt-2" />
        </div>

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Name')" class="form-label" />
            <x-text-input id="name" class="form-input block mt-1 w-full rounded-xl" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Phone -->
        <div class="mt-4">
            <x-input-label for="phone" :value="__('Phone Number')" class="form-label" />
            <x-text-input id="phone" class="form-input block mt-1 w-full rounded-xl" type="text" name="phone" :value="old('phone')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="form-label" />
            <x-text-input id="password" class="form-input block mt-1 w-full rounded-xl" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="form-label" />
            <x-text-input id="password_confirmation" class="form-input block mt-1 w-full rounded-xl" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-6 flex items-center justify-between">
            <a class="text-sm text-white/80 underline hover:text-white" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>
            <x-primary-button class="login-btn rounded-xl">
                {{ __('Continue to payment') }}
            </x-primary-button>
        </div>

        <!-- Free trial (only for plans that offer one) -->
        <button type="submit" name="start_trial" value="1" x-show="(trials[plan] || 0) > 0" x-cloak
            class="mt-3 w-full rounded-xl border border-emerald-400/60 bg-emerald-500/20 py-2.5 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/30 transition">
            {{ __('Start free trial') }} (<span x-text="trials[plan] || 0"></span> {{ __('days') }})
        </button>
    </form>
</x-guest-layout>
