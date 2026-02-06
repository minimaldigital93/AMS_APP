<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <h2 class="login-title">AMS</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="form-label" />
            <x-text-input id="email" class="form-input block mt-1 w-full rounded-xl" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="Enter your email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="form-label" />

            <x-text-input id="password" class="form-input block mt-1 w-full rounded-xl"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="Enter your password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="form-checkbox rounded" name="remember">
                <span class="ms-2 text-sm form-text">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-6">
            @if (Route::has('password.request'))
                <a class="form-link underline text-sm rounded-md" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif

            <x-primary-button class="login-btn ms-3 rounded-xl">
                {{ __('Sign In') }}
            </x-primary-button>
        </div>

        <div class="divider">
            <span>or</span>
        </div>

        <!-- Register -->
        <div class="text-center">
            <span class="form-text text-sm">Don't have an account?</span>
            <a href="{{ route('register') }}" class="form-link text-sm font-semibold ml-1">
                {{ __('Create Account') }}
            </a>
        </div>
    </form>
</x-guest-layout>
