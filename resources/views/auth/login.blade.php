<x-guest-layout>
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

    

        <div class="flex items-end justify-end mt-6">
       
            <x-primary-button class="login-btn ms-3 rounded-xl">
                {{ __('Sign In') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
