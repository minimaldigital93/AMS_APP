<x-guest-layout>
    <h2 class="login-title">{{ __('Create your account') }}</h2>

    @if (session('error'))
        <div class="mt-4 rounded-lg border border-red-400/40 bg-red-500/20 px-4 py-3 text-sm text-red-100">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('subscribe.store') }}"
          x-data="{ plan: '{{ $selected->slug }}', cycle: '{{ $cycle ?? 'monthly' }}', trials: {{ \Illuminate\Support\Js::from($plans->pluck('trial_days', 'slug')) }}, submitting: false }"
          @submit="submitting ? $event.preventDefault() : (submitting = true)">
        @csrf
        <input type="hidden" name="billing_cycle" x-model="cycle">

        <!-- Billing cycle toggle -->
        <div class="flex justify-center">
            <div class="inline-flex rounded-full border border-white/20 bg-white/5 p-1 text-xs font-medium text-white/80">
                <button type="button" @click="cycle = 'monthly'" :class="cycle === 'monthly' ? 'bg-indigo-500/40 text-white' : ''" class="rounded-full px-4 py-1.5">{{ __('messages.monthly') }}</button>
                <button type="button" @click="cycle = 'yearly'" :class="cycle === 'yearly' ? 'bg-indigo-500/40 text-white' : ''" class="rounded-full px-4 py-1.5">{{ __('messages.yearly') }}</button>
            </div>
        </div>

        <!-- Plan picker -->
        <div class="mt-4">
            <x-input-label :value="__('Selected plan')" class="form-label" />
            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5">
                @foreach($plans as $p)
                    <label class="cursor-pointer rounded-xl border p-3 text-center text-white/90 transition"
                           :class="plan === '{{ $p->slug }}' ? 'border-indigo-400 bg-indigo-500/30' : 'border-white/20 bg-white/5'">
                        <input type="radio" name="plan" value="{{ $p->slug }}" class="sr-only" x-model="plan">
                        <div class="text-sm font-semibold">{{ $p->name }}</div>
                        <div class="text-lg font-bold" x-show="cycle === 'monthly'">${{ rtrim(rtrim(number_format($p->price_usd, 2), '0'), '.') }}<span class="text-xs font-normal">/{{ __('mo') }}</span></div>
                        <div class="text-lg font-bold" x-show="cycle === 'yearly'" x-cloak>${{ rtrim(rtrim(number_format($p->hasYearly() ? $p->price_yearly_usd : $p->price_usd, 2), '0'), '.') }}<span class="text-xs font-normal">/{{ __('messages.year') }}</span></div>
                        <div class="mt-1 text-[11px] leading-tight opacity-80">
                            {{ $p->max_properties === null ? '∞' : $p->max_properties }} {{ __('messages.properties') }}<br>
                            {{ $p->max_rooms === null ? '∞' : $p->max_rooms }} {{ __('messages.rooms') }}<br>
                            {{ $p->max_staff === null ? '∞' : $p->max_staff }} {{ __('messages.staff') }}
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
            <x-primary-button class="login-btn rounded-xl" x-bind:disabled="submitting"
                              x-bind:class="submitting ? 'opacity-60 cursor-not-allowed' : ''">
                <span x-show="!submitting">{{ __('Continue to payment') }}</span>
                <span x-show="submitting" x-cloak class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                    </svg>
                    {{ __('Please wait…') }}
                </span>
            </x-primary-button>
        </div>

        <!-- Free trial (only for plans that offer one) -->
        {{-- Not disabled on submit: a disabled submit button is dropped from the
             form data set, which would strip start_trial=1. The form's @submit
             re-entry guard blocks the double-submit instead; this is visual only. --}}
        <button type="submit" name="start_trial" value="1" x-show="(trials[plan] || 0) > 0" x-cloak
            x-bind:class="submitting ? 'opacity-60 cursor-not-allowed pointer-events-none' : ''"
            class="mt-3 w-full rounded-xl border border-emerald-400/60 bg-emerald-500/20 py-2.5 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/30 transition">
            {{ __('Start free trial') }} (<span x-text="trials[plan] || 0"></span> {{ __('days') }})
        </button>
    </form>
</x-guest-layout>
