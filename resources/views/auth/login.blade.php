<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <a href="{{ route('login') }}" class="inline-flex items-center justify-center">
                <img
                    src="{{ asset('images/qits-logo.svg') }}"
                    alt="QITS"
                    class="h-14 w-auto max-w-[220px] object-contain"
                >
            </a>
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <div class="mt-4">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-jet-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-jet-checkbox id="remember_me" name="remember" />
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <button
                    type="submit"
                    class="ml-4 inline-flex items-center px-4 py-2 bg-[#23545B] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition hover:bg-[#F7B32B] hover:text-[#1A4D2E] focus:bg-[#F7B32B] focus:text-[#1A4D2E] focus:outline-none focus:ring-2 focus:ring-[#F7B32B] focus:ring-offset-2 active:bg-[#1A4D2E]"
                >
                    {{ __('Log in') }}
                </button>
            </div>
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
