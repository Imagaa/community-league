<x-layouts::auth :title="__('Daftar')" :logo-landscape="true">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Buat akun baru')" :description="__('Lengkapi data di bawah untuk membuat akun')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Nama')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Nama lengkap')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat email')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Phone -->
            <flux:input
                name="phone"
                :label="__('Nomor HP')"
                :value="old('phone')"
                type="tel"
                required
                placeholder="08xxxxxxxxxx"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-password-strength-meter for="password" />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Konfirmasi password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Konfirmasi password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <x-password-match-hint for="password" confirm="password_confirmation" />

            {{--
                BUKAN <flux:checkbox>...isi...</flux:checkbox> -- versi Flux di proyek ini
                membuang isi slot default checkbox sepenuhnya, cuma prop `label`/`description`
                (teks polos) yang benar-benar dirender (lihat vendor/livewire/flux/stubs/
                resources/views/flux/with-inline-field.blade.php). Karena butuh tautan Syarat &
                Ketentuan/Kebijakan Privasi di dalam teksnya, pakai checkbox native + <label>
                manual supaya tautannya benar-benar muncul.
            --}}
            <div class="flex items-start gap-2">
                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    value="1"
                    @checked(old('terms'))
                    required
                    class="mt-1 size-[1.125rem] shrink-0 rounded-[.3rem] border-zinc-300 text-red-600 focus:ring-red-500 dark:border-navy-700 dark:bg-navy-950 dark:text-orange-600"
                />
                <label for="terms" class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('Saya menyetujui') }}
                    <a href="{{ route('legal.terms') }}" target="_blank" class="underline">{{ __('Syarat & Ketentuan') }}</a>
                    {{ __('dan') }}
                    <a href="{{ route('legal.privacy') }}" target="_blank" class="underline">{{ __('Kebijakan Privasi') }}</a>
                </label>
            </div>
            @error('terms')
                <flux:text color="red" size="sm">{{ $message }}</flux:text>
            @enderror

            @if (config('services.turnstile.site_key'))
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            @endif

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Buat Akun') }}
                </flux:button>
            </div>
        </form>

        @if (config('services.google.client_id'))
            <x-google-auth-button label="Daftar dengan Google" />
        @endif

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Sudah punya akun?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Masuk') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
