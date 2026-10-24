<x-layouts::public :title="__('Membership')">
    <h1 class="font-display text-2xl font-semibold tracking-wide text-zinc-900 lg:text-3xl dark:text-white">{{ __('Community League Membership') }}</h1>
    <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ __('Jadi member dan nikmati keuntungan lebih di setiap match.') }}</p>

    <ul class="mt-6 flex flex-col gap-3">
        @foreach ($benefits as $benefit)
            <li class="flex items-start gap-3">
                <flux:icon :name="$benefit->icon" class="mt-1 size-5 shrink-0 text-red-600 dark:text-orange-500" />
                <span>{{ $benefit->text }}</span>
            </li>
        @endforeach
    </ul>

    <p class="mt-6 text-lg font-bold text-zinc-900 dark:text-white">
        {{ \App\Support\Money::rupiah($settings->price) }}
        <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400">/ {{ $settings->duration_days }} {{ __('hari') }}</span>
    </p>

    <div class="mt-4">
        @guest
            <form method="POST" action="{{ route('membership.login-intent') }}">
                @csrf
                <flux:button type="submit">{{ __('Login untuk Aktifkan') }}</flux:button>
            </form>
        @else
            @unless (auth()->user()->hasVerifiedEmail())
                <div class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                    <flux:icon name="exclamation-triangle" class="size-5 shrink-0" />
                    <span class="flex-1">{{ __('Verifikasi email Anda dulu sebelum membeli membership.') }}</span>
                    <flux:link :href="route('verification.notice')" wire:navigate class="shrink-0 font-medium">
                        {{ __('Verifikasi sekarang') }}
                    </flux:link>
                </div>
            @else
                <form method="POST" action="{{ route('membership.purchase') }}">
                    @csrf
                    <flux:button type="submit">{{ __('Aktifkan Membership') }}</flux:button>
                </form>
            @endunless

            <a href="{{ route('membership.redemptions') }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-red-600 underline dark:text-orange-400">
                {{ __('Riwayat penukaran') }}
            </a>
        @endguest
    </div>
</x-layouts::public>
